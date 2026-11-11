<?php

namespace App\Services;

use App\Support\SearchDate;
use Carbon\CarbonImmutable;

/**
 * 管理画面のお知らせ一覧の検索条件（リポジトリにそのまま渡す値）
 *
 * 日付は from 以上 to 未満（どちらも null のとき絞り込まない）。
 */
final readonly class AdminNoticeCriteria
{
    public function __construct(
        public ?CarbonImmutable $from,
        public ?CarbonImmutable $to,
    ) {}

    /**
     * 検索欄の値（作成日は Y-m-d・空）から作る
     *
     * 日付は日本時間（アプリのタイムゾーン）のその日の0時から翌日の0時の手前まで。
     * 日付が Y-m-d の実在する日付でないときは InvalidArgumentException になる（黙って絞り込みを外すと全件が表示されるため。呼び出す前に入力の検証で弾くこと）。
     *
     * @param  array{date?: string}  $conditions
     */
    public static function fromConditions(array $conditions): self
    {
        $from = SearchDate::parse($conditions['date'] ?? '');

        return new self(from: $from, to: $from?->addDay());
    }
}
