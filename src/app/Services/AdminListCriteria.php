<?php

namespace App\Services;

use App\Support\SearchName;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * 管理画面の一覧（注文・予約）の検索条件（リポジトリにそのまま渡す値）
 *
 * 発送状況は null のとき絞り込まない。日付は from 以上 to 未満（どちらも null のとき絞り込まない）。名前は空文字のとき絞り込まない。
 */
final readonly class AdminListCriteria
{
    public function __construct(
        public ?bool $shipped,
        public ?CarbonImmutable $from,
        public ?CarbonImmutable $to,
        public string $name,
    ) {}

    /**
     * 検索欄の値（発送状況は '0'・'1'・空、日付は Y-m-d・空、名前）から作る
     *
     * 日付は日本時間（アプリのタイムゾーン）のその日の0時から翌日の0時の手前まで。
     * 名前は、半角・全角のスペースを除く（「山田 太郎」でも「山田太郎」でも見つかるように、姓名をつなげた文字列に合わせる）。
     * 日付が Y-m-d の実在する日付でないとき、名前が正しい UTF-8 でないときは InvalidArgumentException になる
     * （黙って絞り込みを外すと全件が表示・出力されるため。呼び出す前に入力の検証で弾くこと）。
     *
     * @param  array{shipped?: string, date?: string, name?: string}  $conditions
     */
    public static function fromConditions(array $conditions): self
    {
        $shipped = $conditions['shipped'] ?? '';
        $from = self::parseDate($conditions['date'] ?? '');

        return new self(
            shipped: $shipped === '' ? null : $shipped === '1',
            from: $from,
            to: $from?->addDay(),
            name: SearchName::normalize($conditions['name'] ?? ''),
        );
    }

    /**
     * Y-m-d の日付を、日本時間（アプリのタイムゾーン）のその日の0時にする（空は null）
     */
    private static function parseDate(string $date): ?CarbonImmutable
    {
        if ($date === '') {
            return null;
        }

        // 存在しない日付（2月30日など）は PHP が翌月に繰り越すため、書式どおりに戻せるかで確かめる
        // Carbon の設定によっては、解釈できないときに例外ではなく false（型の定義では null）が返るため、真偽で判定する
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, (string) config('app.timezone'));
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('日付が Y-m-d の実在する日付ではありません。');
        }

        return $parsed;
    }
}
