<?php

namespace App\View\Components;

use App\Support\OldInput;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Override;

/**
 * 認証ページ・管理画面の入力欄（ラベル・input・エラーメッセージ）
 *
 * 使い方・スロット（labelExtra・hint）・パスワード欄の表示切り替えは、ビュー（components/auth-input）の冒頭の説明を参照。
 * variant は default（公開側。橙系）／neutral（管理画面。グレー）。これ以外の値は default と同じ。
 */
class AuthInput extends Component
{
    /** パスワードの欄かどうか */
    public bool $isPassword;

    /**
     * $value は入力欄の初期値（old() があればそちらを優先。パスワードの欄では出さない）。
     * $describedby は aria-describedby につなぐ補足の要素の id（スペース区切り）。
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'text',
        public string $placeholder = '',
        public string $autocomplete = 'off',
        public mixed $value = null,
        public bool $srLabel = false,
        public string $labelClass = 'text-base font-medium',
        public ?string $describedby = null,
        public string $variant = 'default',
        public ?string $inputmode = null,
        public string|int|null $maxlength = null,
    ) {
        $this->isPassword = $type === 'password';
    }

    /**
     * 入力欄に出す値（old() を優先。配列など文字列にできない値は空文字）
     */
    public function oldValue(): string
    {
        return OldInput::scalar($this->name, $this->value);
    }

    /**
     * 枠線の色のクラス
     */
    public function borderClass(): string
    {
        return $this->variant === 'neutral' ? 'border-line-strong' : 'border-primary-dark';
    }

    /**
     * フォーカス枠の色のクラス
     */
    public function outlineClass(): string
    {
        return $this->variant === 'neutral' ? 'focus-visible:outline-ink' : 'focus-visible:outline-primary-dark';
    }

    /**
     * 「表示」「非表示」ボタンの文字色のクラス
     */
    public function toggleClass(): string
    {
        return $this->variant === 'neutral' ? 'text-muted' : 'text-primary-text';
    }

    /**
     * aria-describedby に入れる id（呼び出し側の補足の id と、エラー文の id をスペースでつなぐ。なければ空文字）
     */
    public function describedIds(bool $hasError): string
    {
        return trim(($this->describedby ?? '').' '.($hasError ? $this->name.'-error' : ''));
    }

    #[Override]
    public function render(): View
    {
        return view('components.auth-input');
    }
}
