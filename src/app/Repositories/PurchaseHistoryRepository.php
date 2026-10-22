<?php

namespace App\Repositories;

use App\Models\Purchase_history;
use App\Repositories\Interfaces\PurchaseHistoryRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * 購入履歴リポジトリ
 */
class PurchaseHistoryRepository implements PurchaseHistoryRepositoryInterface
{
    /**
     * 購入履歴・購入明細（銀行振込のときは振込先も）をトランザクションで作成
     *
     * @param  array{user_id: int, delivery_name: string, delivery_prefecture: string, delivery_city: string, delivery_tell: string, payment_method: string, shipping_fee: int, cod_fee: int, total_price: int}  $history
     * @param  list<array{item_id: int, quantity: int, price: int, subtotal: int}>  $items
     * @param  array{stripe_payment_intent_id: string, bank_name: string|null, branch_name: string|null, account_type: string|null, account_number: string|null, account_holder_name: string|null, amount: int}|null  $bankTransfer
     */
    #[Override]
    public function createWithItems(array $history, array $items, ?array $bankTransfer = null): Purchase_history
    {
        return DB::transaction(function () use ($history, $items, $bankTransfer): Purchase_history {
            $purchase = Purchase_history::create($history);
            $purchase->purchaseHistoryItems()->createMany($items);
            if ($bankTransfer !== null) {
                $purchase->purchaseBankTransfer()->create($bankTransfer);
            }

            return $purchase;
        });
    }
}
