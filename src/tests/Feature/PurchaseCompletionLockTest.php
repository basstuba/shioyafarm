<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Purchase_history;
use App\Services\Payment\BankTransferInstructions;
use App\Services\Payment\BankTransferIntentData;
use App\Services\Payment\CheckoutSessionData;
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
     * 同時に届いた2つ目のリクエスト（セッションに保存待ちの注文が残っている）は、代金引換でも二重に保存されず、500にもならない
     */
    #[Test]
    public function 代金引換の同時リクエストでも二重に保存されない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $order = $this->putPending($user, $items, PaymentMethod::Cod);
        $cart = session('cart');

        $this->actingAs($user)->get(route('shop.checkout.success'))->assertOk()->assertViewIs('shop.purchase_thanks');

        session(['pending_order' => $order->toArray(), 'cart' => $cart]);
        $this->get(route('shop.checkout.success'))->assertOk()->assertViewIs('shop.purchase_thanks');

        $this->assertSame(1, Purchase_history::count());
        $this->assertNull(session('pending_order'));
        $this->assertNull(session('cart'));
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

        $this->putPending($user, $items, PaymentMethod::Cod);
        $this->actingAs($user)->get(route('shop.checkout.success'))->assertOk();

        $this->putPending($user, $items, PaymentMethod::Cod);
        $this->get(route('shop.checkout.success'))->assertOk();

        $this->assertSame(2, Purchase_history::count());
    }

    /**
     * ロックを取れない（別のリクエストが処理中のまま待っても終わらない）ときは、500にせず、保存もせずに「処理中」を案内する。
     * 保存待ちの注文は残す（カード・代金引換）
     */
    #[Test]
    public function ロックを取れないときは処理中を案内する(): void
    {
        config(['purchase.complete_lock_wait' => 0]);
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::Cod);
        $lock = Cache::lock('purchase-complete:'.$user->id, 30);
        $this->assertTrue($lock->get());

        $this->actingAs($user)
            ->get(route('shop.checkout.success'))
            ->assertStatus(503)
            ->assertViewIs('shop.payment_pending')
            ->assertSee('ただいまご注文を処理しています')
            ->assertSee('再読み込み');

        $this->assertSame(0, Purchase_history::count());
        $this->assertNotNull(session('pending_order'));

        // ロックが解放されたあとは保存できる
        $lock->release();
        $this->get(route('shop.checkout.success'))->assertOk();
        $this->assertSame(1, Purchase_history::count());
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
