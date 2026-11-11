<?php

namespace App\Services;

use App\Models\Notice;
use App\Repositories\Interfaces\NoticeRepositoryInterface;
use App\Services\Concerns\PaginatesAdminList;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 管理画面のお知らせサービス（一覧・検索・詳細・作成・修正・削除）
 *
 * 検索条件は、検索欄の値の形（作成日は Y-m-d・空）で受け取る。
 */
class AdminNoticeService
{
    use PaginatesAdminList;

    public function __construct(
        private readonly NoticeRepositoryInterface $noticeRepository,
    ) {}

    /**
     * 検索条件に合うお知らせを作成日の新しい順にページ分けして取得（範囲外のページ番号は最終ページにする）
     *
     * @param  array{date?: string}  $conditions
     * @return LengthAwarePaginator<int, Notice>
     */
    public function search(array $conditions): LengthAwarePaginator
    {
        $criteria = AdminNoticeCriteria::fromConditions($conditions);

        return $this->paginateWithinRange(fn (?int $page): LengthAwarePaginator => $this->noticeRepository->paginateForAdmin($criteria, self::PER_PAGE, $page));
    }

    /**
     * お知らせの詳細を取得（存在しないお知らせは null）
     */
    public function findDetail(int $id): ?NoticeDetail
    {
        $notice = $this->noticeRepository->find($id);

        return $notice === null ? null : NoticeDetail::fromNotice($notice);
    }

    /**
     * 修正ページに出すお知らせを取得（存在しないお知らせは null）
     */
    public function findForEdit(int $id): ?Notice
    {
        return $this->noticeRepository->find($id);
    }

    /**
     * お知らせを作成する
     *
     * @param  array{title: string, text: string}  $input
     */
    public function create(array $input): Notice
    {
        return $this->noticeRepository->create($input);
    }

    /**
     * お知らせを修正する（存在しないお知らせは何も変えず false。作成日は変わらないため、一覧・トップの順番も変わらない）
     *
     * @param  array{title: string, text: string}  $input
     */
    public function update(int $id, array $input): bool
    {
        return $this->noticeRepository->update($id, $input);
    }

    /**
     * お知らせを物理削除する（存在しないお知らせは何も変えず false。お知らせを参照するテーブルはない）
     */
    public function delete(int $id): bool
    {
        return $this->noticeRepository->delete($id);
    }
}
