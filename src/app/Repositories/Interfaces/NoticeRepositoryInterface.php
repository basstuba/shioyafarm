<?php

namespace App\Repositories\Interfaces;

use App\Models\Notice;
use App\Services\AdminNoticeCriteria;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * お知らせリポジトリインターフェース
 */
interface NoticeRepositoryInterface
{
    /**
     * 作成日の新しい順（同じ日時は新しく作った順）にお知らせを指定件数取得（公開サイトのトップ用。修正しても順番は変わらない）
     *
     * @return Collection<int, Notice>
     */
    public function getNoticeList(int $limit): Collection;

    /**
     * 管理画面の一覧用に、検索条件に合うお知らせを作成日の新しい順（同じ日時は新しく作った順）でページ分けして取得
     *
     * @return LengthAwarePaginator<int, Notice>
     */
    public function paginateForAdmin(AdminNoticeCriteria $criteria, int $perPage, ?int $page = null): LengthAwarePaginator;

    /**
     * IDでお知らせを1件取得（存在しないお知らせは null）
     */
    public function find(int $id): ?Notice;

    /**
     * お知らせを保存
     *
     * @param  array{title: string, text: string}  $data
     */
    public function create(array $data): Notice;

    /**
     * お知らせを更新（存在しないお知らせは更新せず false）
     *
     * @param  array{title: string, text: string}  $data
     */
    public function update(int $id, array $data): bool;

    /**
     * お知らせを物理削除（存在しないお知らせは何も変えず false）
     */
    public function delete(int $id): bool;
}
