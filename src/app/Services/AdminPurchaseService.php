<?php

namespace App\Services;

use App\Exports\PurchaseExport;
use App\Models\Purchase_history;
use App\Repositories\Interfaces\PurchaseHistoryRepositoryInterface;
use App\Services\Concerns\PaginatesAdminList;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 管理画面の注文一覧サービス（検索・発送済みの更新・詳細・エクスポート）
 *
 * 検索条件は、検索欄の値の形（発送状況は '0'・'1'・空、注文日は Y-m-d・空、注文者名）で受け取る。
 */
class AdminPurchaseService
{
    use PaginatesAdminList;

    public function __construct(
        private readonly PurchaseHistoryRepositoryInterface $purchaseHistoryRepository,
    ) {}

    /**
     * 検索条件に合う注文を新しい順にページ分けして取得（範囲外のページ番号は最終ページにする）
     *
     * @param  array{shipped?: string, date?: string, name?: string}  $conditions
     * @return LengthAwarePaginator<int, Purchase_history>
     */
    public function search(array $conditions): LengthAwarePaginator
    {
        $criteria = AdminListCriteria::fromConditions($conditions);

        return $this->paginateWithinRange(fn (?int $page): LengthAwarePaginator => $this->purchaseHistoryRepository->paginateForAdmin($criteria, self::PER_PAGE, $page));
    }

    /**
     * 注文の発送済みかどうかを更新する（存在しない注文は、呼び出し側のバリデーションで弾く）
     */
    public function updateShipped(int $id, bool $shipped): void
    {
        $this->purchaseHistoryRepository->updateShipped($id, $shipped);
    }

    /**
     * 注文の詳細を取得（存在しない注文は null）
     */
    public function findDetail(int $id): ?OrderDetail
    {
        $purchase = $this->purchaseHistoryRepository->findDetailForAdmin($id);

        return $purchase === null ? null : OrderDetail::fromPurchase($purchase);
    }

    /**
     * 検索条件に合う全件（ページ番号は無視）を出力するエクスポートを作る
     *
     * @param  array{shipped?: string, date?: string, name?: string}  $conditions
     */
    public function purchaseExport(array $conditions): PurchaseExport
    {
        return new PurchaseExport($this->purchaseHistoryRepository->lazyForExport(AdminListCriteria::fromConditions($conditions)));
    }
}
