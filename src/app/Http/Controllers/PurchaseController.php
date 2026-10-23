<?php

namespace App\Http\Controllers;

use App\Http\Responses\CheckoutResponder;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 購入コントローラ
 */
class PurchaseController extends Controller
{
    /**
     * 購入ページを表示
     *
     * `?restore=1`（Stripe Checkout の cancel_url）で開かれたときは、復元の合図をフラッシュに置き換えてリダイレクトする。
     * 商品IDなしはカートの中身、商品IDありはその商品1個からの購入。
     * 数量・住所・支払方法の操作と金額の更新は Livewire の purchase-form コンポーネントが担当する
     * （注文トークンの発行もそこで行う）。
     * 作った時点で支払い済みだった銀行振込の注文が、保存できないまま保存待ちに残っているときは、先に保存する
     * （保存できたらサンクスページへ。まだ処理中ならフォームを出さず、再読み込みを案内する。押し直して注文が重複するのを防ぐ）。
     * カートが空ならカートページへ戻す。存在しない商品・予約限定の商品は404。
     */
    public function index(Request $request, PurchaseService $purchaseService, ?int $item = null): View|RedirectResponse|Response
    {
        Log::debug('PurchaseController index start');

        // 決済ページ（Stripe Checkout）の「戻る」で戻ったとき：入力を復元する合図をフラッシュに置き換え、URLからは外す
        // （再読み込みや開き直しでは復元しない）
        if ($request->query->has('restore')) {
            Log::debug('PurchaseController index end (redirect)');

            return CheckoutResponder::forPurchase($purchaseService)->restoreIndex($item);
        }

        $user = $request->user();
        abort_if($user === null, 403);

        $paidPendingResponse = CheckoutResponder::forPurchase($purchaseService)->paidPending($purchaseService->settlePaidBankTransfer($user));
        if ($paidPendingResponse !== null) {
            Log::debug('PurchaseController index end (paid pending order)');

            return $paidPendingResponse;
        }

        if ($item === null) {
            if ($purchaseService->isCartEmpty()) {
                Log::debug('PurchaseController index end (redirect)');

                return redirect()->route('shop.cart');
            }
        } else {
            $purchaseService->findPurchasableItem($item);
        }

        Log::debug('PurchaseController index end');

        return view('shop.purchase', ['itemId' => $item]);
    }

    /**
     * 銀行振込の「振込先のご案内」ページを表示
     *
     * 保存待ちの注文が銀行振込で、振込先があるときだけ表示する。
     * 銀行振込でない注文は購入ページへ、保存待ちの注文がなければトップページへ戻す
     * （購入完了後にブラウザの「戻る」で開き直した場合も、トップページへ戻る）。
     */
    public function bankTransfer(Request $request, PurchaseService $purchaseService): View|RedirectResponse
    {
        Log::debug('PurchaseController bankTransfer start');

        $user = $request->user();
        abort_if($user === null, 403);

        $order = $purchaseService->getBankTransferOrder($user);
        if ($order === null) {
            $pending = $purchaseService->getPendingOrder($user);

            Log::debug('PurchaseController bankTransfer end (redirect)');

            return $pending === null
                ? redirect()->route('top.index')
                : redirect()->route('shop.checkout.index', $pending->itemId());
        }

        Log::debug('PurchaseController bankTransfer end');

        return view('shop.bank_transfer', ['order' => $order, 'bankTransfer' => $order->bankTransfer]);
    }

    /**
     * 「振込先のご案内」の「戻る」：銀行振込の支払いをキャンセルして購入ページへ戻る
     *
     * 状態を変えるため POST。購入データは作らず、入力内容は購入ページに残る。
     * キャンセルに失敗したときは、PaymentIntent の状態を確認し直し、入金済み・入金処理中なら購入データを保存して
     * サンクスページへ、それ以外の失敗は案内ページに戻してエラーを表示する（保存待ちの注文は変えない）。
     */
    public function cancelBankTransfer(Request $request, PurchaseService $purchaseService): RedirectResponse
    {
        Log::debug('PurchaseController cancelBankTransfer start');

        $user = $request->user();
        abort_if($user === null, 403);

        $result = $purchaseService->cancelBankTransfer($user);

        Log::debug('PurchaseController cancelBankTransfer end');

        return CheckoutResponder::forPurchase($purchaseService)->cancelBankTransfer($result, $user);
    }

    /**
     * 「振込先のご案内」の「振込み」：PaymentIntent を確認して購入データを保存し、サンクスページへ移動する
     *
     * 状態を変えるため POST（CSRF あり）。サンクスページはフラッシュで1回だけ表示する。
     */
    public function confirmBankTransfer(Request $request, PurchaseService $purchaseService): RedirectResponse
    {
        Log::debug('PurchaseController confirmBankTransfer start');

        $user = $request->user();
        abort_if($user === null, 403);

        $result = $purchaseService->confirmBankTransfer($user);

        Log::debug('PurchaseController confirmBankTransfer end', ['result' => $result->name]);

        return CheckoutResponder::forPurchase($purchaseService)->bankTransferConfirm($result, $user);
    }

    /**
     * 銀行振込・代金引換の購入完了ページ（サンクスページ）を表示
     *
     * 銀行振込は「振込み」、代金引換は「注文を確定する」で保存した直後（フラッシュあり）だけ表示する。再読み込み・直接アクセスはトップページへ戻す。
     */
    public function thanks(): View|RedirectResponse
    {
        Log::debug('PurchaseController thanks start');

        if (! session()->has(PurchaseService::THANKS_FLASH_KEY)) {
            Log::debug('PurchaseController thanks end (redirect)');

            return redirect()->route('top.index');
        }

        Log::debug('PurchaseController thanks end');

        return view('shop.purchase_thanks');
    }

    /**
     * 購入完了ページを表示（クレジットカード専用：決済を確認して購入データを保存する）
     *
     * Stripe の success_url から来る。銀行振込は「振込み」（POST）、代金引換は「注文を確定する」（POST）でだけ保存するため、
     * ここでは保存しない（銀行振込は案内ページへ、代金引換の古い保存待ちの注文は購入ページへ戻す）。
     * 保存待ちの注文が無いとき（再読み込み・直接アクセス）は何も保存せずトップページへ戻す。
     * 決済が未完了のときは購入ページへ戻してエラーを表示する。Stripe に問い合わせられなかったとき（支払い済みかどうか不明）は、
     * 再決済をさせず、保存待ちの注文を残したまま、再読み込みを案内する。
     */
    public function purchaseSuccess(Request $request, PurchaseService $purchaseService): View|RedirectResponse|Response
    {
        Log::debug('PurchaseController purchaseSuccess start');

        $user = $request->user();
        abort_if($user === null, 403);

        $sessionId = $request->query('session_id');
        $result = $purchaseService->completeCheckout($user, is_string($sessionId) ? $sessionId : null);

        Log::debug('PurchaseController purchaseSuccess end', ['result' => $result->name]);

        return CheckoutResponder::forPurchase($purchaseService)->success($result, $user);
    }
}
