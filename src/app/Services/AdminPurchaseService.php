<?php

namespace App\Services;

use App\Exports\PurchaseExport;
use App\Models\Purchase_history;
use App\Repositories\Interfaces\PurchaseHistoryRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 管理画面の注文一覧サービス（検索・発送済みの更新・詳細・エクスポート）
 *
 * 検索条件は、検索欄の値の形（発送状況は '0'・'1'・空、注文日は Y-m-d・空、注文者名）で受け取る。
 */
class AdminPurchaseService
{
    /** 一覧の1ページの件数 */
    public const int PER_PAGE = 10;

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
        $criteria = $this->criteria($conditions);
        $paginator = $this->purchaseHistoryRepository->paginateForAdmin($criteria, self::PER_PAGE);

        // 存在しないページ（?page=99 など）は、空の表ではなく最終ページを表示する
        if ($paginator->total() > 0 && $paginator->currentPage() > $paginator->lastPage()) {
            $paginator = $this->purchaseHistoryRepository->paginateForAdmin($criteria, self::PER_PAGE, $paginator->lastPage());
        }

        return $paginator;
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
        return new PurchaseExport($this->purchaseHistoryRepository->lazyForExport($this->criteria($conditions)));
    }

    /**
     * 検索欄の値を、リポジトリに渡す条件にする
     *
     * 注文日は日本時間（アプリのタイムゾーン）のその日の0時から翌日の0時の手前まで。
     * 注文者名は、半角・全角のスペースを除く（「山田 太郎」でも「山田太郎」でも見つかるように、姓名をつなげた文字列に合わせる）。
     *
     * @param  array{shipped?: string, date?: string, name?: string}  $conditions
     * @return array{shipped: bool|null, from: CarbonImmutable|null, to: CarbonImmutable|null, name: string}
     */
    private function criteria(array $conditions): array
    {
        $shipped = $conditions['shipped'] ?? '';
        $date = $conditions['date'] ?? '';
        $from = $date === '' ? null : CarbonImmutable::createFromFormat('!Y-m-d', $date, (string) config('app.timezone'));

        return [
            'shipped' => $shipped === '' ? null : $shipped === '1',
            'from' => $from ?: null,
            'to' => $from ? $from->addDay() : null,
            'name' => (string) preg_replace('/[\s\x{3000}]+/u', '', $conditions['name'] ?? ''),
        ];
    }
}
