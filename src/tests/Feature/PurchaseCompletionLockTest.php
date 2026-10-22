<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Purchase_history;
use App\Services\Payment\BankTransferInstructions;
use App\Services\Payment\BankTransferIntentData;
use App\Services\Payment\CheckoutSessionData;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * 購入完了処理の排他（会員ごとのロック）と、同時リクエストでの二重保存の防止のテスト
 */
class PurchaseCompletionLockTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 代金引換の「注文を確定する」は、その場で購入データを保存し、保存待ちの注文を残さず、購入したカートの商品を減らす
     */
    #[Test]
    public function 代金引換の注文を確定すると保存され保存待ちは残らない(): void
    {
        [$user, $items] = $this->makeCartScenario();

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['payment_method' => 'cod']))
            ->assertRedirect(route('shop.checkout.thanks'));

        $this->assertSame(1, Purchase_history::count());
        $this->assertNull(session('pending_order'));
        $this->assertEmpty(session('cart'));
    }

    /**
     * カードの同時リクエスト：2つ目は Stripe に問い合わせず、保存済みとして扱う
     */
    #[Test]
    public function カードの同時リクエストでも二重に保存されない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $order = $this->putPending($user, $items, PaymentMethod::Card, 'cs_test_paid');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_test_paid')
                ->andReturn(new CheckoutSessionData('cs_test_paid', null, 'complete', 'paid'));
        });

        $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => 'cs_test_paid']))->assertOk();

        session(['pending_order' => $order->toArray()]);
        $this->get(route('shop.checkout.success', ['session_id' => 'cs_test_paid']))->assertOk()->assertViewIs('shop.purchase_thanks');

        $this->assertSame(1, Purchase_history::count());
    }

    /**
     * 別の注文（別の注文ID）は、保存済みの記録に引っかからず、それぞれ保存される
     */
    #[Test]
    public function 別の注文は二重とみなされない(): void
    {
        [$user, $items] = $this->makeCartScenario();

        $data = $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['payment_method' => 'cod']);

        $this->actingAs($user)->post(route('shop.checkout.payment'), $data)->assertRedirect(route('shop.checkout.thanks'));
        // 別の画面（別の注文トークン）からの注文なので、別の注文として保存される
        $this->post(route('shop.checkout.payment'), [...$data, 'order_token' => $this->newOrderToken($user)])->assertRedirect(route('shop.checkout.thanks'));

        $this->assertSame(2, Purchase_history::count());
    }

    /**
     * ロックを取れない（別のリクエストが処理中のまま待っても終わらない）ときは、500にせず、保存もせずに「処理中」を案内する。
     * 保存待ちの注文は残す
     */
    #[Test]
    public function ロックを取れないときは処理中を案内する(): void
    {
        config(['purchase.complete_lock_wait' => 0]);
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::Card, 'cs_test_paid');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_test_paid')
                ->andReturn(new CheckoutSessionData('cs_test_paid', null, 'complete', 'paid'));
        });
        $lock = Cache::lock('purchase-complete:'.$user->id, 30);
        $this->assertTrue($lock->get());

        $this->actingAs($user)
            ->get(route('shop.checkout.success', ['session_id' => 'cs_test_paid']))
            ->assertStatus(503)
            ->assertViewIs('shop.payment_pending')
            ->assertSee('ただいまご注文を処理しています')
            ->assertSee('再読み込み');

        $this->assertSame(0, Purchase_history::count());
        $this->assertNotNull(session('pending_order'));

        // ロックが解放されたあとは保存できる
        $lock->release();
        $this->get(route('shop.checkout.success', ['session_id' => 'cs_test_paid']))->assertOk();
        $this->assertSame(1, Purchase_history::count());
    }

    /**
     * 代金引換の「注文を確定する」で保存のロックを取れないときは、保存せず、保存待ちの注文も残さず、購入ページへ戻して「処理中」を案内する
     */
    #[Test]
    public function 代金引換で保存のロックを取れないときは購入ページへ戻る(): void
    {
        config(['purchase.complete_lock_wait' => 0]);
        [$user, $items] = $this->makeCartScenario();
        $lock = Cache::lock('purchase-complete:'.$user->id, 30);
        $this->assertTrue($lock->get());

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2], ['payment_method' => 'cod']))
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors('payment');

        $this->assertSame(0, Purchase_history::count());
        $this->assertNull(session('pending_order'));
    }

    /**
     * 「注文を確定する」の処理中（会員ごとのロックが取られている間）に届いた再送信は、待っても取れなければ、何も作らず、
     * 購入ページへ戻して「処理中」を案内する（注文トークンは使わないので、ロックが解放されたあとに同じ画面から確定できる）
     */
    #[Test]
    public function 注文確定の処理中の再送信は処理中を案内する(): void
    {
        config(['purchase.begin_lock_wait' => 0]);
        [$user, $items] = $this->makeCartScenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('ensureCustomer');
            $mock->shouldNotReceive('createCheckoutSession');
            $mock->shouldNotReceive('createBankTransferIntent');
        });
        $lock = Cache::lock('purchase-begin:'.$user->id, 30);
        $this->assertTrue($lock->get());
        $data = $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1]);

        foreach (['card', 'customer_balance', 'cod'] as $method) {
            $this->actingAs($user)
                ->from(route('shop.checkout.index'))
                ->post(route('shop.checkout.payment'), [...$data, 'payment_method' => $method])
                ->assertRedirect(route('shop.checkout.index'))
                ->assertSessionHasErrors(['payment' => 'ただいまご注文を処理しています。しばらく待ってから、もう一度お試しください']);
        }

        $this->assertSame(0, Purchase_history::count());
        $this->assertNull(session('pending_order'));

        // ロックを解放すれば、もう一度確定できる
        $lock->release();
        $this->post(route('shop.checkout.payment'), [...$data, 'payment_method' => 'cod'])->assertRedirect(route('shop.checkout.thanks'));
        $this->assertSame(1, Purchase_history::count());
    }

    /**
     * 「注文を確定する」の処理のあとは（Stripe のエラーで失敗したときも）ロックが解放されている
     */
    #[Test]
    public function 注文確定のあとはロックが解放される(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')->once()->andThrow(new PaymentGatewayException('通信エラー'));
        });

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2]))
            ->assertSessionHasErrors('payment');

        $lock = Cache::lock('purchase-begin:'.$user->id, 30);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    /**
     * 銀行振込の「振込み」でロックを取れないときは、案内ページへ戻して「もう一度押してください」と案内する（500にしない）
     */
    #[Test]
    public function 銀行振込でロックを取れないときは案内ページへ戻る(): void
    {
        config(['purchase.complete_lock_wait' => 0]);
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveBankTransferIntent')->once()->andReturn(new BankTransferIntentData('pi_test_bank', 'requires_action', 3723, new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 3723)));
        });
        $lock = Cache::lock('purchase-complete:'.$user->id, 30);
        $lock->get();

        $this->actingAs($user)
            ->post(route('shop.checkout.bank-transfer.confirm'))
            ->assertRedirect(route('shop.checkout.bank-transfer'))
            ->assertSessionHasErrors('payment');
        $this->get(route('shop.checkout.bank-transfer'))->assertSee('ただいまご注文を処理しています');
        $this->assertSame(0, Purchase_history::count());

        $lock->release();
        $this->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('shop.checkout.thanks'));
        $this->assertSame(1, Purchase_history::count());
    }

    /**
     * 処理のあとはロックが解放されている（検証で保存しなかった場合も）
     */
    #[Test]
    public function 処理のあとはロックが解放される(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::Card, 'cs_test_open');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_test_open', null, 'open', 'unpaid'));
        });

        $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => 'cs_test_open']))->assertRedirect(route('shop.checkout.index'));

        $lock = Cache::lock('purchase-complete:'.$user->id, 30);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    /**
     * 完了の判定（item 6）：status が complete でも payment_status が paid でなければ保存しない
     */
    #[Test]
    public function completeでも支払い済みでなければ保存されない(): void
    {
        [$user, $items] = $this->makeCartScenario();

        foreach (['unpaid', 'no_payment_required', null] as $paymentStatus) {
            $this->putPending($user, $items, PaymentMethod::Card, 'cs_test_unpaid');
            $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($paymentStatus): void {
                $mock->shouldReceive('retrieveCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_test_unpaid', null, 'complete', $paymentStatus));
            });

            $this->actingAs($user)
                ->get(route('shop.checkout.success', ['session_id' => 'cs_test_unpaid']))
                ->assertRedirect(route('shop.checkout.index'))
                ->assertSessionHasErrors('payment');

            $this->assertSame(0, Purchase_history::count());
            $this->assertNotNull(session('pending_order'));
        }
    }
}
