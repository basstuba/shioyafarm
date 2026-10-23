<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequest;
use App\Http\Responses\CheckoutResponder;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * 決済コントローラ
 */
class PaymentController extends Controller
{
    /**
     * 「注文を確定する」：注文を再計算して、決済ページ（Stripe）・振込先のご案内・サンクスページのいずれかへ移動
     *
     * - クレジットカード：Stripe Checkout へ移動する。購入データは購入完了ページ（purchaseSuccess）で決済を確認してから保存する。
     * - 銀行振込：振込先のご案内ページへ移動する。購入データは「振込み」（confirmBankTransfer）で保存する。
     *   PaymentIntent を作った時点で顧客残高から支払い済みになっていたときは、ここで保存してサンクスページへ移動する。
     * - 代金引換：Stripe を通さず、ここで購入データを保存してサンクスページ（/purchase/thanks）へ移動する。
     *
     * 保存した場合のサンクスページは、フラッシュ（THANKS_FLASH_KEY）で保存した直後の1回だけ表示する。
     * 二重送信は、購入ページが発行した1回限りの注文トークン（order_token）で防ぐ。使用済み・不正なトークンでは注文を作らず、
     * 直前の送信で保存済みならサンクスページへ、そうでなければ購入ページへ戻して「画面が古くなっています」と案内する。
     * 別のリクエストが処理中のとき（連打・複数タブ）は、数秒待ってから、購入ページへ戻してエラーを表示する。
     */
    public function payment(PurchaseRequest $request, PurchaseService $purchaseService): RedirectResponse
    {
        Log::debug('PaymentController payment start');

        $user = $request->user();
        abort_if($user === null, 403);

        $response = CheckoutResponder::forPurchase($purchaseService)->payment($user, $request->validated());

        Log::debug('PaymentController payment end');

        return $response;
    }
}
