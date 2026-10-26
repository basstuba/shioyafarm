<?php

namespace App\Livewire\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

/**
 * 範囲外のページ番号（?purchasePage=99 など）を最終ページに丸めるページ送り
 *
 * 丸めないと、存在しないページの「0件」を表示して「履歴はありません」と出てしまう。
 */
trait WithClampedPagination
{
    use WithPagination;

    /**
     * 一覧を取得する。現在のページが最終ページより後ろのときは、最終ページにして取得し直す
     *
     * @template TModel of Model
     *
     * @param  Closure(): LengthAwarePaginator<int, TModel>  $load  現在のページ番号で一覧を取得する処理
     * @return LengthAwarePaginator<int, TModel>
     */
    private function loadClamped(Closure $load, string $pageName): LengthAwarePaginator
    {
        $paginator = $load();

        if ($paginator->currentPage() > $paginator->lastPage()) {
            $this->setPage($paginator->lastPage(), $pageName);
            $paginator = $load();
        }

        return $paginator;
    }
}
