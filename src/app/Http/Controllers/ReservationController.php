<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReservationRequest;
use App\Http\Responses\CheckoutResponder;
use App\Services\ReservationLimitService;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 予約コントローラ
 *
 * 購入（PurchaseController / PaymentController）と同じ流れで、予約限定の商品1つを先払いで予約する。
 * 決済・銀行振込・二重送信の対策は ReservationService（CheckoutService）が担当する。
 */
class ReservationController extends Controller
{
    /**
     * 予約ページを表示
     *
     * `?restore=1`（Stripe Checkout の cancel_url）で開かれたときは、復元の合図をフラッシュに置き換えてリダイレクトする。
     * 数量・住所・支払方法の操作と金額の更新は Livewire の reservation-form コンポーネントが担当する
     * （注文トークンの発行もそこで行う）。
     * 作った時点で支払い済みだった銀行振込の予約が、保存できないまま保存待ちに残っているときは、先に保存する
     * （保存できたらサンクスページへ。まだ処理中ならフォームを出さず、再読み込みを案内する。押し直して予約が重複するのを防ぐ）。
     * 存在しない商品・予約限定でない商品は404。予約可能数に達している商品は、商品詳細へ戻して受付終了を案内する。
     * 取得した商品と数量の上限は予約フォームにも渡し、最初の表示で商品の取得と予約数の集計を繰り返さない。
     */
    public function index(Request $request, ReservationService $reservationService, ReservationLimitService $limitService, int $item): View|RedirectResponse|Response
    {
        Log::debug('ReservationController index start');

        // 決済ページ（Stripe Checkout）の「戻る」で戻ったとき：入力を復元する合図をフラッシュに置き換え、URLからは外す
        // （再読み込みや開き直しでは復元しない）
        if ($request->query->has('restore')) {
            Log::debug('ReservationController index end (redirect)');

            return CheckoutResponder::forReservation($reservationService)->restoreIndex($item);
        }

        $user = $request->user();
        abort_if($user === null, 403);

        $paidPendingResponse = CheckoutResponder::forReservation($reservationService)->paidPending($reservationService->settlePaidBankTransfer($user));
        if ($paidPendingResponse !== null) {
            Log::debug('ReservationController index end (paid pending reservation)');

            return $paidPendingResponse;
        }

        // 商品の取得と予約済みの数量の集計は、ここで1回ずつだけ行い、フォーム（Livewire）にも渡す
        $foundItem = $reservationService->findReservableItem($item);
        $remaining = $limitService->remaining($foundItem);
        if ($limitService->isClosedByRemaining($remaining)) {
            Log::debug('ReservationController index end (closed)');

            return redirect()
                ->route('shop.items.show', $foundItem)
                ->with(ReservationService::CLOSED_FLASH_KEY, ReservationService::CLOSED_MESSAGE);
        }

        Log::debug('ReservationController index end');

        return view('shop.reservation', [
            'itemId' => $foundItem->id,
            'item' => $foundItem,
            'maxQuantity' => $limitService->maxQuantityByRemaining($remaining),
        ]);
    }

    /**
     * 「予約を確定する」：予約を再計算して、決済ページ（Stripe）・振込先のご案内・サンクスページのいずれかへ移動
     *
     * - クレジットカード：Stripe Checkout へ移動する。予約データは決済完了ページ（reservationSuccess）で決済を確認してから保存する。
     * - 銀行振込：振込先のご案内ページへ移動する。予約データは「振込み」（confirmBankTransfer）で保存する。
     *   PaymentIntent を作った時点で顧客残高から支払い済みになっていたときは、ここで保存してサンクスページへ移動する。
     *
     * 予約可能数は、ここで確認する（超えているときはエラーにして先へ進ませない）。
     * 二重送信は、予約ページが発行した1回限りの注文トークン（order_token）で防ぐ。別のリクエストが処理中のとき（連打・複数タブ）は、
     * 数秒待ってから、予約ページへ戻してエラーを表示する。
     */
    public function payment(ReservationRequest $request, ReservationService $reservationService): RedirectResponse
    {
        Log::debug('ReservationController payment start');

        $user = $request->user();
        abort_if($user === null, 403);

        $response = CheckoutResponder::forReservation($reservationService)->payment($user, $request->validated());

        Log::debug('ReservationController payment end');

        return $response;
    }

    /**
     * 予約完了ページを表示（クレジットカード専用：決済を確認して予約データを保存する）
     *
     * Stripe の success_url から来る。銀行振込は「振込み」（POST）でだけ保存するため、ここでは保存しない（案内ページへ戻す）。
     * 保存待ちの予約が無いとき（再読み込み・直接アクセス）は何も保存せずトップページへ戻す。
     * 決済が未完了のときは予約ページへ戻してエラーを表示する。Stripe に問い合わせられなかったとき（支払い済みかどうか不明）は、
     * 再決済をさせず、保存待ちの予約を残したまま、再読み込みを案内する。
     */
    public function reservationSuccess(Request $request, ReservationService $reservationService): View|RedirectResponse|Response
    {
        Log::debug('ReservationController reservationSuccess start');

        $user = $request->user();
        abort_if($user === null, 403);

        $sessionId = $request->query('session_id');
        $result = $reservationService->completeCheckout($user, is_string($sessionId) ? $sessionId : null);

        Log::debug('ReservationController reservationSuccess end', ['result' => $result->name]);

        return CheckoutResponder::forReservation($reservationService)->success($result, $user);
    }

    /**
     * 銀行振込の「振込先のご案内」ページを表示
     *
     * 保存待ちの予約が銀行振込で、振込先があるときだけ表示する。
     * 銀行振込でない予約は予約ページへ、保存待ちの予約がなければトップページへ戻す
     * （予約完了後にブラウザの「戻る」で開き直した場合も、トップページへ戻る）。
     */
    public function bankTransfer(Request $request, ReservationService $reservationService): View|RedirectResponse
    {
        Log::debug('ReservationController bankTransfer start');

        $user = $request->user();
        abort_if($user === null, 403);

        $order = $reservationService->getBankTransferOrder($user);
        if ($order === null) {
            $pending = $reservationService->getPendingOrder($user);

            Log::debug('ReservationController bankTransfer end (redirect)');

            return $pending === null
                ? redirect()->route('top.index')
                : redirect()->route('shop.reservation.index', $pending->itemId());
        }

        Log::debug('ReservationController bankTransfer end');

        return view('shop.bank_transfer', [
            'order' => $order,
            'bankTransfer' => $order->bankTransfer,
            'cancelRoute' => 'shop.reservation.bank-transfer.cancel',
            'confirmRoute' => 'shop.reservation.bank-transfer.confirm',
            'orderHeading' => 'ご予約内容',
            'shippingNotice' => '下記の口座へお振込みください。お振込みの確認後、商品は9月より、準備が整い次第順次発送いたします。',
        ]);
    }

    /**
     * 「振込先のご案内」の「戻る」：銀行振込の支払いをキャンセルして予約ページへ戻る
     *
     * 状態を変えるため POST。予約データは作らず、入力内容は予約ページに残る。
     * キャンセルに失敗したときは、PaymentIntent の状態を確認し直し、入金済み・入金処理中なら予約データを保存して
     * サンクスページへ、それ以外の失敗は案内ページに戻してエラーを表示する（保存待ちの予約は変えない）。
     */
    public function cancelBankTransfer(Request $request, ReservationService $reservationService): RedirectResponse
    {
        Log::debug('ReservationController cancelBankTransfer start');

        $user = $request->user();
        abort_if($user === null, 403);

        $result = $reservationService->cancelBankTransfer($user);

        Log::debug('ReservationController cancelBankTransfer end');

        return CheckoutResponder::forReservation($reservationService)->cancelBankTransfer($result, $user);
    }

    /**
     * 「振込先のご案内」の「振込み」：PaymentIntent を確認して予約データを保存し、サンクスページへ移動する
     *
     * 状態を変えるため POST（CSRF あり）。サンクスページはフラッシュで1回だけ表示する。
     */
    public function confirmBankTransfer(Request $request, ReservationService $reservationService): RedirectResponse
    {
        Log::debug('ReservationController confirmBankTransfer start');

        $user = $request->user();
        abort_if($user === null, 403);

        $result = $reservationService->confirmBankTransfer($user);

        Log::debug('ReservationController confirmBankTransfer end', ['result' => $result->name]);

        return CheckoutResponder::forReservation($reservationService)->bankTransferConfirm($result, $user);
    }

    /**
     * 銀行振込の予約完了ページ（サンクスページ）を表示
     *
     * 「振込み」で保存した直後（フラッシュあり）だけ表示する。再読み込み・直接アクセスはトップページへ戻す。
     */
    public function thanks(): View|RedirectResponse
    {
        Log::debug('ReservationController thanks start');

        if (! session()->has(ReservationService::THANKS_FLASH_KEY)) {
            Log::debug('ReservationController thanks end (redirect)');

            return redirect()->route('top.index');
        }

        Log::debug('ReservationController thanks end');

        return view('shop.reservation_thanks');
    }
}
