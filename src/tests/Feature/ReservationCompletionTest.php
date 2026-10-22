<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Item;
use App\Models\Reservation;
use App\Models\Reservation_bank_transfer;
use App\Models\User;
use App\Services\Payment\BankTransferInstructions;
use App\Services\Payment\BankTransferIntentData;
use App\Services\Payment\CheckoutSessionData;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\Concerns\ReservationTestHelpers;
use Tests\TestCase;

/**
 * 予約の完了（GET /reservation/success、銀行振込の「振込先のご案内」「戻る」「振込み」、サンクスページ）のテスト
 */
class ReservationCompletionTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;
    use ReservationTestHelpers;

    /**
     * 会員（住所：北海道）と北の恵みセット10kg（1,450円・予約可能数200）を作る
     *
     * @return array{User, Item}
     */
    private function scenario(): array
    {
        $this->makeAreas();

        return [$this->makeUser(), $this->makeReservable()];
    }

    /**
     * 決済状態を返すモックに差し替える
     */
    private function mockSession(string $sessionId, string $status, ?string $paymentStatus = null): void
    {
        $paymentStatus ??= $status === 'complete' ? 'paid' : 'unpaid';

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($sessionId, $status, $paymentStatus): void {
            $mock->shouldReceive('retrieveCheckoutSession')
                ->once()
                ->with($sessionId)
                ->andReturn(new CheckoutSessionData($sessionId, null, $status, $paymentStatus));
        });
    }

    /**
     * 銀行振込の PaymentIntent を返すモックに差し替える
     */
    private function mockBankIntent(string $id, string $status, int $amount, bool $withInstructions = true, int $times = 1): void
    {
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($id, $status, $amount, $withInstructions, $times): void {
            $mock->shouldReceive('retrieveBankTransferIntent')
                ->times($times)
                ->with($id)
                ->andReturn(new BankTransferIntentData(
                    $id,
                    $status,
                    $amount,
                    $withInstructions ? new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', $amount) : null,
                ));
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
     * 未ログインはログインページへ移動する
     */
    #[Test]
    public function 未ログインはログインページへ移動する(): void
    {
        $this->get(route('shop.reservation.success'))->assertRedirect(route('login'));
        $this->get(route('shop.reservation.bank-transfer'))->assertRedirect(route('login'));
        $this->post(route('shop.reservation.bank-transfer.cancel'))->assertRedirect(route('login'));
        $this->post(route('shop.reservation.bank-transfer.confirm'))->assertRedirect(route('login'));
        $this->get(route('shop.reservation.thanks'))->assertRedirect(route('login'));
    }

    /**
     * カード：Stripe に決済状態を問い合わせてから予約データを保存し、サンクスページを表示する（AC-10, S-14）
     */
    #[Test]
    public function カードの支払いを確認して予約データを保存する(): void
    {
        [$user, $item] = $this->scenario();
        $carrot = $this->makeItem('人参', 2, 418);
        session(['cart' => [$carrot->id => 1]]);
        $this->putPendingReservation($user, $item, PaymentMethod::Card, 'cs_test_res');
        $this->mockSession('cs_test_res', 'complete');

        $this->actingAs($user)
            ->get(route('shop.reservation.success', ['session_id' => 'cs_test_res']))
            ->assertOk()
            ->assertViewIs('shop.reservation_thanks');

        $reservation = Reservation::sole();
        $this->assertSame($user->id, $reservation->user_id);
        $this->assertSame($item->id, $reservation->item_id);
        $this->assertSame(1, $reservation->quantity);
        $this->assertSame(1450, $reservation->price);
        $this->assertSame(1450, $reservation->subtotal);
        $this->assertSame(1530, $reservation->shipping_fee);
        $this->assertSame(2980, $reservation->total_price);
        $this->assertSame('card', $reservation->payment_method);
        $this->assertSame('山田花子', $reservation->delivery_name);
        $this->assertSame('北海道', $reservation->delivery_prefecture);
        $this->assertSame('札幌市中央区1-1-1', $reservation->delivery_city);
        $this->assertSame('08011112222', $reservation->delivery_tell);
        $this->assertSame(0, Reservation_bank_transfer::count());

        // 保存待ちの予約は消え、カートは変わらない（AC-14）
        $this->assertNull(session('pending_reservation'));
        $this->assertSame([$carrot->id => 1], session('cart'));
    }

    /**
     * サンクスページに背景画像・お礼・発送の案内・「ホームに戻る」が表示される（AC-13, S-21）
     */
    #[Test]
    public function サンクスページの内容(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::Card, 'cs_test_res');
        $this->mockSession('cs_test_res', 'complete');

        $response = $this->actingAs($user)->get(route('shop.reservation.success', ['session_id' => 'cs_test_res']));

        $response->assertOk()
            ->assertSee('storage/image/tool/thanks-page-photo.webp', false)
            ->assertSee('ご予約ありがとうございます。')
            ->assertSee('お届けまで、どうぞ楽しみにお待ちください。')
            ->assertSee('商品は9月より、')
            ->assertSee('準備が整い次第順次発送いたします。')
            ->assertSee('ホームに戻る')
            ->assertSee('href="'.route('top.index').'"', false)
            ->assertSee('<title>ご予約完了 | Shioya Farm</title>', false);
        // 共通のヘッダーは出さない（サンクスページ用のレイアウト）
        $this->assertStringNotContainsString('<header', $response->getContent());
    }

    /**
     * 保存待ちの予約が無いとき（再読み込み・直接アクセス）は、予約データを作らずトップページへ戻す（AC-12, S-19）
     */
    #[Test]
    public function 保存待ちの予約が無ければトップページへ戻る(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::Card, 'cs_test_res');
        $this->mockSession('cs_test_res', 'complete');

        $this->actingAs($user)->get(route('shop.reservation.success', ['session_id' => 'cs_test_res']))->assertOk();
        // 再読み込み：Stripe には問い合わせず、保存もしない
        $this->get(route('shop.reservation.success', ['session_id' => 'cs_test_res']))->assertRedirect(route('top.index'));
        $this->get(route('shop.reservation.success'))->assertRedirect(route('top.index'));

        $this->assertSame(1, Reservation::count());
    }

    /**
     * 購入の保存待ちの注文だけがあるときの /reservation/success は、購入を保存せずトップページへ戻す（購入と予約は干渉しない）
     */
    #[Test]
    public function 購入の保存待ちの注文では予約を保存しない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::Card, 'cs_purchase');
        $this->gatewayNeverCalled();

        $this->actingAs($user)->get(route('shop.reservation.success', ['session_id' => 'cs_purchase']))->assertRedirect(route('top.index'));

        $this->assertSame(0, Reservation::count());
        $this->assertNotNull(session('pending_order'));
    }

    /**
     * セッションIDが保存待ちの予約のものと違う・無いときは、予約を保存せず予約ページへ戻してエラーを表示する（入力は1回だけ復元）
     */
    #[Test]
    public function セッションidが違えば予約ページへ戻る(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::Card, 'cs_test_res');
        $this->gatewayNeverCalled();

        foreach ([['session_id' => 'cs_other'], []] as $query) {
            $this->actingAs($user)
                ->get(route('shop.reservation.success', $query))
                ->assertRedirect(route('shop.reservation.index', $item))
                ->assertSessionHasErrors('payment')
                ->assertSessionHas(ReservationService::RESTORE_FLASH_KEY, true);
        }

        $this->assertSame(0, Reservation::count());
        $this->assertNotNull(session('pending_reservation'));
    }

    /**
     * Stripe に問い合わせた結果、まだ支払われていないときは保存せず、予約ページへ戻す（S-18）
     */
    #[Test]
    public function 支払われていなければ保存しない(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::Card, 'cs_test_res');
        $this->mockSession('cs_test_res', 'open');

        $this->actingAs($user)
            ->get(route('shop.reservation.success', ['session_id' => 'cs_test_res']))
            ->assertRedirect(route('shop.reservation.index', $item))
            ->assertSessionHasErrors('payment');

        $this->assertSame(0, Reservation::count());
    }

    /**
     * Stripe に問い合わせられなかったときは、再決済をさせず、保存待ちの予約を残して再読み込みを案内する（503）
     */
    #[Test]
    public function stripeに問い合わせられなければ再読み込みを案内する(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::Card, 'cs_test_res');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->andThrow(new PaymentGatewayException('通信エラー'));
        });

        $this->actingAs($user)
            ->get(route('shop.reservation.success', ['session_id' => 'cs_test_res']))
            ->assertStatus(503)
            ->assertViewIs('shop.payment_pending')
            ->assertSee('お支払いの確認に失敗しました')
            ->assertSee('再読み込み');

        $this->assertSame(0, Reservation::count());
        $this->assertNotNull(session('pending_reservation'));
    }

    /**
     * 銀行振込の保存待ちの予約は、/reservation/success では保存せず、振込先のご案内へ戻す
     */
    #[Test]
    public function 銀行振込はsuccessでは保存しない(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->get(route('shop.reservation.success', ['session_id' => 'cs_x']))
            ->assertRedirect(route('shop.reservation.bank-transfer'));

        $this->assertSame(0, Reservation::count());
    }

    /**
     * 同じ予約を2回保存しない（同じ予約IDの保存待ちが残っていても、保存済みの記録で検知する）
     */
    #[Test]
    public function 同じ予約を二重に保存しない(): void
    {
        [$user, $item] = $this->scenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_test_res')->andReturn(new CheckoutSessionData('cs_test_res', null, 'complete', 'paid'));
        });

        $this->putPendingReservation($user, $item, PaymentMethod::Card, 'cs_test_res', null, 'order-1');
        $this->actingAs($user)->get(route('shop.reservation.success', ['session_id' => 'cs_test_res']))->assertOk();

        // 別のタブなど、古いセッションが同じ予約を保存待ちとして持っていた場合
        $this->putPendingReservation($user, $item, PaymentMethod::Card, 'cs_test_res', null, 'order-1');
        $this->get(route('shop.reservation.success', ['session_id' => 'cs_test_res']))->assertOk()->assertViewIs('shop.reservation_thanks');

        $this->assertSame(1, Reservation::count());
        $this->assertNull(session('pending_reservation'));
    }

    /**
     * 保存時には予約可能数を再確認しない（Stripe で支払っている間に重なった分は許容する）
     */
    #[Test]
    public function 保存時には予約可能数を再確認しない(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable(10, 1450, 1);
        $this->makeReservedBy($item, 1);
        $this->putPendingReservation($user, $item, PaymentMethod::Card, 'cs_test_res');
        $this->mockSession('cs_test_res', 'complete');

        $this->actingAs($user)
            ->get(route('shop.reservation.success', ['session_id' => 'cs_test_res']))
            ->assertOk();

        $this->assertSame(2, Reservation::where('item_id', $item->id)->count());
        $this->assertSame(2, (int) Reservation::where('item_id', $item->id)->sum('quantity'));
    }

    /**
     * 振込先のご案内：振込先と振込金額、ご予約内容、「戻る」「振込み」が表示される（AC-11, S-15）
     */
    #[Test]
    public function 振込先のご案内を表示する(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->get(route('shop.reservation.bank-transfer'))
            ->assertOk()
            ->assertViewIs('shop.bank_transfer')
            ->assertSee('振込先のご案内')
            ->assertSee('いろは銀行')
            ->assertSee('中央支店')
            ->assertSee('普通')
            ->assertSee('1234567')
            ->assertSee('ストライプジャパン')
            ->assertSee('2,980円')
            ->assertSee('ご予約内容')
            ->assertDontSee('ご注文内容')
            ->assertSee('北の恵みセット 10kg × 1個')
            ->assertSee('商品は9月より、準備が整い次第順次発送いたします。')
            ->assertSee('action="'.route('shop.reservation.bank-transfer.cancel').'"', false)
            ->assertSee('action="'.route('shop.reservation.bank-transfer.confirm').'"', false)
            ->assertSee('戻る')
            ->assertSee('振込み');
    }

    /**
     * 「戻る」「振込み」は、どちらかを押した直後に両方とも押せなくなる（連打で何度も送らない）
     */
    #[Test]
    public function 戻ると振込みは押した直後に押せなくなる(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_test_bank');

        $html = $this->actingAs($user)->get(route('shop.reservation.bank-transfer'))->getContent();

        $this->assertStringContainsString('x-on:submit="submitting = true"', $html);
        $this->assertMatchesRegularExpression('#<button[^>]*x-bind:disabled="submitting"[^>]*>\s*戻る#', $html);
        $this->assertMatchesRegularExpression('#<button[^>]*x-bind:disabled="submitting"[^>]*>\s*振込み#', $html);
    }

    /**
     * 保存待ちの予約が無ければトップページへ、銀行振込でなければ予約ページへ戻す
     */
    #[Test]
    public function 振込先のご案内は銀行振込の保存待ちのときだけ表示する(): void
    {
        [$user, $item] = $this->scenario();
        $this->gatewayNeverCalled();

        $this->actingAs($user)->get(route('shop.reservation.bank-transfer'))->assertRedirect(route('top.index'));

        $this->putPendingReservation($user, $item, PaymentMethod::Card, 'cs_test_res');
        $this->get(route('shop.reservation.bank-transfer'))->assertRedirect(route('shop.reservation.index', $item));
    }

    /**
     * 購入の振込先のご案内は、これまでどおり購入のルートで「戻る」「振込み」になる
     */
    #[Test]
    public function 購入の振込先のご案内は購入のルートのまま(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_bank');

        $this->actingAs($user)
            ->get(route('shop.checkout.bank-transfer'))
            ->assertOk()
            ->assertSee('action="'.route('shop.checkout.bank-transfer.cancel').'"', false)
            ->assertSee('ご注文内容')
            ->assertSee('3~5営業日以内に発送いたします');

        // 予約の保存待ちは無いので、予約の案内ページはトップへ
        $this->get(route('shop.reservation.bank-transfer'))->assertRedirect(route('top.index'));
    }

    /**
     * 「振込み」：PaymentIntent の状態と金額を確認して予約データと振込先を保存し、サンクスページへ進む。表示した振込先・金額と同じ値（AC-11, S-15）
     */
    #[Test]
    public function 振込みで予約データと振込先を保存しサンクスページへ進む(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mockBankIntent('pi_test_bank', 'requires_action', 2980);

        $this->actingAs($user)
            ->post(route('shop.reservation.bank-transfer.confirm'))
            ->assertRedirect(route('shop.reservation.thanks'))
            ->assertSessionHas(ReservationService::THANKS_FLASH_KEY, true);

        $this->get(route('shop.reservation.thanks'))
            ->assertOk()
            ->assertViewIs('shop.reservation_thanks')
            ->assertSee('ご予約ありがとうございます。');

        $reservation = Reservation::sole();
        $this->assertSame('customer_balance', $reservation->payment_method);
        $this->assertSame(1530, $reservation->shipping_fee);
        $this->assertSame(2980, $reservation->total_price);
        $this->assertSame(1450, $reservation->subtotal);

        $transfer = Reservation_bank_transfer::sole();
        $this->assertSame($reservation->id, $transfer->reservation_id);
        $this->assertSame('pi_test_bank', $transfer->stripe_payment_intent_id);
        $this->assertSame('いろは銀行', $transfer->bank_name);
        $this->assertSame('中央支店', $transfer->branch_name);
        $this->assertSame('futsu', $transfer->account_type);
        $this->assertSame('1234567', $transfer->account_number);
        $this->assertSame('ストライプジャパン', $transfer->account_holder_name);
        $this->assertSame(2980, $transfer->amount);
        $this->assertNull(session('pending_reservation'));
    }

    /**
     * 「振込み」は POST だけ。GET では実行できない
     */
    #[Test]
    public function 振込みは_ge_tでは実行できない(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->gatewayNeverCalled();

        $this->actingAs($user)->get('/reservation/bank-transfer/confirm')->assertStatus(405);
        $this->get('/reservation/bank-transfer/cancel')->assertStatus(405);

        $this->assertSame(0, Reservation::count());
    }

    /**
     * サンクスページは保存した直後の1回だけ表示し、再読み込み・直接アクセスはトップページへ戻す（AC-12, S-19）
     */
    #[Test]
    public function サンクスページは直後の1回だけ表示する(): void
    {
        [$user, $item] = $this->scenario();
        $this->actingAs($user)->get(route('shop.reservation.thanks'))->assertRedirect(route('top.index'));

        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mockBankIntent('pi_test_bank', 'requires_action', 2980);
        $this->post(route('shop.reservation.bank-transfer.confirm'))->assertRedirect(route('shop.reservation.thanks'));

        // フラッシュは次のリクエストまで。1回目は表示、再読み込みはトップへ
        $this->get(route('shop.reservation.thanks'))->assertOk();
        $this->get(route('shop.reservation.thanks'))->assertRedirect(route('top.index'));
    }

    /**
     * 購入のサンクスの合図では予約のサンクスページは表示されない
     */
    #[Test]
    public function 購入のサンクスの合図では予約のサンクスは表示されない(): void
    {
        [$user] = $this->scenario();

        $this->actingAs($user)
            ->withSession(['purchase_completed' => true])
            ->get(route('shop.reservation.thanks'))
            ->assertRedirect(route('top.index'));
    }

    /**
     * 「振込み」を2回押しても予約は1件だけ（2回目は保存待ちの予約が無いのでトップページへ）。戻って押し直しても作られない（S-19, S-20）
     */
    #[Test]
    public function 振込みを二回押しても予約は一件だけ(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mockBankIntent('pi_test_bank', 'requires_action', 2980);

        $this->actingAs($user)->post(route('shop.reservation.bank-transfer.confirm'))->assertRedirect(route('shop.reservation.thanks'));
        $this->post(route('shop.reservation.bank-transfer.confirm'))->assertRedirect(route('top.index'));

        $this->assertSame(1, Reservation::count());
        $this->assertSame(1, Reservation_bank_transfer::count());
    }

    /**
     * 金額が合わない・状態が想定外の PaymentIntent は保存せず、予約ページへ戻す
     */
    #[Test]
    public function 金額が違う_payment_intentは保存しない(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mockBankIntent('pi_test_bank', 'requires_action', 100);

        $this->actingAs($user)
            ->post(route('shop.reservation.bank-transfer.confirm'))
            ->assertRedirect(route('shop.reservation.index', $item))
            ->assertSessionHasErrors('payment')
            ->assertSessionHas(ReservationService::RESTORE_FLASH_KEY, true);

        $this->assertSame(0, Reservation::count());
    }

    /**
     * Stripe に問い合わせられなかったときは保存せず、案内ページに戻してエラーを表示する（保存待ちの予約は残す）
     */
    #[Test]
    public function 振込みで確認できなければ案内ページに戻る(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveBankTransferIntent')->once()->andThrow(new PaymentGatewayException('通信エラー'));
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.bank-transfer.confirm'))
            ->assertRedirect(route('shop.reservation.bank-transfer'))
            ->assertSessionHasErrors('payment');

        $this->assertSame(0, Reservation::count());
        $this->assertNotNull(session('pending_reservation'));
    }

    /**
     * 保存待ちの予約が無いときの「振込み」は、何も保存せずトップページへ戻す
     */
    #[Test]
    public function 保存待ちが無ければ振込みはトップページへ戻る(): void
    {
        [$user] = $this->scenario();
        $this->gatewayNeverCalled();

        $this->actingAs($user)->post(route('shop.reservation.bank-transfer.confirm'))->assertRedirect(route('top.index'));

        $this->assertSame(0, Reservation::count());
    }

    /**
     * 「戻る」：PaymentIntent をキャンセルして予約ページへ戻り、入力が残る。予約データは作らない（AC-11, S-16）
     */
    #[Test]
    public function 戻るでキャンセルして予約ページへ戻る(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_test_bank');
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.bank-transfer.cancel'))
            ->assertRedirect(route('shop.reservation.index', $item))
            ->assertSessionHas(ReservationService::RESTORE_FLASH_KEY, true);

        $this->assertSame(0, Reservation::count());
        $pending = session('pending_reservation');
        $this->assertNull($pending['payment_intent_id']);
        $this->assertNull($pending['bank_transfer']);
        $this->assertSame('山田花子', $pending['delivery_name']);

        // 戻った直後の予約ページには、入力した内容が残っている
        $this->get(route('shop.reservation.index', $item))
            ->assertSee('value="山田花子"', false)
            ->assertSee('value="08011112222"', false)
            ->assertSee('value="customer_balance"', false);
    }

    /**
     * 「戻る」でキャンセルに失敗し、確認したところ入金済みだったときは、予約データを保存してサンクスページへ進む
     */
    #[Test]
    public function 戻るでキャンセルできず入金済みなら保存する(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntent')->once()->andThrow(new PaymentGatewayException('入金済み'));
            $mock->shouldReceive('retrieveBankTransferIntent')
                ->twice()
                ->with('pi_test_bank')
                ->andReturn(new BankTransferIntentData('pi_test_bank', 'succeeded', 2980, null));
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.bank-transfer.cancel'))
            ->assertRedirect(route('shop.reservation.thanks'));

        $this->assertSame(1, Reservation::count());
    }

    /**
     * 「戻る」でキャンセルも状態確認もできないときは、案内ページに戻してエラーを表示する（保存待ちの予約は変えない）
     */
    #[Test]
    public function 戻るで取り消せなければ案内ページに戻る(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntent')->once()->andThrow(new PaymentGatewayException('通信エラー'));
            $mock->shouldReceive('retrieveBankTransferIntent')->once()->andThrow(new PaymentGatewayException('通信エラー'));
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.bank-transfer.cancel'))
            ->assertRedirect(route('shop.reservation.bank-transfer'))
            ->assertSessionHasErrors('payment');

        $this->assertSame('pi_test_bank', session('pending_reservation')['payment_intent_id']);
        $this->assertSame(0, Reservation::count());
    }

    /**
     * 保存待ちの予約が無いときの「戻る」は、トップページへ戻す
     */
    #[Test]
    public function 保存待ちが無ければ戻るはトップページへ戻る(): void
    {
        [$user] = $this->scenario();
        $this->gatewayNeverCalled();

        $this->actingAs($user)->post(route('shop.reservation.bank-transfer.cancel'))->assertRedirect(route('top.index'));
    }

    /**
     * 購入の保存待ちの注文だけがあるとき、予約の「振込み」「戻る」は購入の注文に触れない
     */
    #[Test]
    public function 購入の保存待ちの注文は予約の振込みと戻るに影響されない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_purchase');
        $this->gatewayNeverCalled();

        $this->actingAs($user)->post(route('shop.reservation.bank-transfer.confirm'))->assertRedirect(route('top.index'));
        $this->post(route('shop.reservation.bank-transfer.cancel'))->assertRedirect(route('top.index'));

        $this->assertSame('pi_purchase', session('pending_order')['payment_intent_id']);
        $this->assertSame(0, Reservation::count());
    }
}
