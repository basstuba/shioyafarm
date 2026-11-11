<?php

namespace App\Repositories;

use App\Models\Notice;
use App\Repositories\Interfaces\NoticeRepositoryInterface;
use App\Services\AdminNoticeCriteria;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * お知らせリポジトリ
 */
class NoticeRepository implements NoticeRepositoryInterface
{
    #[Override]
    public function getNoticeList(int $limit): Collection
    {
        return Notice::query()->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get();
    }

    /**
     * 日付は created_at が from 以上 to 未満のもの。
     */
    #[Override]
    public function paginateForAdmin(AdminNoticeCriteria $criteria, int $perPage, ?int $page = null): LengthAwarePaginator
    {
        $query = Notice::query();
        if ($criteria->from !== null && $criteria->to !== null) {
            $query->where('created_at', '>=', $criteria->from)->where('created_at', '<', $criteria->to);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage, page: $page);
    }

    #[Override]
    public function find(int $id): ?Notice
    {
        return Notice::find($id);
    }

    #[Override]
    public function create(array $data): Notice
    {
        return Notice::create($data);
    }

    /**
     * 行をロックして取得してから更新する（取得と更新の間に行が消えても、更新できたと答えない）
     */
    #[Override]
    public function update(int $id, array $data): bool
    {
        return DB::transaction(function () use ($id, $data): bool {
            $notice = Notice::query()->whereKey($id)->lockForUpdate()->first();
            if ($notice === null) {
                return false;
            }

            $notice->update($data);

            return true;
        });
    }

    #[Override]
    public function delete(int $id): bool
    {
        return Notice::query()->whereKey($id)->delete() > 0;
    }
}
