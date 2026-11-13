<?php

namespace App\View\Components;

use App\Support\LivewireBoot;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;
use Override;

/**
 * 白地に橙の枠線のボタン。ホバー・フォーカス時は黄色の背景に濃い茶色の文字になる（枠線は透明にして大きさを変えない）
 *
 * - href を渡すとリンク（<a>）、渡さないとボタン（<button>。type は既定で submit）として出す
 * - size は sm（既定。会員登録・詳しく見るなど）／lg（フォームの送信ボタンなど）／md（200×40で文字16px。カート追加のモーダル）／xl（200×60。商品詳細）／address（160×40で文字14px、角丸20px。購入ページの「＋新しい住所を追加」と住所追加モーダル。SPは高さ44で、幅は親いっぱい。PCは160）。これ以外の値は sm と同じ
 * - livewire を付けると、Livewire が起動するまでは押せない（disabled）。wire:click などを付けるボタン用（<button> のときだけ。App\Support\LivewireBoot）。呼び出し側が渡した disabled は、起動後も押せない条件として保つ
 * - disabled のボタンは薄くして押せなくし、ホバーでも変わらない（リンクには付けない）
 */
class OutlineButton extends Component
{
    public function __construct(
        public ?string $href = null,
        public string $type = 'submit',
        public string $size = 'sm',
        public bool $livewire = false,
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
            'cursor-pointer '.LivewireBoot::DISABLED_CLASSES.' disabled:hover:border-action disabled:hover:bg-white disabled:hover:text-action disabled:hover:shadow-none',
        ];
    }

    /**
     * <button> に付ける属性。livewire のときは Livewire の起動前だけ押せない属性を足す
     *
     * 呼び出し側が渡した disabled（`disabled`・`:disabled="条件"`）は、LivewireBoot に「もともと押せない条件」として渡す。
     * 条件が true なら起動しても外れず、false でも起動前は disabled になる（渡された値のまま使うと起動前に押せてしまう）。
     */
    public function buttonAttributes(ComponentAttributeBag $attributes): ComponentAttributeBag
    {
        if (! $this->livewire) {
            return $attributes;
        }

        return $attributes
            ->except('disabled')
            ->merge(LivewireBoot::attributes($this->isDisabled($attributes))->getAttributes());
    }

    /**
     * 呼び出し側が渡した disabled が「押せない」を意味するか
     *
     * 押せない：true・属性だけ（`disabled`。Blade では true になる）・"disabled"・"true"・"1"・"on"・"yes"。
     * 押せる：渡していない・false・null・"false"・"0"・""（文字の "false" や "0" を true にしない）。
     */
    private function isDisabled(ComponentAttributeBag $attributes): bool
    {
        $value = $attributes->get('disabled');

        return $value === 'disabled' || filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    #[Override]
    public function render(): View
    {
        return view('components.outline-button');
    }
}
