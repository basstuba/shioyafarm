<?php

namespace App\Support;

/**
 * 管理画面の一覧（注文・予約・会員・お問い合わせ）の、検索欄に戻す値の整形
 *
 * 注文一覧・予約一覧（admin/partials/order-list）、お問い合わせ一覧、会員一覧のビューが使う。
 * 検索欄には、直前の入力（old）を優先し、なければコントローラーが渡す検索条件（$conditions）の値を戻す。
 */
final class SearchCondition
{
    /**
     * 検索欄に戻す値を文字列で返す
     *
     * 配列など文字列でない入力（改変した送信）は戻さず空にする（TypeError にしない）。整形は OldInput と同じ。
     *
     * @param  array<string, mixed>  $conditions  検索欄に戻す検索条件（キーは検索欄の name）
     */
    public static function restore(array $conditions, string $key): string
    {
        return OldInput::scalar($key, $conditions[$key]);
    }

    /**
     * 日付の検索欄に戻す値を返す
     *
     * 日付の入力にエラーがあるときは、空にする。不正な日付は type=date の欄に戻さない（ブラウザのコンソールに警告が出る）。
     *
     * @param  array<string, mixed>  $conditions  検索欄に戻す検索条件（キーは検索欄の name）
     * @param  bool  $hasError  日付の入力にエラーがあるか（$errors->has('date')）
     * @param  string  $key  日付の検索欄の name
     */
    public static function restoreDate(array $conditions, bool $hasError, string $key = 'date'): string
    {
        return $hasError ? '' : self::restore($conditions, $key);
    }
}
