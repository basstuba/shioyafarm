<?php

namespace App\Support;

/**
 * フォームに入力された文字の整え方（管理画面の資材の入力で使う）
 *
 * 文字列でない値（配列など）は、検証のエラーにするため、そのまま返す。
 */
final class FormText
{
    /**
     * 前後の空白を取り除く（半角・全角のスペース、タブ、改行を含む）
     */
    public static function trim(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        // 正しい UTF-8 でないときは preg_replace が null を返すので、そのまま返して検証に任せる
        return preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u', '', $value) ?? $value;
    }

    /**
     * 全角の数字を半角にして、前後の空白を取り除く（例：「４０」→「40」）。数字だけのときは、先頭の0を取り除く（「05」→「5」、「00」→「0」）
     *
     * 先頭に0のある数字は、整数の検証（integer）が受け付けないため、検証の前にそろえる。数字以外を含む値（-1・1.5・abc）はそのまま返す。
     */
    public static function number(mixed $value): mixed
    {
        $trimmed = self::trim($value);
        if (! is_string($trimmed)) {
            return $trimmed;
        }

        $halfWidth = mb_convert_kana($trimmed, 'n');

        return preg_match('/\A[0-9]+\z/', $halfWidth) === 1 ? ltrim($halfWidth, '0') ?: '0' : $halfWidth;
    }
}
