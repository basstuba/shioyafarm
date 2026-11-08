<?php

namespace App\Repositories;

use App\Models\Inquiry;
use App\Repositories\Interfaces\InquiryRepositoryInterface;
use App\Services\AdminInquiryCriteria;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * お問い合わせリポジトリ
 */
class InquiryRepository implements InquiryRepositoryInterface
{
    /**
     * お問い合わせを保存
     *
     * @param  array{last_name: string, first_name: string, email: string, detail: string, addressed: bool}  $data
     */
    #[Override]
    public function create(array $data): Inquiry
    {
        return Inquiry::create($data);
    }

    /**
     * 日付は created_at が from 以上 to 未満のもの。対応状況は addressed の一致。
     */
    #[Override]
    public function paginateForAdmin(AdminInquiryCriteria $criteria, int $perPage, ?int $page = null): LengthAwarePaginator
    {
        $query = Inquiry::query();
        if ($criteria->addressed !== null) {
            $query->where('addressed', $criteria->addressed);
        }
        if ($criteria->from !== null && $criteria->to !== null) {
            $query->where('created_at', '>=', $criteria->from)->where('created_at', '<', $criteria->to);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage, page: $page);
    }

    #[Override]
    public function findForAdmin(int $id): ?Inquiry
    {
        return Inquiry::find($id);
    }

    /**
     * 行をロックして取得してから更新する（注文の発送済みの更新と同じ。取得と更新の間に行が消えても、更新できたと答えない）
     */
    #[Override]
    public function updateAddressed(int $id, bool $addressed): bool
    {
        return DB::transaction(function () use ($id, $addressed): bool {
            $inquiry = Inquiry::query()->whereKey($id)->lockForUpdate()->first();
            if ($inquiry === null) {
                return false;
            }

            $inquiry->forceFill(['addressed' => $addressed])->save();

            return true;
        });
    }
}
