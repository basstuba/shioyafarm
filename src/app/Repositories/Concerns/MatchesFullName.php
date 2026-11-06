<?php

namespace App\Repositories\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * 会員の姓名（last_name・first_name）への部分一致の条件を、クエリに足す
 *
 * 注文一覧・予約一覧（会員を whereHas で探す）と、会員一覧で共通に使う。
 */
trait MatchesFullName
{
    /**
     * 姓と名をつなげた文字列（スペースを除く）への部分一致の条件を足す
     *
     * 入力の名前は、スペースを除いたもの（SearchName::normalize）を渡す。LIKE のワイルドカード（%・_）と、その退避文字（!）は文字として扱う。
     * DB側の姓名も、入力と同じく半角・全角スペースを除いてから比較する（名前の中にスペースがある会員も見つかる）。
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  last_name・first_name のカラムを持つテーブルのクエリ
     * @return Builder<TModel>
     */
    private function whereFullNameLike(Builder $query, string $name): Builder
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $name).'%';
        $joined = DB::connection()->getDriverName() === 'sqlite' ? '(last_name || first_name)' : 'CONCAT(last_name, first_name)';
        $fullName = "REPLACE(REPLACE({$joined}, ' ', ''), '　', '')";

        return $query->whereRaw("{$fullName} LIKE ? ESCAPE '!'", [$pattern]);
    }
}
