<?php

namespace App\Repositories;

use App\Enums\PaymentMethod;
use App\Models\Reservation;
use App\Repositories\Interfaces\ReservationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
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

    /**
     * 会員の予約を新しい順にページ分けして取得（予約した商品を Eager Load する）
     *
     * @return LengthAwarePaginator<int, Reservation>
     */
    #[Override]
    public function paginateByUserId(int $userId, int $perPage, string $pageName): LengthAwarePaginator
    {
        return Reservation::with('item')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, pageName: $pageName);
    }

    /**
     * 会員の予約を1件、詳細の表示に必要な関連（商品・振込先）つきで取得（他の会員の予約・存在しない予約は null）
     */
    #[Override]
    public function findDetailByUserId(int $userId, int $id): ?Reservation
    {
        return Reservation::with(['item', 'reservationBankTransfer'])
            ->where('user_id', $userId)
            ->where('id', $id)
            ->first();
    }

    /**
     * サンクスメールの表示に必要な関連（会員・商品。銀行振込のときは振込先も）を、まだ読み込んでいなければ読み込む
     *
     * 会員は保存した側が持っているもの（User）をそのまま使えるよう、無いときだけ読み込む。
     */
    #[Override]
    public function loadForThanksMail(Reservation $reservation): Reservation
    {
        $reservation->loadMissing(['user', 'item']);
        // 振込先は銀行振込のときだけ読む
        if (PaymentMethod::from($reservation->payment_method)->usesBankTransfer()) {
            $reservation->loadMissing('reservationBankTransfer');
        }

        return $reservation;
    }
}
