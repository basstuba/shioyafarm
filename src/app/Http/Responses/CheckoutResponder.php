<?php

namespace App\Http\Responses;

use App\Enums\BankTransferCancelResult;
use App\Enums\CheckoutResult;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Payment\PaymentGatewayException;
use App\Services\PurchaseService;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 購入・予約の手続きの結果に応じた応答（リダイレクト・エラー表示・再読み込みの案内）を作る
 *
 * 購入（PurchaseController / PaymentController）と予約（ReservationController）で共通の応答を、CheckoutFlow の違いだけで切り替える。
 * 保存待ちの注文の商品IDは、渡された CheckoutService（PurchaseService / ReservationService）から取得する。
 * 設定とサービスの組み合わせを取り違えないよう、forPurchase() / forReservation() からだけ作る。
 */
class CheckoutResponder
{
    /** Stripe に問い合わせられなかったときの、再読み込みの案内 */
    private const string VERIFICATION_FAILED_RELOAD_MESSAGE = 'お支払いの確認に失敗しました。時間をおいてこのページを再読み込みしてください。お支払い済みの場合は、再度のお手続きは不要です';

    private function __construct(
        private readonly CheckoutFlow $flow,
        private readonly CheckoutService $service,
    ) {}

    /**
     * 購入の応答を作る
     */
    public static function forPurchase(PurchaseService $purchaseService): self
    {
        return new self(CheckoutFlow::purchase(), $purchaseService);
    }

    /**
     * 予約の応答を作る
     */
    public static function forReservation(ReservationService $reservationService): self
    {
        return new self(CheckoutFlow::reservation(), $reservationService);
    }

    /**
     * 「注文を確定する」「予約を確定する」：決済ページ・振込先のご案内・サンクスページのいずれかへ移動する
     *
     * 決済ページ（Stripe）を開けなかったときは、入力を残して直前のページへ戻し、エラーを表示する。
     *
     * @param  array<string, mixed>  $data  検証済みの入力
     */
    public function payment(User $user, array $data): RedirectResponse
    {
        try {
            $destination = $this->service->beginCheckout($user, $data);
        } catch (PaymentGatewayException $e) {
            Log::error('決済ページを開けませんでした', ['message' => $e->getMessage()]);

            return back()
                ->withInput()
                ->withErrors(['payment' => '決済ページを開けませんでした。しばらく時間をおいてから、もう一度お試しください']);
        }

        $response = redirect()->away($destination->url);

        return $destination->completed ? $response->with($this->flow->thanksFlashKey, true) : $response;
    }

    /**
     * `?restore=1` で開かれたとき：入力を復元する合図をフラッシュに置き換えて、購入・予約ページへ戻す
     */
    public function restoreIndex(?int $itemId): RedirectResponse
    {
        return redirect()->route($this->flow->route('index'), $itemId)->with($this->flow->restoreFlashKey, true);
    }

    /**
     * 保存待ちの注文のページへ戻す（入力は1回だけ復元する）
     *
     * 商品IDが必須のフロー（予約）では、保存待ちの注文がなければトップページへ戻す。
     */
    public function restore(User $user): RedirectResponse
    {
        $itemId = $this->service->getPendingOrder($user)?->itemId();

        if ($this->flow->itemRequired && $itemId === null) {
            return redirect()->route('top.index');
        }

        return $this->restoreIndex($itemId);
    }

    /**
     * 支払い済みで保存待ちに残っていた銀行振込の注文を保存した結果に応じた応答（購入・予約ページを開いたとき）
     *
     * 保存できたらサンクスページへ。まだ保存できない、または確認できないときは、ページを出さずに再読み込みを案内する
     * （フォームを出すと、押し直して新しい注文が重複して作られるため）。対象の注文がない、またはお支払いを確認できなかったときは null。
     */
    public function paidPending(?CheckoutResult $result): RedirectResponse|Response|null
    {
        return match ($result) {
            CheckoutResult::Completed, CheckoutResult::AlreadyCompleted => $this->thanksRedirect(),
            CheckoutResult::Busy => $this->pending('お支払いは完了しています。ただいま確定処理中です。しばらく待ってから、このページを再読み込みしてください'),
            CheckoutResult::VerificationFailed => $this->pending(self::VERIFICATION_FAILED_RELOAD_MESSAGE),
            default => null,
        };
    }

    /**
     * 決済完了ページ（クレジットカード専用）で、決済を確認した結果に応じた応答
     */
    public function success(CheckoutResult $result, User $user): View|RedirectResponse|Response
    {
        return match ($result) {
            CheckoutResult::Completed, CheckoutResult::AlreadyCompleted => view($this->flow->thanksView),
            CheckoutResult::NoPendingOrder => redirect()->route('top.index'),
            CheckoutResult::UseBankTransferConfirmation => redirect()->route($this->flow->route('bank-transfer')),
            CheckoutResult::VerificationFailed => $this->pending(self::VERIFICATION_FAILED_RELOAD_MESSAGE),
            CheckoutResult::Busy => $this->pending("ただいま{$this->flow->orderNoun}を処理しています。しばらく待ってから、このページを再読み込みしてください"),
            CheckoutResult::SessionMismatch, CheckoutResult::NotPaid => $this->notPaid($user),
        };
    }

    /**
     * 銀行振込の「振込み」の結果に応じた移動先
     */
    public function bankTransferConfirm(CheckoutResult $result, User $user): RedirectResponse
    {
        return match ($result) {
            CheckoutResult::Completed, CheckoutResult::AlreadyCompleted => $this->thanksRedirect(),
            CheckoutResult::NoPendingOrder => redirect()->route('top.index'),
            CheckoutResult::VerificationFailed => redirect()
                ->route($this->flow->route('bank-transfer'))
                ->withErrors(['payment' => 'お支払いの確認に失敗しました。時間をおいて、もう一度「振込み」を押してください']),
            CheckoutResult::Busy => redirect()
                ->route($this->flow->route('bank-transfer'))
                ->withErrors(['payment' => "ただいま{$this->flow->orderNoun}を処理しています。しばらく待ってから、もう一度「振込み」を押してください"]),
            CheckoutResult::SessionMismatch, CheckoutResult::NotPaid, CheckoutResult::UseBankTransferConfirmation => $this->notPaid($user),
        };
    }

    /**
     * 銀行振込の「戻る」（キャンセル）の結果に応じた移動先
     *
     * キャンセルに失敗しても入金済み・入金処理中だったとき（Paid）は、「振込み」と同じく保存してサンクスページへ移動する。
     */
    public function cancelBankTransfer(?BankTransferCancelResult $result, User $user): RedirectResponse
    {
        return match ($result) {
            null => redirect()->route('top.index'),
            BankTransferCancelResult::Cancelled => $this->restore($user),
            BankTransferCancelResult::Paid => $this->bankTransferConfirm($this->service->confirmBankTransfer($user), $user),
            BankTransferCancelResult::Failed => redirect()
                ->route($this->flow->route('bank-transfer'))
                ->withErrors(['payment' => '振込みの取り消しができませんでした。しばらく時間をおいてから、もう一度お試しください']),
        };
    }

    /**
     * お支払いを確認できなかったとき：購入・予約ページへ戻してエラーを表示する（入力は1回だけ復元する）
     */
    public function notPaid(User $user): RedirectResponse
    {
        return $this->restore($user)
            ->withErrors(['payment' => "お支払いを確認できませんでした。お手数ですが、もう一度「{$this->flow->confirmLabel}」からお進みください"]);
    }

    /**
     * 保存できたときのサンクスページへの移動（サンクスページは、この合図で1回だけ表示する）
     */
    private function thanksRedirect(): RedirectResponse
    {
        return redirect()
            ->route($this->flow->route('thanks'))
            ->with($this->flow->thanksFlashKey, true);
    }

    /**
     * 処理中・確認できないときの、再読み込みを案内するページ（503）
     */
    private function pending(string $message): Response
    {
        return response()->view('shop.payment_pending', ['message' => $message], 503);
    }
}
