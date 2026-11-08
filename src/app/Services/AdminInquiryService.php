<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Repositories\Interfaces\InquiryRepositoryInterface;
use App\Services\Concerns\PaginatesAdminList;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 管理画面のお問い合わせ一覧サービス（検索・対応済みの更新・詳細）
 *
 * 検索条件は、検索欄の値の形（対応状況は '0'・'1'・空、問い合わせ日は Y-m-d・空）で受け取る。
 */
class AdminInquiryService
{
    use PaginatesAdminList;

    public function __construct(
        private readonly InquiryRepositoryInterface $inquiryRepository,
    ) {}

    /**
     * 検索条件に合うお問い合わせを新しい順にページ分けして取得（範囲外のページ番号は最終ページにする）
     *
     * @param  array{addressed?: string, date?: string}  $conditions
     * @return LengthAwarePaginator<int, Inquiry>
     */
    public function search(array $conditions): LengthAwarePaginator
    {
        $criteria = AdminInquiryCriteria::fromConditions($conditions);

        return $this->paginateWithinRange(fn (?int $page): LengthAwarePaginator => $this->inquiryRepository->paginateForAdmin($criteria, self::PER_PAGE, $page));
    }

    /**
     * お問い合わせの対応済みかどうかを更新する（存在しないお問い合わせは更新せず false。お客様にメールは送らない）
     */
    public function updateAddressed(int $id, bool $addressed): bool
    {
        return $this->inquiryRepository->updateAddressed($id, $addressed);
    }

    /**
     * お問い合わせの詳細を取得（存在しないお問い合わせは null）
     */
    public function findDetail(int $id): ?InquiryDetail
    {
        $inquiry = $this->inquiryRepository->findForAdmin($id);

        return $inquiry === null ? null : InquiryDetail::fromInquiry($inquiry);
    }
}
