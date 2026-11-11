<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Override;

/**
 * 管理画面の一覧（注文・予約・会員・お問い合わせ）の検索欄まわりのボタン
 *
 * - href を渡すとリンク（<a>）、渡さないと type="submit" の <button>（type は上書きできる。disabled が true なら押せない）として出す
 * - variant は search（既定。緑の地の「検索」）／reset（灰色の地の「リセット」）／export（濃い灰色の地の「エクスポート」。幅200px、押せないときは薄くする）。これ以外の値は search と同じ
 * - SPは高さ48px・全幅、PC（lg以上）は高さ40px・文字14px・幅は variant ごと（search・reset は100px、export は200px）
 */
class AdminSearchButton extends Component
{
    public function __construct(
        public string $variant = 'search',
        public ?string $href = null,
        public bool $disabled = false,
    ) {}

    /**
     * リンク・ボタン共通のクラス（大きさ・色・variant ごとの幅）
     *
     * @return list<string>
     */
    public function classes(): array
    {
        return [
            'inline-flex h-12 w-full cursor-pointer items-center justify-center rounded-[10px] text-base transition-[background-color,box-shadow] duration-200 hover:shadow-button focus-visible:shadow-button lg:h-10 lg:text-sm',
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink',
            match ($this->variant) {
                'reset' => 'bg-line-strong text-ink hover:bg-admin-gray hover:text-white focus-visible:bg-admin-gray focus-visible:text-white lg:w-[100px]',
                'export' => 'bg-admin-gray text-white hover:bg-admin-dark focus-visible:bg-admin-dark disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none lg:w-[200px]',
                default => 'bg-accent text-ink hover:bg-accent-hover hover:text-white focus-visible:bg-accent-hover focus-visible:text-white lg:w-[100px]',
            },
        ];
    }

    #[Override]
    public function render(): View
    {
        return view('components.admin-search-button');
    }
}
