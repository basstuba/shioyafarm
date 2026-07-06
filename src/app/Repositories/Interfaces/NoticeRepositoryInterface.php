<?php

namespace App\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;

/**
 * お知らせリポジトリインターフェース
 */
interface NoticeRepositoryInterface
{
    /**
     * お知らせ一覧を取得
     */
    public function getNoticeList(): Collection;
}
