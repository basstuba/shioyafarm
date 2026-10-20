<?php

namespace App\Repositories\Interfaces;

use App\Models\Notice;
use Illuminate\Database\Eloquent\Collection;

/**
 * お知らせリポジトリインターフェース
 */
interface NoticeRepositoryInterface
{
    /**
     * 新しい順にお知らせを指定件数取得
     *
     * @return Collection<int, Notice>
     */
    public function getNoticeList(int $limit): Collection;
}
