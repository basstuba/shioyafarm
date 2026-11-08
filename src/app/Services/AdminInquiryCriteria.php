<?php

namespace App\Services;

use App\Support\SearchDate;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * 管理画面のお問い合わせ一覧の検索条件（リポジトリにそのまま渡す値）
 *
 * 対応済みかどうかは null のとき絞り込まない。日付は from 以上 to 未満（どちらも null のとき絞り込まない）。
 */
final readonly class AdminInquiryCriteria
{
    /** 検索欄の対応状況の値：非対応 */
    public const string ADDRESSED_NO = '0';

    /** 検索欄の対応状況の値：対応済 */
    public const string ADDRESSED_YES = '1';

    public function __construct(
        public ?bool $addressed,
        public ?CarbonImmutable $from,
        public ?CarbonImmutable $to,
    ) {}

    /**
     * 検索欄の値（対応状況は '0'・'1'・空、問い合わせ日は Y-m-d・空）から作る
     *
     * 日付は日本時間（アプリのタイムゾーン）のその日の0時から翌日の0時の手前まで。
     * 対応状況が決められた値でないとき、日付が Y-m-d の実在する日付でないときは InvalidArgumentException になる
     * （黙って絞り込みを外すと全件が表示されるため。呼び出す前に入力の検証で弾くこと）。
     *
     * @param  array{addressed?: string, date?: string}  $conditions
     */
    public static function fromConditions(array $conditions): self
    {
        $from = SearchDate::parse($conditions['date'] ?? '');

        return new self(
            addressed: match ($conditions['addressed'] ?? '') {
                '' => null,
                self::ADDRESSED_NO => false,
                self::ADDRESSED_YES => true,
                default => throw new InvalidArgumentException('対応状況が決められた値ではありません。'),
            },
            from: $from,
            to: $from?->addDay(),
        );
    }
}
