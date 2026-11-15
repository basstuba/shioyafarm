<?php

namespace App\Support;

/**
 * 管理画面の一覧（掲載商品・お知らせ・資材・注文・予約・会員・お問い合わせ）の表で共通のクラス
 *
 * SPは1件ずつのカード、PC（lg以上）は表になる一覧のセル・「修正」「削除」のクラス。
 * Tailwind が検出できるよう、クラスは完全な文字列で書く（連結で組み立てない）。
 */
final class AdminListStyle
{
    /** キーボードで操作したときのフォーカス枠 */
    public const string FOCUS = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink';

    /** 表のセル（SPは行のカードの中の項目、PCは表のセル） */
    public const string CELL = 'lg:table-cell lg:border-b lg:border-line lg:px-3 lg:align-middle';

    /** 「修正」「削除」。SPは「詳細」と同じ枠線つきのボタン（高さ44px）、PC（lg以上）は下線つきの文字（フォーカス枠つき） */
    public const string ACTION = 'inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-[10px] border border-line-strong bg-white px-2 text-base hover:no-underline focus-visible:no-underline lg:w-auto lg:min-w-16 lg:border-0 lg:bg-transparent lg:px-0 lg:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink';
}
