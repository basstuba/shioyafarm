<?php

namespace Tests\Feature;

use App\Enums\BankTransferCancelResult;
use App\Enums\CheckoutResult;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Http\Responses\CheckoutFlow;
use App\Http\Responses\CheckoutResponder;
use App\Models\User;
use App\Services\CheckoutDestination;
use App\Services\CheckoutService;
use App\Services\Payment\PaymentGatewayException;
use App\Services\PendingOrder;
use App\Services\PurchaseService;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 購入・予約の手続きの応答（CheckoutResponder）のテスト
 *
 * 購入（shop.checkout.*）と予約（shop.reservation.*）で、ルート名・フラッシュのキー・文言・戻り先だけが違うことを確認する。
 */
class CheckoutResponderTest extends TestCase
{
    /**
     * 指定した商品の保存待ちの注文を作る（商品詳細からの購入・予約）
     */
    private function pendingOrder(int $itemId): PendingOrder
    {
        return new PendingOrder(
            1,
            PurchaseSource::Item,
            'user_1',
            '山田花子',
            '08011112222',
            '北海道',
            '札幌市中央区1-1-1',
            PaymentMethod::Card,
            [['item_id' => $itemId, 'name' => '北の恵みセット 10kg', 'quantity' => 1, 'price' => 1450, 'subtotal' => 1450]],
            1450,
            1530,
            0,
            2980,
        );
    }

    /**
     * 保存待ちの注文の商品IDを返すサービス（購入は PurchaseService、予約は ReservationService）のモックで、応答を作るオブジェクトを作る
     *
     * @return array{CheckoutResponder, MockInterface&CheckoutService}
     */
    private function responder(CheckoutFlow $flow, ?int $pendingItemId): array
    {
        $isReservation = $flow == CheckoutFlow::reservation();
        $service = Mockery::mock($isReservation ? ReservationService::class : PurchaseService::class);
        $service->shouldReceive('getPendingOrder')->andReturn($pendingItemId === null ? null : $this->pendingOrder($pendingItemId));

        $responder = $isReservation ? CheckoutResponder::forReservation($service) : CheckoutResponder::forPurchase($service);

        return [$responder, $service];
    }

    /**
     * 購入・予約それぞれの設定（設定名 => [設定, ルート名の共通部分, サンクスの合図, 復元の合図, 呼び方, 確定ボタン, 完了ページ]）
     *
     * @return array<string, array{CheckoutFlow, string, string, string, string, string, string}>
     */
    public static function flows(): array
    {
        return [
            '購入' => [CheckoutFlow::purchase(), 'shop.checkout', PurchaseService::THANKS_FLASH_KEY, PurchaseService::RESTORE_FLASH_KEY, 'ご注文', '注文を確定する', 'shop.purchase_thanks'],
            '予約' => [CheckoutFlow::reservation(), 'shop.reservation', ReservationService::THANKS_FLASH_KEY, ReservationService::RESTORE_FLASH_KEY, 'ご予約', '予約を確定する', 'shop.reservation_thanks'],
        ];
    }

    /**
     * リダイレクト応答のセッションのエラー（payment）の文言を取り出す
     */
    private function paymentError(RedirectResponse $response): ?string
    {
        $errors = $response->getSession()->get('errors');

        return $errors instanceof ViewErrorBag ? $errors->first('payment') : null;
    }

    /**
     * 設定は、購入と予約でルート名・フラッシュのキー・文言の違いを持つ
     */
    #[Test]
    #[DataProvider('flows')]
    public function 設定は購入と予約の違いを持つ(CheckoutFlow $flow, string $prefix, string $thanksKey, string $restoreKey, string $noun, string $label, string $view): void
    {
        $this->assertSame($prefix, $flow->routePrefix);
        $this->assertSame($prefix.'.bank-transfer', $flow->route('bank-transfer'));
        $this->assertSame($thanksKey, $flow->thanksFlashKey);
        $this->assertSame($restoreKey, $flow->restoreFlashKey);
        $this->assertSame($noun, $flow->orderNoun);
        $this->assertSame($label, $flow->confirmLabel);
        $this->assertSame($view, $flow->thanksView);
        $this->assertSame($prefix === 'shop.reservation', $flow->itemRequired);
    }

    /**
     * `?restore=1` の戻り先は、復元の合図を付けた購入・予約ページ（購入は商品IDなし＝カートでもよい）
     */
    #[Test]
    public function 復元の合図を付けて購入ページと予約ページへ戻す(): void
    {
        [$purchase] = $this->responder(CheckoutFlow::purchase(), null);
        [$reservation] = $this->responder(CheckoutFlow::reservation(), null);

        $cart = $purchase->restoreIndex(null);
        $this->assertSame(route('shop.checkout.index'), $cart->getTargetUrl());
        $this->assertTrue($cart->getSession()->get(PurchaseService::RESTORE_FLASH_KEY));

        $item = $purchase->restoreIndex(7);
        $this->assertSame(route('shop.checkout.index', 7), $item->getTargetUrl());

        $reserved = $reservation->restoreIndex(7);
        $this->assertSame(route('shop.reservation.index', 7), $reserved->getTargetUrl());
        $this->assertTrue($reserved->getSession()->get(ReservationService::RESTORE_FLASH_KEY));
    }

    /**
     * 保存待ちの注文のページへ戻す：購入は商品IDなし（カート）でも購入ページへ、予約は保存待ちの注文がなければトップページへ
     */
    #[Test]
    public function 保存待ちの注文の購入ページか予約ページへ戻す(): void
    {
        $user = new User;

        [$purchase] = $this->responder(CheckoutFlow::purchase(), null);
        $response = $purchase->restore($user);
        $this->assertSame(route('shop.checkout.index'), $response->getTargetUrl());
        $this->assertTrue($response->getSession()->get(PurchaseService::RESTORE_FLASH_KEY));

        [$purchase] = $this->responder(CheckoutFlow::purchase(), 4);
        $this->assertSame(route('shop.checkout.index', 4), $purchase->restore($user)->getTargetUrl());

        [$reservation] = $this->responder(CheckoutFlow::reservation(), null);
        $response = $reservation->restore($user);
        $this->assertSame(route('top.index'), $response->getTargetUrl());
        $this->assertNull($response->getSession()->get(ReservationService::RESTORE_FLASH_KEY));

        [$reservation] = $this->responder(CheckoutFlow::reservation(), 4);
        $response = $reservation->restore($user);
        $this->assertSame(route('shop.reservation.index', 4), $response->getTargetUrl());
        $this->assertTrue($response->getSession()->get(ReservationService::RESTORE_FLASH_KEY));
    }

    /**
     * 支払い済みで保存待ちに残っていた注文：保存できたらサンクスページ、処理中・確認失敗は503、それ以外は null
     */
    #[Test]
    #[DataProvider('flows')]
    public function 支払い済みの保存待ちの応答(CheckoutFlow $flow, string $prefix, string $thanksKey): void
    {
        [$responder] = $this->responder($flow, null);

        foreach ([CheckoutResult::Completed, CheckoutResult::AlreadyCompleted] as $result) {
            $response = $responder->paidPending($result);
            $this->assertInstanceOf(RedirectResponse::class, $response);
            $this->assertSame(route($prefix.'.thanks'), $response->getTargetUrl());
            $this->assertTrue($response->getSession()->get($thanksKey));
        }

        $busy = $responder->paidPending(CheckoutResult::Busy);
        $this->assertInstanceOf(Response::class, $busy);
        $this->assertSame(503, $busy->getStatusCode());
        $this->assertSame('お支払いは完了しています。ただいま確定処理中です。しばらく待ってから、このページを再読み込みしてください', $busy->original->getData()['message']);

        $failed = $responder->paidPending(CheckoutResult::VerificationFailed);
        $this->assertInstanceOf(Response::class, $failed);
        $this->assertSame(503, $failed->getStatusCode());
        $this->assertSame('お支払いの確認に失敗しました。時間をおいてこのページを再読み込みしてください。お支払い済みの場合は、再度のお手続きは不要です', $failed->original->getData()['message']);

        $this->assertNull($responder->paidPending(null));
        $this->assertNull($responder->paidPending(CheckoutResult::NoPendingOrder));
        $this->assertNull($responder->paidPending(CheckoutResult::NotPaid));
    }

    /**
     * 決済完了ページの応答：完了ページ・トップ・案内ページ・503（呼び方だけ違う）・お支払い未確認
     */
    #[Test]
    #[DataProvider('flows')]
    public function 決済完了ページの応答(CheckoutFlow $flow, string $prefix, string $thanksKey, string $restoreKey, string $noun, string $label, string $view): void
    {
        [$responder] = $this->responder($flow, 9);
        $user = new User;

        $this->assertSame($view, $responder->success(CheckoutResult::Completed, $user)->name());
        $this->assertSame($view, $responder->success(CheckoutResult::AlreadyCompleted, $user)->name());

        $top = $responder->success(CheckoutResult::NoPendingOrder, $user);
        $this->assertInstanceOf(RedirectResponse::class, $top);
        $this->assertSame(route('top.index'), $top->getTargetUrl());

        $bank = $responder->success(CheckoutResult::UseBankTransferConfirmation, $user);
        $this->assertInstanceOf(RedirectResponse::class, $bank);
        $this->assertSame(route($prefix.'.bank-transfer'), $bank->getTargetUrl());

        $busy = $responder->success(CheckoutResult::Busy, $user);
        $this->assertInstanceOf(Response::class, $busy);
        $this->assertSame(503, $busy->getStatusCode());
        $this->assertSame("ただいま{$noun}を処理しています。しばらく待ってから、このページを再読み込みしてください", $busy->original->getData()['message']);

        $failed = $responder->success(CheckoutResult::VerificationFailed, $user);
        $this->assertInstanceOf(Response::class, $failed);
        $this->assertSame(503, $failed->getStatusCode());

        foreach ([CheckoutResult::SessionMismatch, CheckoutResult::NotPaid] as $result) {
            $notPaid = $responder->success($result, $user);
            $this->assertInstanceOf(RedirectResponse::class, $notPaid);
            $this->assertSame(route($prefix.'.index', 9), $notPaid->getTargetUrl());
            $this->assertTrue($notPaid->getSession()->get($restoreKey));
            $this->assertSame("お支払いを確認できませんでした。お手数ですが、もう一度「{$label}」からお進みください", $this->paymentError($notPaid));
        }
    }

    /**
     * 銀行振込の「振込み」の応答：サンクスページ・トップ・案内ページへエラー付きで戻す・お支払い未確認
     */
    #[Test]
    #[DataProvider('flows')]
    public function 振込みの応答(CheckoutFlow $flow, string $prefix, string $thanksKey, string $restoreKey, string $noun, string $label): void
    {
        [$responder] = $this->responder($flow, 9);
        $user = new User;

        $thanks = $responder->bankTransferConfirm(CheckoutResult::Completed, $user);
        $this->assertSame(route($prefix.'.thanks'), $thanks->getTargetUrl());
        $this->assertTrue($thanks->getSession()->get($thanksKey));

        $this->assertSame(route('top.index'), $responder->bankTransferConfirm(CheckoutResult::NoPendingOrder, $user)->getTargetUrl());

        $failed = $responder->bankTransferConfirm(CheckoutResult::VerificationFailed, $user);
        $this->assertSame(route($prefix.'.bank-transfer'), $failed->getTargetUrl());
        $this->assertSame('お支払いの確認に失敗しました。時間をおいて、もう一度「振込み」を押してください', $this->paymentError($failed));

        $busy = $responder->bankTransferConfirm(CheckoutResult::Busy, $user);
        $this->assertSame(route($prefix.'.bank-transfer'), $busy->getTargetUrl());
        $this->assertSame("ただいま{$noun}を処理しています。しばらく待ってから、もう一度「振込み」を押してください", $this->paymentError($busy));

        foreach ([CheckoutResult::SessionMismatch, CheckoutResult::NotPaid, CheckoutResult::UseBankTransferConfirmation] as $result) {
            $notPaid = $responder->bankTransferConfirm($result, $user);
            $this->assertSame(route($prefix.'.index', 9), $notPaid->getTargetUrl());
            $this->assertTrue($notPaid->getSession()->get($restoreKey));
            $this->assertSame("お支払いを確認できませんでした。お手数ですが、もう一度「{$label}」からお進みください", $this->paymentError($notPaid));
        }
    }

    /**
     * 銀行振込の「戻る」の応答：キャンセルできたら入力を復元して戻る・入金済みなら保存・失敗は案内ページにエラー
     */
    #[Test]
    #[DataProvider('flows')]
    public function 戻るの応答(CheckoutFlow $flow, string $prefix, string $thanksKey, string $restoreKey): void
    {
        [$responder, $service] = $this->responder($flow, 9);
        $user = new User;

        $this->assertSame(route('top.index'), $responder->cancelBankTransfer(null, $user)->getTargetUrl());

        $cancelled = $responder->cancelBankTransfer(BankTransferCancelResult::Cancelled, $user);
        $this->assertSame(route($prefix.'.index', 9), $cancelled->getTargetUrl());
        $this->assertTrue($cancelled->getSession()->get($restoreKey));

        $service->shouldReceive('confirmBankTransfer')->once()->with($user)->andReturn(CheckoutResult::Completed);
        $paid = $responder->cancelBankTransfer(BankTransferCancelResult::Paid, $user);
        $this->assertSame(route($prefix.'.thanks'), $paid->getTargetUrl());
        $this->assertTrue($paid->getSession()->get($thanksKey));

        $failed = $responder->cancelBankTransfer(BankTransferCancelResult::Failed, $user);
        $this->assertSame(route($prefix.'.bank-transfer'), $failed->getTargetUrl());
        $this->assertSame('振込みの取り消しができませんでした。しばらく時間をおいてから、もう一度お試しください', $this->paymentError($failed));
    }

    /**
     * 「確定する」：決済ページへ移動する。保存済み（支払い済み）のときはサンクスページの合図も付ける
     */
    #[Test]
    #[DataProvider('flows')]
    public function 確定すると移動先へ進む(CheckoutFlow $flow, string $prefix, string $thanksKey): void
    {
        [$responder, $service] = $this->responder($flow, null);
        $user = new User;
        $data = ['quantity' => 1];
        $service->shouldReceive('beginCheckout')->with($user, $data)->andReturn(
            new CheckoutDestination('https://checkout.stripe.test/pay'),
            new CheckoutDestination(route($prefix.'.thanks'), true),
        );

        $stripe = $responder->payment($user, $data);
        $this->assertSame('https://checkout.stripe.test/pay', $stripe->getTargetUrl());
        $this->assertNull($stripe->getSession()->get($thanksKey));

        $completed = $responder->payment($user, $data);
        $this->assertSame(route($prefix.'.thanks'), $completed->getTargetUrl());
        $this->assertTrue($completed->getSession()->get($thanksKey));
    }

    /**
     * 決済ページを開けなかったときは、入力を残して直前のページへ戻し、エラーを表示する
     */
    #[Test]
    #[DataProvider('flows')]
    public function 決済ページを開けなければエラーで戻る(CheckoutFlow $flow): void
    {
        [$responder, $service] = $this->responder($flow, null);
        $service->shouldReceive('beginCheckout')->andThrow(new PaymentGatewayException('通信エラー'));

        $response = $responder->payment(new User, []);

        $this->assertSame('決済ページを開けませんでした。しばらく時間をおいてから、もう一度お試しください', $this->paymentError($response));
    }
}
