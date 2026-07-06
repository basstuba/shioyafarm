<?php

namespace App\Repositories;

use App\Models\Notice;
use App\Repositories\Interfaces\NoticeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Override;

/**
 * お知らせリポジトリ
 */
class NoticeRepository implements NoticeRepositoryInterface
{
    /**
     * お知らせ一覧を取得
     */
    #[Override]
    public function getNoticeList(): Collection
    {
        return Notice::latest('updated_at')->get();
    }
}
