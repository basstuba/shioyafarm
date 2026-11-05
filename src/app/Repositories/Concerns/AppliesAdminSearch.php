<?php

namespace App\Repositories\Concerns;

use App\Services\AdminListCriteria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * 管理画面の一覧（注文・予約）の検索条件を、クエリに適用する
 *
 * 対象のモデルは、shipped・created_at のカラムと、会員（user。退会済みを含む）のリレーションを持つこと。
 */
trait AppliesAdminSearch
{
    /**
     * 検索条件を適用したクエリを返す
     *
     * 名前は users の姓と名をつなげた文字列（スペースを除く）への部分一致で、LIKE のワイルドカード（%・_）と、その退避文字（!）は文字として扱う。
     * 退会済みの会員のものも対象にする（user が withTrashed のため）。
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applyAdminSearch(Builder $query, AdminListCriteria $criteria): Builder
    {
        if ($criteria->shipped !== null) {
            $query->where('shipped', $criteria->shipped);
        }
        if ($criteria->from !== null && $criteria->to !== null) {
            $query->where('created_at', '>=', $criteria->from)->where('created_at', '<', $criteria->to);
        }
        if ($criteria->name !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $criteria->name).'%';
            $joined = DB::connection()->getDriverName() === 'sqlite' ? '(last_name || first_name)' : 'CONCAT(last_name, first_name)';
            // DB側の姓名も、入力と同じく半角・全角スペースを除いてから比較する（名前の中にスペースがある会員も見つかる）
            $fullName = "REPLACE(REPLACE({$joined}, ' ', ''), '　', '')";
            $query->whereHas('user', fn (Builder $user) => $user->whereRaw("{$fullName} LIKE ? ESCAPE '!'", [$pattern]));
        }

        return $query;
    }
}
