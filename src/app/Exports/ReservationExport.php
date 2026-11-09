<?php

namespace App\Exports;

use App\Enums\AdminListType;
use App\Models\Reservation;
use Generator;
use Illuminate\Support\LazyCollection;
use Override;

/**
 * 管理画面の予約一覧のエクスポート（1予約1行）
 *
 * 予約は500件ずつに分けて読み込む。列とセルの書き方は AdminOrderExport。発送済みの列は出さない。
 */
class ReservationExport extends AdminOrderExport
{
    /**
     * @param  LazyCollection<int, Reservation>  $reservations  予約者を Eager Load した、新しい順の予約
     */
    public function __construct(private readonly LazyCollection $reservations) {}

    /**
     * @return Generator<int, list<int|string>>
     */
    #[Override]
    public function generator(): Generator
    {
        foreach ($this->reservations as $reservation) {
            yield $this->row($reservation, $reservation->item_name, $reservation->item_weight, $reservation->quantity);
        }
    }

    #[Override]
    protected static function listType(): AdminListType
    {
        return AdminListType::Reservation;
    }
}
