<?php

namespace App\Livewire;

use App\Http\Requests\CartRequest;
use App\Services\CartService;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * カートページの商品一覧（数量変更・削除・小計）
 */
class CartList extends Component
{
    /**
     * 商品ごとの、数量エラーになった回数
     *
     * 数量の入力欄の wire:key に含め、エラーのたびに入力欄を作り直して保存済みの数量に戻す。
     *
     * @var array<int, int>
     */
    #[Locked]
    public array $errorCounts = [];

    /**
     * 商品の数量を変更する
     *
     * 入力値は文字列か整数だけを受け付け（それ以外は未入力として扱う）、CartRequest と同じルール・メッセージで検証する。
     * エラーのときはセッションの数量を変えず、その商品の行に「数量は○個のままです」を付けたエラーを表示し、
     * 入力欄を保存済みの数量に戻す。
     */
    public function updateQuantity(CartService $cartService, int $itemId, mixed $quantity): void
    {
        $this->resetErrorBag("quantity.{$itemId}");

        $validator = Validator::make(
            ['quantity' => is_string($quantity) ? trim($quantity) : (is_int($quantity) ? $quantity : null)],
            ['quantity' => CartRequest::quantityRules()],
            CartRequest::quantityMessages(),
        );

        if ($validator->fails()) {
            $message = $validator->errors()->first('quantity');
            $current = $cartService->getQuantity($itemId);
            if ($current !== null) {
                $message .= "（数量は{$current}個のままです）";
            }

            $this->addError("quantity.{$itemId}", $message);
            // 入力欄を作り直し、打った値を保存済みの数量に戻す
            $this->errorCounts[$itemId] = ($this->errorCounts[$itemId] ?? 0) + 1;

            return;
        }

        $cartService->updateQuantity($itemId, (int) $validator->validated()['quantity']);
    }

    /**
     * 商品をカートから削除する
     */
    public function remove(CartService $cartService, int $itemId): void
    {
        $this->resetErrorBag("quantity.{$itemId}");
        unset($this->errorCounts[$itemId]);
        $cartService->removeItem($itemId);
    }

    public function render(CartService $cartService): View
    {
        $lines = $cartService->getLines();

        return view('livewire.cart-list', [
            'lines' => $lines,
            'subtotal' => $cartService->calculateSubtotal($lines),
        ]);
    }
}
