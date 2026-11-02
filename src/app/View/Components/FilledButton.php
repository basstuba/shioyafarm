<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Override;

/**
 * 橙の塗りつぶしのボタン（購入する・予約する）
 *
 * - href を渡すとリンク（<a>）、渡さないとボタン（<button>。type は既定で submit）として出す
 * - size は lg（既定。200×40。SPは高さ44）／xl（200×60。商品詳細）／2xl（240×60で文字24px。購入ページの「注文を確定する」）／sm（カートの小計。PCは200×34、SPは全幅）／xs（130×34で文字16px。登録情報変更の「変更する」。SPは高さ44）。これ以外の値は lg と同じ
 * - variant は action（既定。購入など）／reserve（予約）。これ以外の値は action と同じ
 * - ホバー・フォーカスで色が変わる（枠線も背景とそろえる）。xl・2xl・sm は影も付く
 * - disabled のボタンは薄くして押せなくし、ホバーでも変わらない（リンクには付けない）
 */
class FilledButton extends Component
{
    public function __construct(
        public ?string $href = null,
        public string $type = 'submit',
        public string $size = 'lg',
        public string $variant = 'action',
    ) {}

    /**
     * 予約の色かどうか
     */
    private function isReserve(): bool
    {
        return $this->variant === 'reserve';
    }

    /**
     * 影を付ける大きさかどうか
     */
    private function hasShadow(): bool
    {
        return in_array($this->size, ['xl', '2xl', 'sm'], true);
    }

    /**
     * リンク・ボタン共通のクラス（大きさ・色・影）
     *
     * @return list<string>
     */
    public function classes(): array
    {
        return [
            'inline-flex items-center justify-center border-2 font-maru text-ink no-underline',
            'transition-[background-color,border-color,box-shadow] duration-200 focus-visible:outline-none',
            $this->isReserve()
                ? 'border-reserve bg-reserve hover:border-reserve-hover hover:bg-reserve-hover focus-visible:border-reserve-hover focus-visible:bg-reserve-hover'
                : 'border-action bg-action hover:border-primary-hover hover:bg-primary-hover focus-visible:border-primary-hover focus-visible:bg-primary-hover',
            $this->hasShadow() ? 'hover:shadow-button focus-visible:shadow-button' : '',
            match ($this->size) {
                'xl' => 'h-15 w-50 rounded-[40px] text-base',
                '2xl' => 'h-15 w-60 rounded-[40px] text-2xl',
                'sm' => 'h-11 w-full rounded-[40px] text-base md:h-[34px] md:w-50',
                'xs' => 'h-11 w-[130px] rounded-[30px] text-base md:h-[34px]',
                default => 'h-11 w-50 rounded-[30px] text-xl md:h-10',
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
            'cursor-pointer disabled:cursor-not-allowed disabled:opacity-60',
            $this->isReserve() ? 'disabled:hover:border-reserve disabled:hover:bg-reserve' : 'disabled:hover:border-action disabled:hover:bg-action',
            $this->hasShadow() ? 'disabled:hover:shadow-none' : '',
        ];
    }

    #[Override]
    public function render(): View
    {
        return view('components.filled-button');
    }
}
