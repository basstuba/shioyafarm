<?php

namespace App\Support;

/**
 * 入力欄に戻す old() の値の整形
 *
 * 改変した送信（name[]=... のような配列など）は、文字列にできない値が old() に入る。
 * そのまま表示するとエラーになるので、スカラー以外は空文字にする。入力欄の部品（form-field・auth-input・auth-select）で共通に使う。
 */
final class OldInput
{
    /**
     * old() の値を文字列で返す（スカラー以外・値がないときは空文字）
     *
     * 真偽値は PHP の文字列化と同じ（true は "1"、false は ""）。
     * 数値は Blade の `{{ }}` で出したときと同じ文字列になる。
     */
    public static function scalar(string $name, mixed $default = null): string
    {
        $value = old($name, $default);

        return is_scalar($value) ? (string) $value : '';
    }
}
