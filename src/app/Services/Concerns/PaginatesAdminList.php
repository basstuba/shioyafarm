<?php

namespace App\Services\Concerns;

use Closure;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 管理画面の一覧（注文・予約）のページ分けで共通の処理
 */
trait PaginatesAdminList
{
    /** 一覧の1ページの件数 */
    public const int PER_PAGE = 10;

    /**
     * ページ分けして取得する（範囲外のページ番号は最終ページにする）
     *
     * 存在しないページ（?page=99 など）は、空の表ではなく最終ページを表示する。
     *
     * @template TModel
     *
     * @param  Closure(int|null): LengthAwarePaginator<int, TModel>  $paginate  ページ番号（null は現在のページ）を受け取って取得する
     * @return LengthAwarePaginator<int, TModel>
     */
    protected function paginateWithinRange(Closure $paginate): LengthAwarePaginator
    {
        $paginator = $paginate(null);

        if ($paginator->total() > 0 && $paginator->currentPage() > $paginator->lastPage()) {
            $paginator = $paginate($paginator->lastPage());
        }

        return $paginator;
    }
}
