<?php

namespace App\Repositories\Interfaces;

use App\Models\Reservation;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

/**
 * 予約リポジトリインターフェース
 */
interface ReservationRepositoryInterface
{
    /**
     * 商品のすでに予約された数量の合計を取得
     */
    public function getReservedQuantity(int $itemId): int;

    /**
     * 予約（銀行振込のときは振込先も）をトランザクションで作成
     *
     * @param  array{user_id: int, item_id: int, delivery_name: string, delivery_prefecture: string, delivery_city: string, delivery_tell: string, quantity: int, price: int, subtotal: int, payment_method: string, shipping_fee: int, total_price: int}  $reservation
     * @param  array{stripe_payment_intent_id: string, bank_name: string|null, branch_name: string|null, account_type: string|null, account_number: string|null, account_holder_name: string|null, amount: int}|null  $bankTransfer  銀行振込の振込先（銀行振込以外は null。PaymentIntent を作った時点で支払い済みだった予約は、振込先の各項目が null）
     *
     * @throws Throwable 保存に失敗したとき（どちらも保存されない）
     */
    public function createWithBankTransfer(array $reservation, ?array $bankTransfer = null): Reservation;

    /**
     * 会員の予約を新しい順にページ分けして取得（予約した商品を Eager Load する）
     *
     * @return LengthAwarePaginator<int, Reservation>
     */
    public function paginateByUserId(int $userId, int $perPage, string $pageName): LengthAwarePaginator;

    /**
     * 会員の予約を1件、詳細の表示に必要な関連（商品・振込先）つきで取得（他の会員の予約・存在しない予約は null）
     */
    public function findDetailByUserId(int $userId, int $id): ?Reservation;
}
