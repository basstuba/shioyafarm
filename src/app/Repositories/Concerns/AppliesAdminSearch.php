<?php

namespace App\Repositories\Concerns;

use App\Enums\AdminListStatus;
use App\Services\AdminListCriteria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 管理画面の一覧（注文・予約）の検索条件を、クエリに適用する
 *
 * 対象のモデルは、shipped・canceled_at・created_at のカラムと、会員（user。退会済みを含む）のリレーションを持つこと。
 */
trait AppliesAdminSearch
{
    use MatchesFullName;

    /**
     * 検索条件を適用したクエリを返す
     *
     * 発送状況は、「未発送」＝未発送で未取消だけ、「発送済」＝発送済みで未取消だけ、「取消済」＝取消済みだけ。何も選ばないときは絞らない（取消済みも含む）。
     * 名前は users の姓と名をつなげた文字列（スペースを除く）への部分一致（MatchesFullName）。
     * 退会済みの会員のものも対象にする（user が withTrashed のため）。
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applyAdminSearch(Builder $query, AdminListCriteria $criteria): Builder
    {
        match ($criteria->status) {
            AdminListStatus::Unshipped => $query->where('shipped', false)->whereNull('canceled_at'),
            AdminListStatus::Shipped => $query->where('shipped', true)->whereNull('canceled_at'),
            AdminListStatus::Canceled => $query->whereNotNull('canceled_at'),
            null => $query,
        };
        if ($criteria->from !== null && $criteria->to !== null) {
            $query->where('created_at', '>=', $criteria->from)->where('created_at', '<', $criteria->to);
        }
        if ($criteria->name !== '') {
            $query->whereHas('user', fn (Builder $user) => $this->whereFullNameLike($user, $criteria->name));
        }

        return $query;
    }

    /**
     * エクスポートの検索条件を適用したクエリを返す
     *
     * 一覧と同じ検索条件に加えて、発送状況が「取消済」のとき以外は、取消済みを含めない（発送作業での誤発送を防ぐため）。
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applyExportSearch(Builder $query, AdminListCriteria $criteria): Builder
    {
        $this->applyAdminSearch($query, $criteria);

        if ($criteria->status !== AdminListStatus::Canceled) {
            $query->whereNull('canceled_at');
        }

        return $query;
    }
}
