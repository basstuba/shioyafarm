<?php

namespace App\Services;

use App\Enums\CartAddResult;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * カートサービス
 *
 * カートはセッションのキー `cart` に `[商品ID => 数量]` で保存する。
 */
class CartService
{
    /** セッションのキー */
    private const string SESSION_KEY = 'cart';

    /** 1商品の数量の下限 */
    public const int MIN_QUANTITY = 1;

    /** 1商品の数量の上限 */
    public const int MAX_QUANTITY = 10;

    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly Session $session,
    ) {}

    /**
     * カートの商品を1個追加する
     *
     * すでにある商品は数量を1増やす。上限に達している商品、存在しない商品、予約限定の商品は追加しない。
     */
    public function addItem(int $itemId): CartAddResult
    {
        try {
            $item = $this->itemRepository->findById($itemId);
        } catch (ModelNotFoundException) {
            return CartAddResult::Rejected;
        }

        if ($item->is_reserved_only) {
            return CartAddResult::Rejected;
        }

        $quantities = $this->getQuantities();
        $current = $quantities[$item->id] ?? 0;
        if ($current >= self::MAX_QUANTITY) {
            return CartAddResult::LimitReached;
        }

        $quantities[$item->id] = $current + 1;
        $this->session->put(self::SESSION_KEY, $quantities);

        return CartAddResult::Added;
    }

    /**
     * カートに入っている商品の数量を取得する（カートにない商品は null）
     */
    public function getQuantity(int $itemId): ?int
    {
        return $this->getQuantities()[$itemId] ?? null;
    }

    /**
     * カートに入っている商品の数量を変更する（カートにない商品は何もしない）
     *
     * 数量の範囲は呼び出し側でバリデーションしておく。
     */
    public function updateQuantity(int $itemId, int $quantity): void
    {
        $quantities = $this->getQuantities();
        if (! array_key_exists($itemId, $quantities)) {
            return;
        }

        $quantities[$itemId] = max(self::MIN_QUANTITY, min(self::MAX_QUANTITY, $quantity));
        $this->session->put(self::SESSION_KEY, $quantities);
    }

    /**
     * カートから商品を削除する
     */
    public function removeItem(int $itemId): void
    {
        $quantities = $this->getQuantities();
        unset($quantities[$itemId]);
        $this->session->put(self::SESSION_KEY, $quantities);
    }

    /**
     * カートを空にする
     */
    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * カートの行を商品ID順に取得する
     *
     * DBに存在しない商品と予約限定の商品は、行にも小計にも含めず（エラーにはしない）、セッションのカートからも取り除く。
     *
     * @return Collection<int, CartLine>
     */
    public function getLines(): Collection
    {
        $quantities = $this->getQuantities();
        if ($quantities === []) {
            return collect();
        }

        $items = $this->itemRepository->findByIds(array_keys($quantities))->keyBy('id');

        $lines = collect();
        $unavailableIds = [];
        foreach ($quantities as $itemId => $quantity) {
            $item = $items->get($itemId);
            if ($item === null || $item->is_reserved_only) {
                $unavailableIds[] = $itemId;

                continue;
            }
            $lines->push(new CartLine($item, $quantity));
        }

        if ($unavailableIds !== []) {
            $this->session->put(self::SESSION_KEY, array_diff_key($quantities, array_flip($unavailableIds)));
        }

        return $lines;
    }

    /**
     * カートの小計（全商品の金額の合計）を計算する
     *
     * @param  Collection<int, CartLine>  $lines
     */
    public function calculateSubtotal(Collection $lines): int
    {
        return $lines->sum(fn (CartLine $line): int => $line->amount);
    }

    /**
     * セッションのカートを `[商品ID => 数量]` で取得する（不正な値は取り除く）
     *
     * @return array<int, int>
     */
    private function getQuantities(): array
    {
        $stored = $this->session->get(self::SESSION_KEY, []);
        if (! is_array($stored)) {
            return [];
        }

        $quantities = [];
        foreach ($stored as $itemId => $quantity) {
            if (is_int($itemId) && $itemId > 0 && is_int($quantity)) {
                $quantities[$itemId] = max(self::MIN_QUANTITY, min(self::MAX_QUANTITY, $quantity));
            }
        }
        ksort($quantities);

        return $quantities;
    }
}
