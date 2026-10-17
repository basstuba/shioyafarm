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
     * 新しい順にお知らせを指定件数取得
     */
    #[Override]
    public function getNoticeList(int $limit): Collection
    {
        return Notice::latest('updated_at')->limit($limit)->get();
    }
}
