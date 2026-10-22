<?php

namespace App\Repositories\Interfaces;

use App\Models\Purchase_history;
use Throwable;

/**
 * 購入履歴リポジトリインターフェース
 */
interface PurchaseHistoryRepositoryInterface
{
    /**
     * 購入履歴・購入明細（銀行振込のときは振込先も）をトランザクションで作成
     *
     * @param  array{user_id: int, delivery_name: string, delivery_prefecture: string, delivery_city: string, delivery_tell: string, payment_method: string, shipping_fee: int, cod_fee: int, total_price: int}  $history
     * @param  list<array{item_id: int, quantity: int, price: int, subtotal: int}>  $items
     * @param  array{stripe_payment_intent_id: string, bank_name: string|null, branch_name: string|null, account_type: string|null, account_number: string|null, account_holder_name: string|null, amount: int}|null  $bankTransfer  銀行振込の振込先（銀行振込以外は null。PaymentIntent を作った時点で支払い済みだった注文は、振込先の各項目が null）
     *
     * @throws Throwable 保存に失敗したとき（どちらも保存されない）
     */
    public function createWithItems(array $history, array $items, ?array $bankTransfer = null): Purchase_history;
}
