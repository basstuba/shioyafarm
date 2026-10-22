<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Item;
use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use App\Models\User;
use App\Services\Payment\BankTransferInstructions;
use App\Services\Payment\BankTransferIntentData;
use App\Services\Payment\CheckoutSessionData;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;
use Throwable;

/**
 * 新しい「注文を確定する」で前の保存待ちの注文を上書きするときの、前の決済の片づけ（Session の失効・PaymentIntent のキャンセル）のテスト
 */
class PurchaseRetirePreviousOrderTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 代金引換で「注文を確定する」（前の注文を片づけたあとに、Stripe を通さずその場で保存してサンクスページへ移動する）
     *
     * @param  array<string, Item>  $items
     */
    private function orderByCod(User $user, array $items): TestResponse
    {
        return $this->actingAs($user)->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['payment_method' => 'cod']));
    }

    /**
     * 前のカードの Checkout Session がまだ開いているときは、新しい注文（代金引換）で上書きする前に失効させる
     */
    #[Test]
    public function 前のカードのsessionを失効させてから上書きする(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::Card, 'cs_test_old');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_test_old')->andReturn(new CheckoutSessionData('cs_test_old', null, 'open', 'unpaid'));
            $mock->shouldReceive('expireCheckoutSession')->once()->with('cs_test_old')->andReturn(new CheckoutSessionData('cs_test_old', null, 'expired', 'unpaid'));
        });

        $this->orderByCod($user, $items)->assertRedirect(route('shop.checkout.thanks'));

        // 前のカードの注文は保存されず、新しい注文（代金引換）だけが保存されている
        $this->assertSame('cod', Purchase_history::sole()->payment_method);
        $this->assertNull(session('pending_order'));
    }

    /**
     * 前の注文を保存しても、カートから消えるのは前の注文で購入した商品の数量だけ。購入していない商品はカートに残る
     */
    #[Test]
    public function 前の注文の保存でカートの他の商品は消えない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $other = $this->makeItem('かぼちゃ', 3, 500);
        session(['cart' => [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1, $other->id => 2]]);
        $this->putPending($user, $items, PaymentMethod::Card, 'cs_test_paid');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->twice()->with('cs_test_paid')->andReturn(new CheckoutSessionData('cs_test_paid', null, 'complete', 'paid'));
        });

        // 新しい注文は、商品詳細から購入するかぼちゃ（カートの商品ではない）
        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$other->id => 1], ['source' => 'item', 'payment_method' => 'cod']))
            ->assertRedirect(route('shop.checkout.thanks'));

        $this->assertSame(2, Purchase_history::count());
        $this->assertSame([$other->id => 2], session('cart'));
    }

    /**
     * 新しい注文がカード・銀行振込でも、前のカードの Session を失効させる
     */
    #[Test]
    public function 新しいカードや銀行振込でも前のsessionを失効させる(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $data = $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1]);

        foreach (['card', 'customer_balance'] as $method) {
            $this->putPending($user, $items, PaymentMethod::Card, 'cs_test_old');
            $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($method): void {
                $this->stubCustomer($mock);
                $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_test_old')->andReturn(new CheckoutSessionData('cs_test_old', null, 'open', 'unpaid'));
                $mock->shouldReceive('expireCheckoutSession')->once()->with('cs_test_old')->andReturn(new CheckoutSessionData('cs_test_old', null, 'expired', 'unpaid'));
                if ($method === 'card') {
                    $mock->shouldReceive('createCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_test_new', 'https://checkout.stripe.test/new', 'open', 'unpaid'));
                } else {
                    $mock->shouldReceive('createBankTransferIntent')->once()->andReturn(new BankTransferIntentData('pi_new', 'requires_action', 3723, new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 3723)));
                }
            });

            $this->actingAs($user)->post(route('shop.checkout.payment'), [...$data, 'payment_method' => $method, 'order_token' => $this->newOrderToken($user)])->assertRedirect();
        }

        $this->assertSame('pi_new', session('pending_order')['payment_intent_id']);
    }

    /**
     * 前の Session の失効に失敗しても（ログだけ残し）、確認し直して支払い済みでなければ、そのまま新しい注文を進める
     */
    #[Test]
    public function 失効に失敗しても支払い済みでなければ進める(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::Card, 'cs_test_old');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->twice()->with('cs_test_old')->andReturn(new CheckoutSessionData('cs_test_old', null, 'open', 'unpaid'));
            $mock->shouldReceive('expireCheckoutSession')->once()->andThrow(new PaymentGatewayException('失効できません'));
        });

        $this->orderByCod($user, $items)->assertRedirect(route('shop.checkout.thanks'));

        $this->assertSame('cod', Purchase_history::sole()->payment_method);
        $this->assertNull(session('pending_order'));
    }

    /**
     * 前のカードの Session がすでに支払い済みだったときは、新しい注文で上書きする前に、その注文の購入データを保存する
     * （支払いを受けた注文を失わない）。そのうえで新しい注文を進める
     */
    #[Test]
    public function 前のカードが支払い済みならその注文を保存してから進める(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::Card, 'cs_test_paid');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            // 片づけの確認と、保存のための確認
            $mock->shouldReceive('retrieveCheckoutSession')->twice()->with('cs_test_paid')->andReturn(new CheckoutSessionData('cs_test_paid', null, 'complete', 'paid'));
            $mock->shouldNotReceive('expireCheckoutSession');
        });
        $quantities = [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1];

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $quantities, ['payment_method' => 'cod']))
            ->assertRedirect(route('shop.checkout.thanks'));

        // 前の注文（カード・山田花子宛て）が保存されている
        $history = Purchase_history::where('payment_method', 'card')->sole();
        $this->assertSame('山田花子', $history->delivery_name);
        $this->assertSame(3723, $history->total_price);
        // 新しい注文（代金引換）も保存されている
        $this->assertSame(4053, Purchase_history::where('payment_method', 'cod')->sole()->total_price);
        $this->assertNull(session('pending_order'));
    }

    /**
     * 失効に失敗したが、確認し直したら支払い済みだった（失効しようとした瞬間に支払われた）ときも、その注文を保存する
     */
    #[Test]
    public function 失効の失敗後に支払い済みと分かればその注文を保存する(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::Card, 'cs_test_race');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->times(3)->with('cs_test_race')->andReturn(
                new CheckoutSessionData('cs_test_race', null, 'open', 'unpaid'),
                new CheckoutSessionData('cs_test_race', null, 'complete', 'paid'),
                new CheckoutSessionData('cs_test_race', null, 'complete', 'paid'),
            );
            $mock->shouldReceive('expireCheckoutSession')->once()->andThrow(new PaymentGatewayException('Only open sessions can be expired'));
        });

        $this->orderByCod($user, $items)->assertRedirect(route('shop.checkout.thanks'));

        // 前のカードの注文と、新しい代金引換の注文
        $this->assertSame(2, Purchase_history::count());
        $this->assertSame(1, Purchase_history::where('payment_method', 'card')->count());
    }

    /**
     * 前の Session を Stripe に問い合わせられなかったとき（通信エラー）は、支払い済みかどうかを確認できないため、
     * 新しい注文（代金引換・カード・銀行振込）を進めずにエラーにする。前の注文は残す
     */
    #[Test]
    public function 前のsessionを確認できなければ新しい注文は進めない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $data = $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1]);

        foreach (['cod', 'card', 'customer_balance'] as $method) {
            $this->putPending($user, $items, PaymentMethod::Card, 'cs_test_old');
            $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
                $this->stubCustomer($mock);
                $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_test_old')->andThrow(new PaymentGatewayException('通信エラー'));
                $mock->shouldNotReceive('expireCheckoutSession');
                $mock->shouldNotReceive('createCheckoutSession');
                $mock->shouldNotReceive('createBankTransferIntent');
            });

            $this->actingAs($user)
                ->from(route('shop.checkout.index'))
                ->post(route('shop.checkout.payment'), [...$data, 'payment_method' => $method, 'order_token' => $this->newOrderToken($user)])
                ->assertRedirect(route('shop.checkout.index'))
                ->assertSessionHasErrors(['payment' => '前回のお支払いの確認ができませんでした。しばらく時間をおいてから、もう一度お試しください']);

            // 前の注文は残り、新しい注文は保存されない
            $this->assertSame('cs_test_old', session('pending_order')['checkout_session_id']);
            $this->assertSame(0, Purchase_history::count());
        }
    }

    /**
     * 前の注文が支払い済みなのに保存できなかった（ロックが取れない）ときは、新しい注文を進めずにエラーにする（前の注文を残す）
     */
    #[Test]
    public function 前の注文を保存できなければ新しい注文は進めない(): void
    {
        config(['purchase.complete_lock_wait' => 0]);
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::Card, 'cs_test_paid');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_test_paid', null, 'complete', 'paid'));
        });
        $lock = Cache::lock('purchase-complete:'.$user->id, 30);
        $lock->get();

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['payment_method' => 'cod']))
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors('payment');

        $this->assertSame('card', session('pending_order')['payment_method']);
        $this->assertSame('cs_test_paid', session('pending_order')['checkout_session_id']);
        $lock->release();
    }

    /**
     * 前の銀行振込のキャンセルに失敗し、PaymentIntent が入金済み・入金処理中だったときは、その注文の購入データを保存してから進める
     */
    #[Test]
    public function 前の銀行振込が入金済みならその注文を保存してから進める(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_paid');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_test_paid')->andThrow(new PaymentGatewayException('入金済み'));
            // キャンセル失敗後の確認と、保存のための確認
            $mock->shouldReceive('retrieveBankTransferIntent')->twice()->with('pi_test_paid')->andReturn(new BankTransferIntentData('pi_test_paid', 'succeeded', 3723, null));
        });

        $this->orderByCod($user, $items)->assertRedirect(route('shop.checkout.thanks'));

        // 前の銀行振込の注文と、新しい代金引換の注文
        $this->assertSame('customer_balance', Purchase_history::where('payment_method', 'customer_balance')->sole()->payment_method);
        $this->assertSame(1, Purchase_bank_transfer::count());
        $this->assertSame(1, Purchase_history::where('payment_method', 'cod')->count());
        $this->assertNull(session('pending_order'));
    }

    /**
     * 前の銀行振込のキャンセルに失敗し、状態も確認できない・まだ振込待ちのときは、新しい注文を進めず、
     * 前の注文（振込先つき）を残したままエラーにする
     */
    #[Test]
    public function 前の銀行振込をキャンセルできなければ新しい注文は進めない(): void
    {
        [$user, $items] = $this->makeCartScenario();

        foreach ([new PaymentGatewayException('通信エラー'), new BankTransferIntentData('pi_test_old', 'requires_action', 3723, new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 3723))] as $retrieveResult) {
            $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_old');
            $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($retrieveResult): void {
                $mock->shouldReceive('cancelPaymentIntent')->once()->andThrow(new PaymentGatewayException('通信エラー'));
                $expectation = $mock->shouldReceive('retrieveBankTransferIntent')->once();
                $retrieveResult instanceof Throwable ? $expectation->andThrow($retrieveResult) : $expectation->andReturn($retrieveResult);
                $mock->shouldNotReceive('createBankTransferIntent');
                $mock->shouldNotReceive('createCheckoutSession');
            });

            $this->actingAs($user)
                ->from(route('shop.checkout.index'))
                ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['payment_method' => 'cod']))
                ->assertRedirect(route('shop.checkout.index'))
                ->assertSessionHasErrors(['payment' => '前回の銀行振込のお手続きを取り消せませんでした。しばらく時間をおいてから、もう一度お試しください']);

            // 前の注文（振込先つき）は外さない
            $this->assertSame('pi_test_old', session('pending_order')['payment_intent_id']);
            $this->assertNotNull(session('pending_order')['bank_transfer']);
        }

        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * 前の銀行振込のキャンセルに失敗しても、PaymentIntent がすでにキャンセル済みなら、新しい注文を進める
     */
    #[Test]
    public function 前の銀行振込がキャンセル済みなら進める(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_old');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntent')->once()->andThrow(new PaymentGatewayException('すでにキャンセル済み'));
            $mock->shouldReceive('retrieveBankTransferIntent')->once()->andReturn(new BankTransferIntentData('pi_test_old', 'canceled', 3723, null));
        });

        $this->orderByCod($user, $items)->assertRedirect(route('shop.checkout.thanks'));

        // 前の銀行振込の注文は保存されず、新しい注文（代金引換）だけが保存されている
        $this->assertSame('cod', Purchase_history::sole()->payment_method);
        $this->assertSame(0, Purchase_bank_transfer::count());
        $this->assertNull(session('pending_order'));
    }
}
