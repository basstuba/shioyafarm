<?php

namespace App\Repositories\Interfaces;

use App\Models\Inquiry;
use App\Services\AdminInquiryCriteria;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * お問い合わせリポジトリインターフェース
 */
interface InquiryRepositoryInterface
{
    /**
     * お問い合わせを保存
     *
     * @param  array{last_name: string, first_name: string, email: string, detail: string, addressed: bool}  $data
     */
    public function create(array $data): Inquiry;

    /**
     * 管理画面のお問い合わせ一覧を新しい順（同時刻は ID の降順）にページ分けして取得
     *
     * @return LengthAwarePaginator<int, Inquiry>
     */
    public function paginateForAdmin(AdminInquiryCriteria $criteria, int $perPage, ?int $page = null): LengthAwarePaginator;

    /**
     * 管理画面の詳細に出すお問い合わせを1件取得（存在しないお問い合わせは null）
     */
    public function findForAdmin(int $id): ?Inquiry;

    /**
     * お問い合わせの対応済みかどうかを更新する（存在しないお問い合わせは更新せず false）
     */
    public function updateAddressed(int $id, bool $addressed): bool;
}
