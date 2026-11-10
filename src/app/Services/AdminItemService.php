<?php

namespace App\Services;

use App\Models\Item;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use App\Repositories\Interfaces\ReservationRepositoryInterface;
use App\Services\ProductImage\PreparedImage;
use App\Services\ProductImage\ProductImageStorageInterface;
use App\Support\ItemLabel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * 管理画面の掲載商品サービス（一覧・詳細・登録・修正・削除）
 */
class AdminItemService
{
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly ItemService $itemService,
        private readonly ReservationLimitService $reservationLimitService,
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly ProductImageStorageInterface $images,
    ) {}

    /**
     * 掲載中の商品を全件取得（商品名ごとにまとめ、まとまりは同じ商品名の最小IDの順、その中は重さの軽い順、同じ重さはID順。削除済みは含まない）
     *
     * @return Collection<int, Item>
     */
    public function listItems(): Collection
    {
        return $this->itemService->getItemsGroupedByName()->flatMap(fn (EloquentCollection $items): EloquentCollection => $items)->values();
    }

    /**
     * 商品の詳細を取得（存在しない・削除済みの商品は null）
     */
    public function findDetail(int $id): ?ItemDetail
    {
        $item = $this->itemRepository->findByIds([$id])->first();
        if ($item === null) {
            return null;
        }

        // 予約の残りは、予約限定で上限がある商品だけ求める
        $remaining = $item->is_reserved_only ? $this->reservationLimitService->remaining($item) : null;

        return new ItemDetail($item, $remaining);
    }

    /**
     * 商品を論理削除する（削除済み・存在しない商品は何も変えず false）
     *
     * 過去の注文・予約は、注文した時点の商品名・重さを持っているため、そのまま残る。
     */
    public function delete(int $id): bool
    {
        return $this->itemRepository->softDelete($id);
    }

    /**
     * 修正ページに出す商品を取得（存在しない・削除済みの商品は null）
     */
    public function findForEdit(int $id): ?Item
    {
        return $this->itemRepository->find($id);
    }

    /**
     * 商品を登録する
     *
     * 同じ商品名・重さの商品がすでにあるときは、保存せずに ValidationException（name）を投げる。
     * 画像の読み込み・縮小・書き出しの重い処理は、ロックを取るトランザクションの前に済ませる（読めない画像・大きすぎる画像は、ここで image の ValidationException）。
     * ファイルを書くのは、重複の確認が通ってから（トランザクションの中。失敗したのに保存することがない）。書いたあとにDBへの登録が失敗したときだけ、そのファイルを消す。
     *
     * @throws ValidationException
     */
    public function create(ItemInput $input): Item
    {
        $prepared = $this->images->prepare($input->image ?? throw new InvalidArgumentException('商品画像がありません。'));
        $path = null;

        try {
            return $this->itemRepository->create(
                $this->attributes($input),
                function (bool $duplicated) use ($input, $prepared, &$path): array {
                    $this->assertSavable($input, null, $duplicated);

                    return ['img_url' => $this->saveOnce($prepared, $path)];
                },
            );
        } catch (Throwable $e) {
            $this->discardImage($path);

            throw $e;
        }
    }

    /**
     * 商品を修正する（存在しない・削除済みの商品は何も変えず false）
     *
     * 同じ商品名・重さの別の商品があるとき（name）と、予約可能数をすでに予約された数より小さくするとき（reservation_limit）は、
     * 保存せずに ValidationException を投げる。画像を選ばなかったときは今の画像のまま。
     * 新しい画像の読み込み・縮小・書き出しの重い処理は、ロックを取るトランザクションの前に済ませる（読めない画像・大きすぎる画像は、ここで image の ValidationException）。
     * ファイルを書くのは、商品の存在・重複・予約済みの数の確認が通ってから（トランザクションの中。失敗したのに保存することがない）。書いたあとにDBの更新が失敗したときだけ、そのファイルを消す。
     * 画像を差し替えて更新が確定（コミット）したあとに、古い画像が uploads の中のファイルなら消す（シードの画像は消さない。更新が失敗したときも消さない）。
     *
     * @throws ValidationException
     */
    public function update(int $id, ItemInput $input): bool
    {
        $prepared = $input->image === null ? null : $this->images->prepare($input->image);
        $path = null;
        $oldPath = null;

        try {
            $updated = $this->itemRepository->update(
                $id,
                $this->attributes($input),
                function (Item $item, bool $duplicated) use ($input, $prepared, &$path, &$oldPath): array {
                    $this->assertSavable($input, $item, $duplicated);

                    if ($prepared === null) {
                        return [];
                    }

                    $oldPath = $item->img_url;

                    return ['img_url' => $this->saveOnce($prepared, $path)];
                },
            );
        } catch (Throwable $e) {
            $this->discardImage($path);

            throw $e;
        }

        // コミットされたあとに、差し替えた古い画像を消す（uploads の外のファイルは、delete が消さない）
        if ($updated && $path !== null && $oldPath !== null && $oldPath !== $path) {
            $this->images->delete($oldPath);
        }

        return $updated;
    }

    /**
     * 用意した画像をファイルに書く（デッドロックの再試行で closure が何度呼ばれても、二度書かない）
     */
    private function saveOnce(PreparedImage $prepared, ?string &$path): string
    {
        return $path ??= $this->images->save($prepared);
    }

    /**
     * 保存する項目（画像のパス以外）
     *
     * @return array{name: string, weight: int, price: int, detail: string, recommend: bool, is_reserved_only: bool, reservation_limit: int|null}
     */
    private function attributes(ItemInput $input): array
    {
        return [
            'name' => $input->name,
            'weight' => $input->weight,
            'price' => $input->price,
            'detail' => $input->detail,
            'recommend' => $input->recommend,
            'is_reserved_only' => $input->isReservedOnly,
            // 通常の商品・上限なしは null（呼び出し側が通常のときの数を捨てているが、ここでも通常なら必ず null にする）
            'reservation_limit' => $input->isReservedOnly ? $input->reservationLimit : null,
        ];
    }

    /**
     * 保存してよいか確かめる（ロックを取ったあとの、保存の直前に呼ばれる。保存できないときは ValidationException）
     *
     * @param  Item|null  $current  修正する商品（登録のときは null）
     * @param  bool  $duplicated  削除していない別の商品に、同じ商品名・重さがあるか（リポジトリがロックした行で判定した結果）
     *
     * @throws ValidationException
     */
    private function assertSavable(ItemInput $input, ?Item $current, bool $duplicated): void
    {
        $messages = [];

        if ($duplicated) {
            $messages['name'] = ItemLabel::of($input->name, $input->weight).' はすでに登録されています';
        }

        if ($current !== null && $input->isReservedOnly && $input->reservationLimit !== null) {
            $reserved = $this->reservationRepository->getReservedQuantity($current->id);
            if ($input->reservationLimit < $reserved) {
                $messages['reservation_limit'] = "すでに{$reserved}個の予約があるため、{$reserved}以上にしてください";
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    /**
     * 保存したけれど使わなかった新しい画像のファイルを消す（まだ保存していなければ何もしない）
     */
    private function discardImage(?string $path): void
    {
        if ($path !== null) {
            $this->images->delete($path);
        }
    }
}
