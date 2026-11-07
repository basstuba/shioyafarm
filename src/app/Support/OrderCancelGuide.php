<?php

namespace App\Support;

use App\Enums\AdminListType;

/**
 * お客様向けの、注文・予約の取消の案内文（マイページの詳細モーダル・購入と予約のサンクスメールで共通）
 *
 * 取り消せるのは農園（管理者）だけ。お客様には、お問い合わせフォームから連絡してもらう。
 * お客様の画面に管理番号が出ていないため、注文日・予約日とお名前を書いてもらう。
 * 「お問い合わせフォーム」は /inquiry へのリンクにするため、前後の文に分けて返す（リンクは呼び出し側で作る）。
 */
final class OrderCancelGuide
{
    /** リンクにする文字 */
    public const string LINK_LABEL = 'お問い合わせフォーム';

    /**
     * 案内文を、リンクの前の文とリンクの後の文に分けて返す
     *
     * 例（注文）：「ご注文の取り消しをご希望の場合は、」「お問い合わせフォーム」「から、注文日とご注文者のお名前を添えてご連絡ください。発送後の取り消しはお受けできません。」
     *
     * @return array{before: string, after: string}
     */
    public static function parts(AdminListType $type): array
    {
        [$word, $date] = match ($type) {
            AdminListType::Purchase => ['ご注文', '注文日'],
            AdminListType::Reservation => ['ご予約', '予約日'],
        };

        return [
            'before' => "{$word}の取り消しをご希望の場合は、",
            'after' => "から、{$date}と{$word}者のお名前を添えてご連絡ください。発送後の取り消しはお受けできません。",
        ];
    }
}
