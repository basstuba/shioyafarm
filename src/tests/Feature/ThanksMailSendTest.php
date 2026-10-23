<?php

namespace Tests\Feature;

use App\Enums\CheckoutResult;
use App\Enums\PaymentMethod;
use App\Mail\PurchaseThanksMail;
use App\Mail\ReservationThanksMail;
use App\Models\Item;
use App\Models\Purchase_history;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CartService;
use App\Services\Payment\BankTransferInstructions;
use App\Services\Payment\BankTransferIntentData;
use App\Services\Payment\CheckoutSessionData;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\PurchaseService;
use App\Services\ReservationService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\Concerns\ReservationTestHelpers;
use Tests\TestCase;

/**
 * 購入・予約のサンクスメールの送信のテスト
 *
 * 購入（代金引換・クレジットカード・銀行振込・顧客残高で支払い済み）と予約（クレジットカード・銀行振込・顧客残高で支払い済み）の
 * 保存直後に1通だけ送り、保存しなかった経路では送らず、送信に失敗しても保存してサンクスページへ進むことを確かめる。
 */
class ThanksMailSendTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;
    use ReservationTestHelpers;

    /**
     * 差出人・署名は config('mail.from') から出るため、.env に左右されないよう、既定の値にそろえる
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.from.address' => 'shioyafarm@example.com', 'mail.from.name' => 'しおや農園']);
    }

    /**
     * 購入のシナリオ（北海道・じゃがいも2kg×2、玉ねぎ5kg×1、人参2kg×1。商品小計1,873円・送料1,850円）の会員とカートを作る
     *
     * @return array{User, array<string, Item>}
     */
    private function purchaseScenario(): array
    {
        return $this->makeCartScenario();
    }

    /**
     * 予約のシナリオ（北海道・北の恵みセット10kg×1。商品小計1,450円・送料1,530円・合計2,980円）の会員と商品を作る
     *
     * @return array{User, Item}
     */
    private function reservationScenario(): array
    {
        $this->makeAreas();

        return [$this->makeUser(), $this->makeReservable()];
    }

    /**
     * 代金引換で「注文を確定する」（カートの全商品）
     *
     * @param  array<string, Item>  $items
     * @param  array<string, mixed>  $overrides
     */
    private function orderByCod(User $user, array $items, array $overrides = []): TestResponse
    {
        return $this->actingAs($user)->post(route('shop.checkout.payment'), $this->paymentData(
            $user,
            [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1],
            ['payment_method' => 'cod'] + $overrides,
        ));
    }

    /**
     * 銀行振込用の PaymentIntent の応答を作る
     */
    private function bankIntent(string $id, string $status, int $amount, bool $withInstructions = true): BankTransferIntentData
    {
        return new BankTransferIntentData(
            $id,
            $status,
            $amount,
            $withInstructions ? new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', $amount) : null,
        );
    }

    /**
     * Checkout Session の状態を返すモックに差し替える
     */
    private function mockCheckoutSession(string $sessionId, string $status): void
    {
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($sessionId, $status): void {
            $mock->shouldReceive('retrieveCheckoutSession')
                ->once()
                ->with($sessionId)
                ->andReturn(new CheckoutSessionData($sessionId, null, $status, $status === 'complete' ? 'paid' : 'unpaid'));
        });
    }

    /**
     * 銀行振込の PaymentIntent を取得するモックに差し替える（「振込み」で確認する）
     */
    private function mockBankIntentRetrieve(string $id, int $amount): void
    {
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($id, $amount): void {
            $mock->shouldReceive('retrieveBankTransferIntent')->once()->with($id)->andReturn($this->bankIntent($id, 'requires_action', $amount));
        });
    }

    /**
     * Stripe に何も問い合わせないことを保証する
     */
    private function gatewayNeverCalled(): void
    {
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            foreach (['createCheckoutSession', 'retrieveCheckoutSession', 'expireCheckoutSession', 'ensureCustomer', 'createBankTransferIntent', 'retrieveBankTransferIntent', 'cancelPaymentIntent'] as $method) {
                $mock->shouldNotReceive($method);
            }
        });
    }

    /**
     * 購入：代金引換の注文を確定すると、保存の直後に1通だけ届く。代引手数料の行があり、合計に含まれる（AC-1, AC-2, S-3）
     */
    #[Test]
    public function 代金引換の購入でメールが1通届く(): void
    {
        [$user, $items] = $this->purchaseScenario();
        $this->gatewayNeverCalled();
        Mail::fake();

        $this->orderByCod($user, $items)->assertRedirect(route('shop.checkout.thanks'));

        $history = Purchase_history::sole();
        $codFee = (int) config('shipping.cod_fee');
        Mail::assertSentCount(1);
        Mail::assertSent(PurchaseThanksMail::class, function (PurchaseThanksMail $mail) use ($user, $history, $codFee): bool {
            $this->assertTrue($mail->hasTo($user->email));
            $this->assertTrue($mail->hasFrom('shioyafarm@example.com', 'しおや農園'));
            $this->assertTrue($mail->hasSubject("【しおや農園】ご注文ありがとうございます（注文番号：{$history->id}）"));
            foreach (['assertSeeInHtml', 'assertSeeInText'] as $see) {
                $mail->{$see}('塩谷 太郎 様', false);
                $mail->{$see}("注文番号：{$history->id}", false);
                $mail->{$see}('お支払方法：代金引換', false);
                $mail->{$see}('じゃがいも 2kg × 2　　¥660', false);
                $mail->{$see}('商品小計　　¥1,873', false);
                $mail->{$see}('送料　　　　¥1,850', false);
                $mail->{$see}('代引手数料　¥'.number_format($codFee), false);
                $mail->{$see}('合計　　　　¥'.number_format(1873 + 1850 + $codFee), false);
                $mail->{$see}('塩谷太郎 様', false);
                $mail->{$see}('北海道札幌市中央区1-1-1', false);
                $mail->{$see}('電話番号：09012345678', false);
            }
            $mail->assertDontSeeInText('■ お振込先', false);

            return true;
        });
    }

    /**
     * 購入：お届け先氏名のHTMLのタグは、メールでもタグとして解釈されない（AC-9, S-11）
     */
    #[Test]
    public function お届け先氏名のタグはメールでエスケープされる(): void
    {
        [$user, $items] = $this->purchaseScenario();
        $this->gatewayNeverCalled();
        Mail::fake();

        $this->orderByCod($user, $items, ['delivery_name' => '<b>UAT太字</b>'])->assertRedirect(route('shop.checkout.thanks'));

        Mail::assertSent(PurchaseThanksMail::class, function (PurchaseThanksMail $mail): bool {
            $mail->assertSeeInHtml('&lt;b&gt;UAT太字&lt;/b&gt;', false);
            $mail->assertDontSeeInHtml('<b>UAT太字', false);
            $mail->assertSeeInText('<b>UAT太字</b> 様', false);

            return true;
        });
    }

    /**
     * 購入：クレジットカードは、決済ページから戻った /purchase/success で保存した直後に1通だけ届く。再読み込みしても増えない（AC-1, AC-7, S-1, S-8）
     */
    #[Test]
    public function クレジットカードの購入でメールが1通届き再読み込みでは増えない(): void
    {
        [$user, $items] = $this->purchaseScenario();
        $this->putPending($user, $items, PaymentMethod::Card, 'cs_test_ok');
        $this->mockCheckoutSession('cs_test_ok', 'complete');
        Mail::fake();

        $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => 'cs_test_ok']))->assertOk();

        $history = Purchase_history::sole();
        Mail::assertSentCount(1);
        Mail::assertSent(PurchaseThanksMail::class, function (PurchaseThanksMail $mail) use ($user, $history): bool {
            $this->assertTrue($mail->hasTo($user->email));
            $this->assertTrue($mail->hasSubject("【しおや農園】ご注文ありがとうございます（注文番号：{$history->id}）"));
            $mail->assertSeeInHtml('お支払方法：クレジットカード', false);
            $mail->assertSeeInText('合計　　　　¥3,723', false);
            $mail->assertDontSeeInText('代引手数料', false);
            $mail->assertDontSeeInText('■ お振込先', false);
            $mail->assertSeeInText('山田花子 様', false);

            return true;
        });

        // 再読み込み・直接アクセス：保存待ちの注文はなく、トップページへ移動する。メールは増えない
        $this->get(route('shop.checkout.success', ['session_id' => 'cs_test_ok']))->assertRedirect(route('top.index'));
        $this->get(route('shop.checkout.success'))->assertRedirect(route('top.index'));
        Mail::assertSentCount(1);
        $this->assertSame(1, Purchase_history::count());
    }

    /**
     * 購入：銀行振込は、振込先のご案内の「振込み」で保存した直後に1通だけ届き、お振込先が載る。連打（2回目）では増えない（AC-1, AC-4, AC-7, S-2, S-9）
     */
    #[Test]
    public function 銀行振込の購入でメールが1通届きお振込先が載る(): void
    {
        [$user, $items] = $this->purchaseScenario();
        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mockBankIntentRetrieve('pi_test_bank', 3723);
        Mail::fake();

        $this->actingAs($user)->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('shop.checkout.thanks'));

        $history = Purchase_history::sole();
        Mail::assertSentCount(1);
        Mail::assertSent(PurchaseThanksMail::class, function (PurchaseThanksMail $mail) use ($user, $history): bool {
            $this->assertTrue($mail->hasTo($user->email));
            $this->assertTrue($mail->hasSubject("【しおや農園】ご注文ありがとうございます（注文番号：{$history->id}）"));
            foreach (['assertSeeInHtml', 'assertSeeInText'] as $see) {
                $mail->{$see}('お支払方法：銀行振込', false);
                $mail->{$see}('■ お振込先', false);
                $mail->{$see}('銀行名：いろは銀行', false);
                $mail->{$see}('支店名：中央支店', false);
                $mail->{$see}('口座種別：普通', false);
                $mail->{$see}('口座番号：1234567', false);
                $mail->{$see}('口座名義：ストライプジャパン', false);
                $mail->{$see}('お振込金額：¥3,723', false);
                $mail->{$see}('ご入金を確認してから発送いたします。', false);
            }
            $mail->assertDontSeeInText('お客様の残高からお支払いが完了しています', false);

            return true;
        });

        // 2回目（保存待ちの注文は消えている）：保存もメールもしない
        $this->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('top.index'));
        Mail::assertSentCount(1);
        $this->assertSame(1, Purchase_history::count());
    }

    /**
     * 購入：銀行振込で、PaymentIntent を作った時点で顧客残高から支払い済みなら、「注文を確定する」の時点で保存して1通届く。口座の項目は載らない（AC-4, S-4）
     */
    #[Test]
    public function 顧客残高で支払い済みの銀行振込の購入でメールが1通届く(): void
    {
        [$user, $items] = $this->purchaseScenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn($this->bankIntent('pi_paid', 'succeeded', 3723, false));
        });
        Mail::fake();

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['payment_method' => 'customer_balance']))
            ->assertRedirect(route('shop.checkout.thanks'));

        $this->assertSame(1, Purchase_history::count());
        Mail::assertSentCount(1);
        Mail::assertSent(PurchaseThanksMail::class, function (PurchaseThanksMail $mail) use ($user): bool {
            $this->assertTrue($mail->hasTo($user->email));
            foreach (['assertSeeInHtml', 'assertSeeInText'] as $see) {
                $mail->{$see}('■ お振込先', false);
                $mail->{$see}('お客様の残高からお支払いが完了しています', false);
                $mail->{$see}('合計　　　　¥3,723', false);
            }
            foreach (['assertDontSeeInHtml', 'assertDontSeeInText'] as $dontSee) {
                $mail->{$dontSee}('銀行名', false);
                $mail->{$dontSee}('口座番号', false);
            }

            return true;
        });
    }

    /**
     * 予約：クレジットカードは /reservation/success で保存した直後に1通届き、9月からの発送の案内が載る。再読み込みでは増えない（AC-5, AC-7, S-5）
     */
    #[Test]
    public function クレジットカードの予約でメールが1通届き再読み込みでは増えない(): void
    {
        [$user, $item] = $this->reservationScenario();
        $this->putPendingReservation($user, $item, PaymentMethod::Card, 'cs_test_res');
        $this->mockCheckoutSession('cs_test_res', 'complete');
        Mail::fake();

        $this->actingAs($user)->get(route('shop.reservation.success', ['session_id' => 'cs_test_res']))->assertOk();

        $reservation = Reservation::sole();
        Mail::assertSentCount(1);
        Mail::assertSent(ReservationThanksMail::class, function (ReservationThanksMail $mail) use ($user, $reservation): bool {
            $this->assertTrue($mail->hasTo($user->email));
            $this->assertTrue($mail->hasFrom('shioyafarm@example.com', 'しおや農園'));
            $this->assertTrue($mail->hasSubject("【しおや農園】ご予約ありがとうございます（予約番号：{$reservation->id}）"));
            foreach (['assertSeeInHtml', 'assertSeeInText'] as $see) {
                $mail->{$see}('塩谷 太郎 様', false);
                $mail->{$see}("予約番号：{$reservation->id}", false);
                $mail->{$see}('お支払方法：クレジットカード', false);
                $mail->{$see}('北の恵みセット 10kg × 1　　¥1,450', false);
                $mail->{$see}('商品小計　　¥1,450', false);
                $mail->{$see}('送料　　　　¥1,530', false);
                $mail->{$see}('合計　　　　¥2,980', false);
                $mail->{$see}('商品は9月より、準備が整い次第順次発送いたします。', false);
                $mail->{$see}('山田花子 様', false);
            }
            $mail->assertDontSeeInText('■ お振込先', false);
            $mail->assertDontSeeInText('代引手数料', false);

            return true;
        });

        $this->get(route('shop.reservation.success', ['session_id' => 'cs_test_res']))->assertRedirect(route('top.index'));
        $this->get(route('shop.reservation.success'))->assertRedirect(route('top.index'));
        Mail::assertSentCount(1);
        $this->assertSame(1, Reservation::count());
    }

    /**
     * 予約：銀行振込は「振込み」で保存した直後に1通届き、お振込先が載る。連打（2回目）では増えない（AC-4, AC-5, AC-7, S-6, S-9）
     */
    #[Test]
    public function 銀行振込の予約でメールが1通届きお振込先が載る(): void
    {
        [$user, $item] = $this->reservationScenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mockBankIntentRetrieve('pi_test_bank', 2980);
        Mail::fake();

        $this->actingAs($user)->post(route('shop.reservation.bank-transfer.confirm'))->assertRedirect(route('shop.reservation.thanks'));

        $reservation = Reservation::sole();
        Mail::assertSentCount(1);
        Mail::assertSent(ReservationThanksMail::class, function (ReservationThanksMail $mail) use ($user, $reservation): bool {
            $this->assertTrue($mail->hasTo($user->email));
            $this->assertTrue($mail->hasSubject("【しおや農園】ご予約ありがとうございます（予約番号：{$reservation->id}）"));
            foreach (['assertSeeInHtml', 'assertSeeInText'] as $see) {
                $mail->{$see}('お支払方法：銀行振込', false);
                $mail->{$see}('銀行名：いろは銀行', false);
                $mail->{$see}('口座番号：1234567', false);
                $mail->{$see}('お振込金額：¥2,980', false);
            }

            return true;
        });

        $this->post(route('shop.reservation.bank-transfer.confirm'))->assertRedirect(route('top.index'));
        Mail::assertSentCount(1);
        $this->assertSame(1, Reservation::count());
    }

    /**
     * 予約：銀行振込で、PaymentIntent を作った時点で顧客残高から支払い済みなら、予約の確定の時点で保存して1通届く（AC-4, AC-5）
     */
    #[Test]
    public function 顧客残高で支払い済みの銀行振込の予約でメールが1通届く(): void
    {
        [$user, $item] = $this->reservationScenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn($this->bankIntent('pi_paid', 'succeeded', 2980, false));
        });
        Mail::fake();

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 1, ['payment_method' => 'customer_balance']))
            ->assertRedirect(route('shop.reservation.thanks'));

        $this->assertSame(1, Reservation::count());
        Mail::assertSentCount(1);
        Mail::assertSent(ReservationThanksMail::class, function (ReservationThanksMail $mail) use ($user): bool {
            $this->assertTrue($mail->hasTo($user->email));
            foreach (['assertSeeInHtml', 'assertSeeInText'] as $see) {
                $mail->{$see}('お客様の残高からお支払いが完了しています', false);
                $mail->{$see}('合計　　　　¥2,980', false);
            }
            $mail->assertDontSeeInText('銀行名', false);

            return true;
        });
    }

    /**
     * 保存しない経路（購入）：決済ページへの移動・振込先のご案内への移動・「戻る」・カードの未払い・直接アクセスでは送らない（AC-7, S-7）
     */
    #[Test]
    public function 購入を保存しない経路ではメールを送らない(): void
    {
        [$user, $items] = $this->purchaseScenario();
        Mail::fake();
        $data = fn (string $method): array => $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['payment_method' => $method]);

        // クレジットカードで「注文を確定する」→ Stripe の決済ページへ移動（まだ保存しない）
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_new', 'https://checkout.stripe.test/cs_new', 'open', 'unpaid'));
        });
        $this->actingAs($user)->post(route('shop.checkout.payment'), $data('card'))->assertRedirect('https://checkout.stripe.test/cs_new');
        $this->assertSame(0, Purchase_history::count());

        // カードが拒否された・支払いが完了していない：success に戻っても保存しない
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_new')->andReturn(new CheckoutSessionData('cs_new', null, 'open', 'unpaid'));
        });
        $this->get(route('shop.checkout.success', ['session_id' => 'cs_new']))->assertRedirect();
        $this->assertSame(0, Purchase_history::count());

        // 直接アクセス（保存待ちの注文なし）
        $this->flushSession();
        $this->gatewayNeverCalled();
        $this->actingAs($user)->get(route('shop.checkout.success'))->assertRedirect(route('top.index'));
        $this->get(route('shop.checkout.thanks'))->assertRedirect(route('top.index'));

        Mail::assertNothingSent();
    }

    /**
     * 保存しない経路（購入の銀行振込）：振込先のご案内へ移動しても、「戻る」でキャンセルしても送らない（AC-7, S-7）
     */
    #[Test]
    public function 銀行振込の案内と戻るではメールを送らない(): void
    {
        [$user, $items] = $this->purchaseScenario();
        Mail::fake();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn($this->bankIntent('pi_wait', 'requires_action', 3723));
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_wait');
        });
        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['payment_method' => 'customer_balance']))
            ->assertRedirect(route('shop.checkout.bank-transfer'));
        $this->assertSame(0, Purchase_history::count());

        $this->post(route('shop.checkout.bank-transfer.cancel'))->assertRedirect();
        $this->assertSame(0, Purchase_history::count());

        Mail::assertNothingSent();
    }

    /**
     * 保存しない経路（予約）：決済ページ・振込先のご案内への移動、「戻る」、カードの未払い、直接アクセスでは送らない（AC-7）
     */
    #[Test]
    public function 予約を保存しない経路ではメールを送らない(): void
    {
        [$user, $item] = $this->reservationScenario();
        Mail::fake();

        // クレジットカードで「予約を確定する」→ Stripe の決済ページへ（まだ保存しない）
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_res', 'https://checkout.stripe.test/cs_res', 'open', 'unpaid'));
        });
        $this->actingAs($user)->post(route('shop.reservation.payment'), $this->reservationData($user, $item))->assertRedirect('https://checkout.stripe.test/cs_res');

        // カードが未払い
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_res')->andReturn(new CheckoutSessionData('cs_res', null, 'open', 'unpaid'));
        });
        $this->get(route('shop.reservation.success', ['session_id' => 'cs_res']))->assertRedirect();
        $this->assertSame(0, Reservation::count());

        // 銀行振込：振込先のご案内へ移動 → 「戻る」
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('expireCheckoutSession')->zeroOrMoreTimes();
            $mock->shouldReceive('retrieveCheckoutSession')->zeroOrMoreTimes()->andReturn(new CheckoutSessionData('cs_res', null, 'expired', 'unpaid'));
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn($this->bankIntent('pi_wait', 'requires_action', 2980));
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_wait');
        });
        $this->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 1, ['payment_method' => 'customer_balance']))
            ->assertRedirect(route('shop.reservation.bank-transfer'));
        $this->post(route('shop.reservation.bank-transfer.cancel'))->assertRedirect();
        $this->assertSame(0, Reservation::count());

        // 直接アクセス
        $this->flushSession();
        $this->gatewayNeverCalled();
        $this->actingAs($user)->get(route('shop.reservation.success'))->assertRedirect(route('top.index'));
        $this->get(route('shop.reservation.thanks'))->assertRedirect(route('top.index'));

        Mail::assertNothingSent();
    }

    /**
     * 二重送信（購入の代金引換）：同じ注文トークンでの2回目は保存もメールもしない（AC-7, S-9）
     */
    #[Test]
    public function 代金引換の二重送信ではメールは1通だけ(): void
    {
        [$user, $items] = $this->purchaseScenario();
        $this->gatewayNeverCalled();
        Mail::fake();
        $data = $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['payment_method' => 'cod']);

        $this->actingAs($user)->post(route('shop.checkout.payment'), $data)->assertRedirect(route('shop.checkout.thanks'));
        $this->post(route('shop.checkout.payment'), $data)->assertRedirect(route('shop.checkout.thanks'));

        $this->assertSame(1, Purchase_history::count());
        Mail::assertSentCount(1);
    }

    /**
     * 保存済みの記録（二重保存の検知）がある注文は、保存しないので、メールも送らない（AC-7）
     */
    #[Test]
    public function 保存済みの注文を再度完了させてもメールは送らない(): void
    {
        [$user, $items] = $this->purchaseScenario();
        $order = $this->putPending($user, $items, PaymentMethod::Card, 'cs_done');
        $this->mockCheckoutSession('cs_done', 'complete');
        Mail::fake();

        $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => 'cs_done']))->assertOk();
        Mail::assertSentCount(1);

        // 同じ注文が保存待ちに残っていても（別タブなど）、保存済みの記録があるので保存もメールもしない
        session(['pending_order' => $order->toArray()]);
        $this->get(route('shop.checkout.success', ['session_id' => 'cs_done']))->assertOk();

        $this->assertSame(1, Purchase_history::count());
        Mail::assertSentCount(1);
    }

    /**
     * メールの送信に失敗しても（購入）、購入履歴は保存され、サンクスページへ進み、失敗がログに残る（AC-8, S-13）
     */
    #[Test]
    public function 送信に失敗しても購入は成立しログに残る(): void
    {
        [$user, $items] = $this->purchaseScenario();
        $this->gatewayNeverCalled();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP に接続できません'));
        Log::spy();

        $this->orderByCod($user, $items)
            ->assertRedirect(route('shop.checkout.thanks'))
            ->assertSessionHas(PurchaseService::THANKS_FLASH_KEY, true)
            ->assertSessionHasNoErrors();

        $history = Purchase_history::sole();
        $this->assertSame(3, $history->purchaseHistoryItems()->count());
        $this->assertNull(session('pending_order'));
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'サンクスメールの送信に失敗')
                && $context['id'] === $history->id
                && $context['saved'] === Purchase_history::class
                && $context['message'] === 'SMTP に接続できません')
            ->once();

        // サンクスページも表示できる
        $this->followingRedirects()->actingAs($user)->get(route('shop.checkout.thanks'))->assertOk();
    }

    /**
     * 保存の後の片づけ（カートの更新）で例外が起きても、保存した購入のメールは1通送られる。例外はこれまでどおり外に出る
     */
    #[Test]
    public function 保存後の片づけで例外が起きてもメールは送られる(): void
    {
        [$user, $items] = $this->purchaseScenario();
        $this->gatewayNeverCalled();
        $this->partialMock(CartService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('reduceQuantities')->once()->andThrow(new RuntimeException('カートを更新できません'));
        });
        Mail::fake();

        // 片づけの例外はこれまでどおり外に出る（500）。メールはレスポンスの後（リクエストの終了時）に、500でも送られる
        $this->orderByCod($user, $items)->assertStatus(500);

        $this->assertSame(1, Purchase_history::count());
        Mail::assertSentCount(1);
        Mail::assertSent(PurchaseThanksMail::class, fn (PurchaseThanksMail $mail): bool => $mail->hasTo($user->email));
    }

    /**
     * 保存の直後の完了マーカーの記録（キャッシュへの書き込み）で例外が起きても、保存した購入のメールは1通送られる
     */
    #[Test]
    public function 完了マーカーの記録で例外が起きてもメールは送られる(): void
    {
        [$user, $items] = $this->purchaseScenario();
        $this->gatewayNeverCalled();
        // 完了マーカー（purchase-completed:…）の書き込みだけ失敗するキャッシュに差し替える（ほかの書き込みは通常どおり）
        Cache::extend('marker-failing', fn () => Cache::repository(new class extends ArrayStore
        {
            public function put($key, $value, $seconds): bool
            {
                if (str_contains($key, 'purchase-completed:')) {
                    throw new RuntimeException('キャッシュに書き込めません');
                }

                return parent::put($key, $value, $seconds);
            }
        }));
        config(['cache.stores.marker-failing' => ['driver' => 'marker-failing'], 'cache.default' => 'marker-failing']);
        Cache::purge('array');
        Mail::fake();

        $this->orderByCod($user, $items)->assertStatus(500);

        $this->assertSame(1, Purchase_history::count());
        Mail::assertSentCount(1);
        Mail::assertSent(PurchaseThanksMail::class, fn (PurchaseThanksMail $mail): bool => $mail->hasTo($user->email));
    }

    /**
     * メールの送信はレスポンスを返した後（リクエストの終了時）に遅らせる。保存した時点では送らず、遅延実行を流したときに1通送る
     *
     * 排他ロックの中（beginCheckout など）から保存しても、メールサーバーの応答を待たない。
     */
    #[Test]
    public function メールの送信はリクエストの終了時まで遅らせる(): void
    {
        [$user, $items] = $this->purchaseScenario();
        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mockBankIntentRetrieve('pi_test_bank', 3723);
        Mail::fake();

        $result = app(PurchaseService::class)->confirmBankTransfer($user);

        $this->assertSame(CheckoutResult::Completed, $result);
        $this->assertSame(1, Purchase_history::count());
        Mail::assertNothingSent();

        app(DeferredCallbackCollection::class)->invoke();

        Mail::assertSentCount(1);
        Mail::assertSent(PurchaseThanksMail::class, fn (PurchaseThanksMail $mail): bool => $mail->hasTo($user->email));
    }

    /**
     * メールの送信に失敗しても（予約）、予約は保存され、サンクスページへ進み、失敗がログに残る（AC-8）
     */
    #[Test]
    public function 送信に失敗しても予約は成立しログに残る(): void
    {
        [$user, $item] = $this->reservationScenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mockBankIntentRetrieve('pi_test_bank', 2980);
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP に接続できません'));
        Log::spy();

        $this->actingAs($user)
            ->post(route('shop.reservation.bank-transfer.confirm'))
            ->assertRedirect(route('shop.reservation.thanks'))
            ->assertSessionHas(ReservationService::THANKS_FLASH_KEY, true);

        $reservation = Reservation::sole();
        $this->assertNull(session('pending_reservation'));
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'サンクスメールの送信に失敗')
                && $context['id'] === $reservation->id
                && $context['saved'] === Reservation::class)
            ->once();
    }

    /**
     * メールの送信先の設定が壊れていて例外になったときも、購入は成立してログに残る
     */
    #[Test]
    public function 送信の設定が壊れていても購入は成立する(): void
    {
        [$user, $items] = $this->purchaseScenario();
        $this->gatewayNeverCalled();
        // 現在の送信先の設定を壊して、送信時に例外にする
        config(['mail.default' => 'broken', 'mail.mailers.broken' => ['transport' => 'unknown-transport']]);
        Log::spy();

        $this->orderByCod($user, $items)->assertRedirect(route('shop.checkout.thanks'));

        $this->assertSame(1, Purchase_history::count());
        Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'サンクスメールの送信に失敗'))->once();
    }
}
