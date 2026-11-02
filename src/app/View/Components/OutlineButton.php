<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Override;

/**
 * 白地に橙の枠線のボタン。ホバー・フォーカス時は黄色の背景に濃い茶色の文字になる（枠線は透明にして大きさを変えない）
 *
 * - href を渡すとリンク（<a>）、渡さないとボタン（<button>。type は既定で submit）として出す
 * - size は sm（既定。会員登録・詳しく見るなど）／lg（フォームの送信ボタンなど）／md（200×40で文字16px。カート追加のモーダル）／xl（200×60。商品詳細）／address（160×40で文字14px、角丸20px。購入ページの「＋新しい住所を追加」と住所追加モーダル。SPは高さ44で、幅は親いっぱい。PCは160）。これ以外の値は sm と同じ
 * - disabled のボタンは薄くして押せなくし、ホバーでも変わらない（リンクには付けない）
 */
class OutlineButton extends Component
{
    public function __construct(
        public ?string $href = null,
        public string $type = 'submit',
        public string $size = 'sm',
    ) {}

    /**
     * リンク・ボタン共通のクラス（大きさ・色）
     *
     * @return list<string>
     */
    public function classes(): array
    {
        return [
            'inline-flex items-center justify-center border-2 border-action bg-white font-maru text-action no-underline',
            'transition-[background-color,color,border-color,box-shadow] duration-200',
            'hover:border-transparent hover:bg-action-hover hover:text-action-hover-text hover:shadow-button',
            'focus-visible:border-transparent focus-visible:bg-action-hover focus-visible:text-action-hover-text focus-visible:shadow-button focus-visible:outline-none',
            match ($this->size) {
                'xl' => 'h-15 w-50 rounded-[40px] text-base font-medium',
                'address' => 'h-11 w-full rounded-[20px] text-sm md:h-10 md:w-40',
                'lg' => 'h-11 w-50 rounded-[30px] text-xl md:h-10',
                'md' => 'h-11 w-50 rounded-[30px] text-base md:h-10',
                default => 'h-11 w-40 rounded-[30px] text-base md:h-[34px] md:w-[130px]',
            },
        ];
    }

    /**
     * ボタン（<button>）にだけ足すクラス（押せない状態の見た目。ホバーでも変わらない）
     *
     * @return list<string>
     */
    public function disabledClasses(): array
    {
        return [
            'cursor-pointer disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:border-action disabled:hover:bg-white disabled:hover:text-action disabled:hover:shadow-none',
        ];
    }

    #[Override]
    public function render(): View
    {
        return view('components.outline-button');
    }
}
