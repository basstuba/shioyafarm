<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * 管理画面の一覧の、日付（注文日・予約日・問い合わせ日）の検索欄の共通の決まり
 *
 * 注文一覧・予約一覧・お問い合わせ一覧の検索条件（AdminListCriteria・AdminInquiryCriteria）が使う。
 */
final class SearchDate
{
    /**
     * Y-m-d の日付を、日本時間（アプリのタイムゾーン）のその日の0時にする（空は null）
     *
     * 実在しない日付や Y-m-d でない値は InvalidArgumentException になる（黙って絞り込みを外すと全件が表示されるため。呼び出す前に入力の検証で弾くこと）。
     *
     * @throws InvalidArgumentException 日付が Y-m-d の実在する日付でないとき
     */
    public static function parse(string $date): ?CarbonImmutable
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
