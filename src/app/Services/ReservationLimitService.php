<?php

namespace App\Services;

use App\Models\Item;
use App\Repositories\Interfaces\ReservationRepositoryInterface;

/**
 * 予約可能数サービス
 *
 * 商品の予約可能数（items.reservation_limit）と、すでに予約された数量の合計から、残数を求める。
 * 予約可能数が未設定（null）の商品は、制限なし。
 */
class ReservationLimitService
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {}

    /**
     * 予約できる残りの数量を取得する（制限なしは null、予約可能数に達していれば 0）
     */
    public function remaining(Item $item): ?int
    {
        if ($item->reservation_limit === null) {
            return null;
        }

        return max(0, $item->reservation_limit - $this->reservationRepository->getReservedQuantity($item->id));
    }

    /**
     * 予約可能数に達していて、予約を受け付けていないかどうか
     */
    public function isClosed(Item $item): bool
    {
        return $this->isClosedByRemaining($this->remaining($item));
    }

    /**
     * 残数から、予約を受け付けていないかどうかを判定する（残数を取得済みのとき用。集計を繰り返さない）
     */
    public function isClosedByRemaining(?int $remaining): bool
    {
        return $remaining === 0;
    }

    /**
     * 1回の予約で入力できる数量の上限（1商品の上限と残数の小さい方）
     */
    public function maxQuantity(Item $item): int
    {
        return $this->maxQuantityByRemaining($this->remaining($item));
    }

    /**
     * 残数から、1回の予約で入力できる数量の上限を求める（残数を取得済みのとき用。集計を繰り返さない）
     */
    public function maxQuantityByRemaining(?int $remaining): int
    {
        return min(CartService::MAX_QUANTITY, $remaining ?? CartService::MAX_QUANTITY);
    }
}
