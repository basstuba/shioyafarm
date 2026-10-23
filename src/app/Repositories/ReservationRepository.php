<?php

namespace App\Repositories;

use App\Models\Reservation;
use App\Repositories\Interfaces\ReservationRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * 予約リポジトリ
 */
class ReservationRepository implements ReservationRepositoryInterface
{
    /**
     * 商品のすでに予約された数量の合計を取得
     */
    #[Override]
    public function getReservedQuantity(int $itemId): int
    {
        return (int) Reservation::where('item_id', $itemId)->sum('quantity');
    }

    /**
     * 予約（銀行振込のときは振込先も）をトランザクションで作成
     *
     * @param  array{user_id: int, item_id: int, delivery_name: string, delivery_prefecture: string, delivery_city: string, delivery_tell: string, quantity: int, price: int, subtotal: int, payment_method: string, shipping_fee: int, total_price: int}  $reservation
     * @param  array{stripe_payment_intent_id: string, bank_name: string|null, branch_name: string|null, account_type: string|null, account_number: string|null, account_holder_name: string|null, amount: int}|null  $bankTransfer
     */
    #[Override]
    public function createWithBankTransfer(array $reservation, ?array $bankTransfer = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $bankTransfer): Reservation {
            $created = Reservation::create($reservation);
            if ($bankTransfer !== null) {
                $created->reservationBankTransfer()->create($bankTransfer);
            }

            return $created;
        });
    }
}
