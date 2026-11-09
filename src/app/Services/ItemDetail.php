<?php

namespace App\Services;

use App\Models\Item;

/**
 * 管理画面の掲載商品一覧の詳細モーダルに表示する内容
 *
 * @see AdminItemService::findDetail()
 */
final readonly class ItemDetail
{
    /**
     * @param  Item  $item  商品（画像の表示に使う）
     * @param  int|null  $remaining  予約の残り数（予約限定で上限があるときだけ。ReservationLimitService::remaining と同じ数）
     */
    public function __construct(
        public Item $item,
        public ?int $remaining,
    ) {}

    /**
     * 予約可能数の表示（通常の商品は「無」、予約限定で上限がなければ「上限なし」、上限があれば「200（残り150）」）
     */
    public function reservationLimitLabel(): string
    {
        if (! $this->item->is_reserved_only) {
            return '無';
        }

        if ($this->item->reservation_limit === null) {
            return '上限なし';
        }

        return $this->item->reservation_limit.'（残り'.($this->remaining ?? 0).'）';
    }
}
