<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequest;
use App\Services\Payment\PaymentGatewayException;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * 決済コントローラ
 */
class PaymentController extends Controller
{
    /**
     * 「注文を確定する」：注文を再計算して、決済ページ（Stripe）か購入完了ページへ移動
     *
     * クレジットカード・銀行振込は Stripe Checkout へ、代金引換は Stripe を通さず購入完了ページへ移動する。
     * 購入データはここでは保存せず、購入完了ページ（purchaseSuccess）で決済を確認してから保存する。
     */
    public function payment(PurchaseRequest $request, PurchaseService $purchaseService): RedirectResponse
    {
        Log::debug('PaymentController payment start');

        $user = $request->user();
        abort_if($user === null, 403);

        try {
            $url = $purchaseService->beginCheckout($user, $request->validated());
        } catch (PaymentGatewayException $e) {
            Log::error('決済ページを開けませんでした', ['message' => $e->getMessage()]);

            return back()
                ->withInput()
                ->withErrors(['payment' => '決済ページを開けませんでした。しばらく時間をおいてから、もう一度お試しください']);
        }

        Log::debug('PaymentController payment end');

        return redirect()->away($url);
    }
}
