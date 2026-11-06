<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * 管理画面の一覧の、名前（注文者名・予約者名・会員の氏名）の検索欄の共通の決まり
 *
 * 注文一覧・予約一覧・会員一覧の検索条件（AdminListCriteria・AdminUserCriteria）と入力の検証（AdminListSearchRequest・UserSearchRequest）が使う。
 */
final class SearchName
{
    /**
     * 検索欄のバリデーションルール
     *
     * 文字列で、正しい UTF-8 であること（壊れた文字を含むと検索の処理が失敗し、絞り込みが外れて全件になるため。\A.*\z の /u は正しい UTF-8 でないと一致しない）。bail は、同じ文言のエラーを重ねて出さないため
     */
    public const array RULES = ['nullable', 'bail', 'string', 'regex:/\A.*\z/su', 'max:100'];

    /**
     * 検索欄の名前から、半角・全角のスペースを除く（姓名をつなげた文字列に合わせる）
     *
     * 正しい UTF-8 でないときは InvalidArgumentException になる（黙って絞り込みを外すと全件が表示されるため。呼び出す前に入力の検証で弾くこと）。
     */
    public static function normalize(string $name): string
    {
        $normalized = preg_replace('/[\s\x{3000}]+/u', '', $name);
        if ($normalized === null) {
            throw new InvalidArgumentException('名前が正しい UTF-8 ではありません。');
        }

        return $normalized;
    }
}
