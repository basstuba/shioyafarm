<?php

namespace App\Repositories;

use App\Enums\OrderCancelResult;
use App\Enums\PaymentMethod;
use App\Models\Purchase_history;
use App\Repositories\Concerns\AppliesAdminSearch;
use App\Repositories\Concerns\CancelsOrder;
use App\Repositories\Interfaces\PurchaseHistoryRepositoryInterface;
use App\Services\AdminListCriteria;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Override;

/**
 * 購入履歴リポジトリ
 */
class PurchaseHistoryRepository implements PurchaseHistoryRepositoryInterface
{
    use AppliesAdminSearch;
    use CancelsOrder;

    /** エクスポートで一度に読み込む注文の件数 */
    private const int EXPORT_CHUNK_SIZE = 500;

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

    /**
     * 管理画面の注文一覧を新しい順（ID の降順）にページ分けして取得（注文者を Eager Load する）
     *
     * @return LengthAwarePaginator<int, Purchase_history>
     */
    #[Override]
    public function paginateForAdmin(AdminListCriteria $criteria, int $perPage, ?int $page = null): LengthAwarePaginator
    {
        return $this->searchQuery($criteria)
            ->with('user')
            ->orderByDesc('id')
            ->paginate($perPage, page: $page);
    }

    /**
     * @return LazyCollection<int, Purchase_history>
     */
    #[Override]
    public function lazyForExport(AdminListCriteria $criteria): LazyCollection
    {
        return $this->exportQuery($criteria)
            ->with(['user', 'purchaseHistoryItems.item'])
            ->lazyByIdDesc(self::EXPORT_CHUNK_SIZE);
    }

    #[Override]
    public function countForExport(AdminListCriteria $criteria): int
    {
        return $this->exportQuery($criteria)->count();
    }

    #[Override]
    public function findDetailForAdmin(int $id): ?Purchase_history
    {
        return Purchase_history::with(['user', 'purchaseHistoryItems.item', 'purchaseBankTransfer'])->find($id);
    }

    /**
     * 取消済みの注文は更新しない
     *
     * 行をロックして読み、存在して未取消のときだけ更新する（取消 cancel と同じロックのため、判定と更新の間に取消が入らない）。
     */
    #[Override]
    public function updateShipped(int $id, bool $shipped): bool
    {
        return DB::transaction(function () use ($id, $shipped): bool {
            $order = Purchase_history::whereKey($id)->lockForUpdate()->first();
            if ($order === null || $order->canceled_at !== null) {
                return false;
            }

            $order->forceFill(['shipped' => $shipped])->save();

            return true;
        });
    }

    /**
     * 取消（CancelsOrder）。銀行振込の振込先を読み込んで渡す
     */
    #[Override]
    public function cancel(int $id, Closure $beforeCancel): OrderCancelResult
    {
        return $this->cancelLocked(Purchase_history::with('purchaseBankTransfer'), $id, $beforeCancel, 'purchaseBankTransfer');
    }

    /**
     * 管理画面の検索条件を適用したクエリ（条件の適用は AppliesAdminSearch）
     *
     * @return Builder<Purchase_history>
     */
    private function searchQuery(AdminListCriteria $criteria): Builder
    {
        return $this->applyAdminSearch(Purchase_history::query(), $criteria);
    }

    /**
     * エクスポートの検索条件を適用したクエリ（取消済みは、発送状況が「取消済」のとき以外は含めない。AppliesAdminSearch）
     *
     * @return Builder<Purchase_history>
     */
    private function exportQuery(AdminListCriteria $criteria): Builder
    {
        return $this->applyExportSearch(Purchase_history::query(), $criteria);
    }
}
