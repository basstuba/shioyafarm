<?php

namespace App\Repositories;

use App\Enums\PaymentMethod;
use App\Models\Purchase_history;
use App\Repositories\Interfaces\PurchaseHistoryRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
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

    /**
     * 会員の購入履歴を新しい順にページ分けして取得（購入商品を Eager Load する）
     *
     * @return LengthAwarePaginator<int, Purchase_history>
     */
    #[Override]
    public function paginateByUserId(int $userId, int $perPage, string $pageName): LengthAwarePaginator
    {
        return Purchase_history::with('purchaseHistoryItems.item')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, pageName: $pageName);
    }

    /**
     * 会員の購入履歴を1件、詳細の表示に必要な関連（商品・振込先）つきで取得（他の会員の購入履歴・存在しない購入履歴は null）
     */
    #[Override]
    public function findDetailByUserId(int $userId, int $id): ?Purchase_history
    {
        return Purchase_history::with(['purchaseHistoryItems.item', 'purchaseBankTransfer'])
            ->where('user_id', $userId)
            ->where('id', $id)
            ->first();
    }

    /**
     * サンクスメールの表示に必要な関連（会員・購入商品。銀行振込のときは振込先も）を、まだ読み込んでいなければ読み込む
     *
     * 会員は保存した側が持っているもの（User）をそのまま使えるよう、無いときだけ読み込む。
     */
    #[Override]
    public function loadForThanksMail(Purchase_history $purchase): Purchase_history
    {
        $purchase->loadMissing(['user', 'purchaseHistoryItems.item']);
        // 振込先は銀行振込のときだけ読む
        if (PaymentMethod::from($purchase->payment_method)->usesBankTransfer()) {
            $purchase->loadMissing('purchaseBankTransfer');
        }

        return $purchase;
    }
}
