<?php

namespace App\Livewire;

use App\Enums\CartAddResult;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * 商品詳細の「カートに入れる」ボタンと、結果を表示するモーダル
 */
class AddToCartButton extends Component
{
    /** 対象の商品ID（ブラウザから書き換えられないようにする） */
    #[Locked]
    public int $itemId;

    /** モーダルを開いているか */
    public bool $open = false;

    /** モーダルに表示するメッセージ */
    public string $message = '';

    /** モーダルのメッセージが、カートに追加できたときのものか */
    public bool $added = false;

    /** カートに入れられない商品だったか（「カートを見る」を出さない） */
    public bool $rejected = false;

    /**
     * カートに商品を1個追加し、結果をモーダルで表示する
     */
    public function addToCart(CartService $cartService): void
    {
        // 未ログインはカートに入れられない
        abort_unless(Auth::check(), 403);

        $limit = CartService::MAX_QUANTITY;
        $result = $cartService->addItem($this->itemId);

        $this->added = $result === CartAddResult::Added;
        $this->rejected = $result === CartAddResult::Rejected;
        $this->message = match ($result) {
            CartAddResult::Added => 'カートに追加しました',
            CartAddResult::LimitReached => "この商品はカートに{$limit}個入っています。1つの商品は{$limit}個までです。",
            CartAddResult::Rejected => 'この商品はカートに入れられません。',
        };
        $this->open = true;
    }

    public function render(): View
    {
        return view('livewire.add-to-cart-button');
    }
}
