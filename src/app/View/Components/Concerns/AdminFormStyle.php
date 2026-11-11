<?php

namespace App\View\Components\Concerns;

/**
 * 管理画面の登録・修正フォーム（掲載商品・お知らせ）で共通のクラスの組み立て
 *
 * 使うコンポーネントは、修正かどうか（isEdit）を返す。
 */
trait AdminFormStyle
{
    /**
     * 修正かどうか
     */
    abstract public function isEdit(): bool;

    /**
     * 項目名のラベルのクラス
     */
    public function labelClass(): string
    {
        return 'text-base font-bold text-ink';
    }

    /**
     * 文字の入力欄（input・textarea）に共通のクラス（大きさと枠の色は含まない）
     */
    public function inputClass(): string
    {
        return 'rounded-[10px] border bg-white px-4 text-base text-ink placeholder:text-line-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink disabled:bg-admin-bar';
    }

    /**
     * 枠線の色のクラス（検証エラーのある項目は赤、ないときは灰色）
     */
    public function borderClass(bool $hasError): string
    {
        return $hasError ? 'border-danger' : 'border-line-strong';
    }

    /**
     * エラー文のクラス
     */
    public function errorClass(): string
    {
        return 'text-base text-danger';
    }

    /**
     * 送信ボタンの色のクラス（登録・作成は橙の地・ink の文字、修正は青の地・白文字。ホバー・フォーカスで濃くなる）
     */
    public function submitClass(): string
    {
        return $this->isEdit()
            ? 'bg-mail-link text-white hover:bg-mail-link-hover focus-visible:bg-mail-link-hover'
            : 'bg-primary text-ink hover:bg-primary-dark focus-visible:bg-primary-dark';
    }
}
