<?php

namespace App\Services;

use App\Models\Item;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * 管理画面の掲載商品一覧サービス（一覧・詳細・削除）
 */
class AdminItemService
{
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly ItemService $itemService,
        private readonly ReservationLimitService $reservationLimitService,
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
}
