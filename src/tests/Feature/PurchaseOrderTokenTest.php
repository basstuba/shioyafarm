<?php

namespace Tests\Feature;

use App\Enums\OrderTokenStatus;
use App\Enums\PaymentMethod;
use App\Livewire\PurchaseForm;
use App\Models\Item;
use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use App\Repositories\Interfaces\PurchaseHistoryRepositoryInterface;
use App\Services\CheckoutService;
use App\Services\OrderTokenStore;
use App\Services\Payment\BankTransferInstructions;
use App\Services\Payment\BankTransferIntentData;
use App\Services\Payment\CheckoutSessionData;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\PurchaseService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * 注文トークン（1回限りのトークン）による二重送信の防止と、確定処理のロック・保存の失敗時の扱いのテスト
 */
class PurchaseOrderTokenTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;

    /** 購入ページのHTMLから注文トークンを取り出す */
    private function tokenFrom(string $html): string
    {
        $this->assertSame(1, preg_match('/name="order_token" value="([^"]+)"/', $html, $matches));

        return $matches[1];
    }

    /**
     * S-2 のカートの数量（商品ID => 数量）
     *
     * @param  array<string, Item>  $items
     * @return array<int, int>
     */
    private function cartQuantities(array $items): array
    {
        return [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1];
    }

    private function bankIntent(string $id, string $status, int $amount = 3723, bool $withInstructions = true): BankTransferIntentData
    {
        return new BankTransferIntentData(
            $id,
            $status,
            $amount,
            $withInstructions ? new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', $amount) : null,
        );
    }

    /**
     * 購入ページを開くと注文トークンが発行され、フォームの hidden に入る。開くたびに別のトークンになり、どれも使える（複数タブ）
     */
    #[Test]
    public function 購入ページを開くたびに注文トークンが発行される(): void
    {
        [$user] = $this->makeCartScenario();

        $first = $this->tokenFrom($this->actingAs($user)->get(route('shop.checkout.index'))->assertOk()->getContent());
        $second = $this->tokenFrom($this->get(route('shop.checkout.index'))->getContent());

        $this->assertNotSame($first, $second);
        $store = app(OrderTokenStore::class);
        $this->assertSame(OrderTokenStatus::Issued, $store->status($user->id, $first));
        $this->assertSame(OrderTokenStatus::Issued, $store->status($user->id, $second));
    }

    /**
     * 再描画（数量・名前の変更）でトークンは変わらず、クライアントからは書き換えられない
     */
    #[Test]
    public function 注文トークンは再描画で変わらず書き換えられない(): void
    {
        [$user, $items] = $this->makeCartScenario();

        $component = Livewire::actingAs($user)->test(PurchaseForm::class);
        $token = $component->get('orderToken');
        $this->assertNotSame('', $token);
        $component->assertSeeHtml('name="order_token" value="'.$token.'"');

        $component->call('updateQuantity', $items['potato']->id, 3)
            ->set('deliveryName', '山田花子')
            ->set('paymentMethod', 'cod')
            ->assertSet('orderToken', $token)
            ->assertSeeHtml('name="order_token" value="'.$token.'"');

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('orderToken', 'forged');
    }

    /**
     * 代金引換を同じ画面から2回送信（同じトークン）しても、注文は1件だけ。2回目はサンクスページへ送り、カートも二重に減らさない
     */
    #[Test]
    public function 代金引換の二重送信で注文は1件だけになる(): void
    {
        [$user, $items] = $this->makeCartScenario();
        session(['cart' => [$items['potato']->id => 5, $items['onion']->id => 1]]);
        $data = $this->paymentData($user, [$items['potato']->id => 2], ['payment_method' => 'cod']);

        $this->actingAs($user)->post(route('shop.checkout.payment'), $data)
            ->assertRedirect(route('shop.checkout.thanks'))
            ->assertSessionHas(PurchaseService::THANKS_FLASH_KEY, true);
        $this->post(route('shop.checkout.payment'), $data)
            ->assertRedirect(route('shop.checkout.thanks'))
            ->assertSessionHas(PurchaseService::THANKS_FLASH_KEY, true)
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Purchase_history::count());
        $this->assertSame(OrderTokenStatus::Completed, app(OrderTokenStore::class)->status($user->id, $data['order_token']));
        // じゃがいも5個のうち2個を購入：3個が残り、2回目では減らない
        $this->assertSame([$items['potato']->id => 3, $items['onion']->id => 1], session('cart'));
        $this->get(route('shop.checkout.thanks'))->assertOk();
    }

    /**
     * 使用済みで保存していないトークン（カードの決済ページへ進んだ送信）では、注文も決済ページも作らず、購入ページへ戻して画面が古いことを案内する。
     * 前の保存待ちの注文は変えない
     */
    #[Test]
    public function 保存していない使用済みのトークンでは注文を作らず購入ページへ戻す(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_test_1', 'https://checkout.stripe.test/first', 'open', 'unpaid'));
            $mock->shouldNotReceive('retrieveCheckoutSession');
            $mock->shouldNotReceive('createBankTransferIntent');
        });
        $data = $this->paymentData($user, [$items['potato']->id => 2]);

        $this->actingAs($user)->post(route('shop.checkout.payment'), $data)->assertRedirect('https://checkout.stripe.test/first');

        // 同じ画面（ブラウザの「戻る」で戻ったなど）からの再送信
        $this->post(route('shop.checkout.payment'), [...$data, 'payment_method' => 'cod'])
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors(['order_token' => CheckoutService::STALE_MESSAGE]);

        $this->assertSame(0, Purchase_history::count());
        $this->assertSame('cs_test_1', session('pending_order')['checkout_session_id']);
        $this->assertSame('card', session('pending_order')['payment_method']);

        // 購入ページを開き直すと新しいトークンになり、メッセージと入力内容が表示される
        $page = $this->get(route('shop.checkout.index'))->assertOk()->assertSee(CheckoutService::STALE_MESSAGE);
        $newToken = $this->tokenFrom($page->getContent());
        $this->assertNotSame($data['order_token'], $newToken);
        $this->assertSame(OrderTokenStatus::Issued, app(OrderTokenStore::class)->status($user->id, $newToken));
    }

    /**
     * 商品詳細から購入する画面のトークンが古いときは、その商品の購入ページへ戻す
     */
    #[Test]
    public function 商品詳細からの購入で古いトークンならその商品の購入ページへ戻る(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $data = $this->paymentData($user, [$items['carrot']->id => 1], ['source' => 'item', 'payment_method' => 'cod']);

        $this->actingAs($user)->post(route('shop.checkout.payment'), $data)->assertRedirect(route('shop.checkout.thanks'));
        $this->assertSame(1, Purchase_history::count());

        // 保存済みのトークン：サンクスページへ
        $this->post(route('shop.checkout.payment'), $data)->assertRedirect(route('shop.checkout.thanks'));

        // 発行していないトークン：購入ページへ
        $this->post(route('shop.checkout.payment'), [...$data, 'order_token' => 'not-issued'])
            ->assertRedirect(route('shop.checkout.index', $items['carrot']->id))
            ->assertSessionHasErrors(['order_token' => CheckoutService::STALE_MESSAGE]);

        $this->assertSame(1, Purchase_history::count());
    }

    /**
     * 他の会員のトークンは使えない
     */
    #[Test]
    public function 他の会員のトークンでは注文できない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $other = $this->makeUser();
        $data = $this->paymentData($user, [$items['potato']->id => 2], ['payment_method' => 'cod', 'order_token' => $this->newOrderToken($other)]);

        $this->actingAs($user)->post(route('shop.checkout.payment'), $data)
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors(['order_token' => CheckoutService::STALE_MESSAGE]);

        $this->assertSame(0, Purchase_history::count());
        $this->assertSame(OrderTokenStatus::Issued, app(OrderTokenStore::class)->status($other->id, $data['order_token']));
    }

    /**
     * トークンがない・長すぎる送信は、バリデーションエラーにする
     */
    #[Test]
    public function 注文トークンの検証(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $base = $this->paymentData($user, [$items['potato']->id => 2], ['payment_method' => 'cod']);
        unset($base['order_token']);

        $this->actingAs($user)->from(route('shop.checkout.index'))->post(route('shop.checkout.payment'), $base)
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors(['order_token' => CheckoutService::STALE_MESSAGE]);
        $this->post(route('shop.checkout.payment'), [...$base, 'order_token' => str_repeat('a', 101)])
            ->assertSessionHasErrors(['order_token' => CheckoutService::STALE_MESSAGE]);
        $this->post(route('shop.checkout.payment'), [...$base, 'order_token' => ['x']])
            ->assertSessionHasErrors('order_token');

        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * 住所・商品の検証に通らなかった送信ではトークンを使わない。購入ページで直して、同じ画面からそのまま送り直せる
     */
    #[Test]
    public function 検証エラーではトークンを使わず購入ページに引き継ぐ(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $data = $this->paymentData($user, [$items['potato']->id => 2], ['payment_method' => 'cod', 'address_id' => 'delivery_999999']);

        $this->actingAs($user)->from(route('shop.checkout.index'))->post(route('shop.checkout.payment'), $data)
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors('address_id');
        $this->assertSame(OrderTokenStatus::Issued, app(OrderTokenStore::class)->status($user->id, $data['order_token']));

        // 戻った購入ページには、同じ（未使用の）トークンが入る
        $this->assertSame($data['order_token'], $this->tokenFrom($this->get(route('shop.checkout.index'))->getContent()));

        $this->post(route('shop.checkout.payment'), [...$data, 'address_id' => 'user_'.$user->id])->assertRedirect(route('shop.checkout.thanks'));
        $this->assertSame(1, Purchase_history::count());
    }

    /**
     * 決済ページ（Stripe）の「戻る」（?restore=1）で購入ページへ戻ったときは、入力を復元し、新しいトークンを発行する。
     * そのトークンで確定し直すと、前の Checkout Session を失効させて新しい決済ページへ進む
     */
    #[Test]
    public function 決済ページから戻ったときは入力を復元して新しいトークンを発行する(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')->twice()->andReturn(
                new CheckoutSessionData('cs_test_1', 'https://checkout.stripe.test/first', 'open', 'unpaid'),
                new CheckoutSessionData('cs_test_2', 'https://checkout.stripe.test/second', 'open', 'unpaid'),
            );
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_test_1')->andReturn(new CheckoutSessionData('cs_test_1', null, 'open', 'unpaid'));
            $mock->shouldReceive('expireCheckoutSession')->once()->with('cs_test_1')->andReturn(new CheckoutSessionData('cs_test_1', null, 'expired', 'unpaid'));
        });
        $first = $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['delivery_name' => '山田花子']);

        $this->actingAs($user)->post(route('shop.checkout.payment'), $first)->assertRedirect('https://checkout.stripe.test/first');

        // 「戻る」：?restore=1 → フラッシュに置き換えてリダイレクト → 購入ページ
        $this->get(route('shop.checkout.index', ['restore' => 1]))->assertRedirect(route('shop.checkout.index'));
        $page = $this->get(route('shop.checkout.index'))->assertOk();
        $page->assertSee('value="山田花子"', false);
        $newToken = $this->tokenFrom($page->getContent());
        $this->assertNotSame($first['order_token'], $newToken);

        $this->post(route('shop.checkout.payment'), [...$first, 'order_token' => $newToken])->assertRedirect('https://checkout.stripe.test/second');
        $this->assertSame('cs_test_2', session('pending_order')['checkout_session_id']);
    }

    /**
     * 振込先のご案内の「戻る」で購入ページへ戻ったときも、入力を復元し、新しいトークンで確定し直せる
     */
    #[Test]
    public function 振込先のご案内から戻ったときも新しいトークンで確定し直せる(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn($this->bankIntent('pi_test_1', 'requires_action'));
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_test_1');
        });
        $first = $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['payment_method' => 'customer_balance', 'delivery_name' => '山田花子']);

        $this->actingAs($user)->post(route('shop.checkout.payment'), $first)->assertRedirect(route('shop.checkout.bank-transfer'));
        $this->post(route('shop.checkout.bank-transfer.cancel'))->assertRedirect(route('shop.checkout.index'));

        $page = $this->get(route('shop.checkout.index'))->assertOk();
        $page->assertSee('value="山田花子"', false);
        $newToken = $this->tokenFrom($page->getContent());
        $this->assertNotSame($first['order_token'], $newToken);
        $this->assertSame(OrderTokenStatus::Issued, app(OrderTokenStore::class)->status($user->id, $newToken));

        // 使用済みの古いトークンは使えない
        $this->post(route('shop.checkout.payment'), [...$first, 'payment_method' => 'cod'])->assertSessionHasErrors('order_token');
        $this->post(route('shop.checkout.payment'), [...$first, 'payment_method' => 'cod', 'order_token' => $newToken])->assertRedirect(route('shop.checkout.thanks'));
        $this->assertSame(1, Purchase_history::count());
    }

    /**
     * ロック待ち：別の処理がロックを持っていても、待っている間に解放されれば、続けて処理する
     */
    #[Test]
    public function 確定のロックが待っている間に解放されれば処理を続ける(): void
    {
        config(['purchase.begin_lock_wait' => 4]);
        [$user, $items] = $this->makeCartScenario();
        // 1秒で期限が切れるロック（別のリクエストが1秒で処理を終えたことにする）
        $this->assertTrue(Cache::lock('purchase-begin:'.$user->id, 1)->get());

        $startedAt = microtime(true);
        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2], ['payment_method' => 'cod']))
            ->assertRedirect(route('shop.checkout.thanks'));

        $this->assertGreaterThan(0.5, microtime(true) - $startedAt);
        $this->assertSame(1, Purchase_history::count());
    }

    /**
     * ロック待ち：待っても取れなければ「処理中」のエラーにする。トークンは使わないので、取れたあとに同じ画面から確定できる
     */
    #[Test]
    public function 確定のロックを待っても取れなければ処理中を案内しトークンは使わない(): void
    {
        config(['purchase.begin_lock_wait' => 1]);
        [$user, $items] = $this->makeCartScenario();
        $lock = Cache::lock('purchase-begin:'.$user->id, 30);
        $this->assertTrue($lock->get());
        $data = $this->paymentData($user, [$items['potato']->id => 2], ['payment_method' => 'cod']);

        $startedAt = microtime(true);
        $this->actingAs($user)->from(route('shop.checkout.index'))->post(route('shop.checkout.payment'), $data)
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors('payment');

        $this->assertGreaterThan(0.5, microtime(true) - $startedAt);
        $this->assertSame(0, Purchase_history::count());
        $this->assertSame(OrderTokenStatus::Issued, app(OrderTokenStore::class)->status($user->id, $data['order_token']));

        $lock->release();
        $this->post(route('shop.checkout.payment'), $data)->assertRedirect(route('shop.checkout.thanks'));
        $this->assertSame(1, Purchase_history::count());
    }

    /**
     * 作成時点で支払い済みの銀行振込を保存できなかった（保存のロックを取れない）ときは、押し直しを促さず「確定処理中」と案内し、保存待ちの注文を残す。
     * 購入ページを開くと、処理中の間はフォームを出さずに再読み込みを案内し、保存できるようになったら保存してサンクスページへ送る
     */
    #[Test]
    public function 支払い済みの銀行振込を保存できなければ購入ページを開き直したときに保存する(): void
    {
        config(['purchase.complete_lock_wait' => 0]);
        [$user, $items] = $this->makeCartScenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn($this->bankIntent('pi_paid', 'succeeded', 3723, false));
            // 購入ページを開き直したときの確認（保存のロックを取れた1回だけ）
            $mock->shouldReceive('retrieveBankTransferIntent')->once()->with('pi_paid')->andReturn($this->bankIntent('pi_paid', 'succeeded', 3723, false));
            $mock->shouldNotReceive('cancelPaymentIntent');
        });
        $lock = Cache::lock('purchase-complete:'.$user->id, 30);
        $this->assertTrue($lock->get());

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'customer_balance']))
            ->assertSessionHasErrors(['payment' => PurchaseService::PAID_PENDING_MESSAGE]);

        $this->assertStringNotContainsString('もう一度お試しください', PurchaseService::PAID_PENDING_MESSAGE);
        $this->assertSame(0, Purchase_history::count());
        $this->assertSame('pi_paid', session('pending_order')['payment_intent_id']);

        // まだ処理中：購入ページ（フォーム）は出さず、再読み込みを案内する
        $this->get(route('shop.checkout.index'))
            ->assertStatus(503)
            ->assertViewIs('shop.payment_pending')
            ->assertSee('お支払いは完了しています')
            ->assertDontSee('name="order_token"', false);
        $this->assertSame(0, Purchase_history::count());

        // 保存できるようになったら、購入ページを開いたときに保存してサンクスページへ
        $lock->release();
        $this->get(route('shop.checkout.index'))
            ->assertRedirect(route('shop.checkout.thanks'))
            ->assertSessionHas(PurchaseService::THANKS_FLASH_KEY, true);

        $this->assertSame(1, Purchase_history::count());
        $this->assertSame('pi_paid', Purchase_bank_transfer::sole()->stripe_payment_intent_id);
        $this->assertNull(session('pending_order'));
        $this->assertEmpty(session('cart'));
        $this->get(route('shop.checkout.index'))->assertRedirect(route('shop.cart'));
    }

    /**
     * 支払い済みの銀行振込を保存できなかったあとに、同じ画面から押し直しても、新しい注文は作られない（注文は1件のまま）
     */
    #[Test]
    public function 支払い済みの銀行振込で保存できなかったあとに押し直しても重複しない(): void
    {
        config(['purchase.complete_lock_wait' => 0]);
        [$user, $items] = $this->makeCartScenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            // PaymentIntent は最初の1回だけ作る（押し直しで2つ目を作らない）
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn($this->bankIntent('pi_paid', 'succeeded', 3723, false));
            $mock->shouldReceive('retrieveBankTransferIntent')->once()->with('pi_paid')->andReturn($this->bankIntent('pi_paid', 'succeeded', 3723, false));
            $mock->shouldNotReceive('cancelPaymentIntent');
            $mock->shouldNotReceive('createCheckoutSession');
        });
        $lock = Cache::lock('purchase-complete:'.$user->id, 30);
        $this->assertTrue($lock->get());
        $data = $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'customer_balance']);

        $this->actingAs($user)->post(route('shop.checkout.payment'), $data)->assertSessionHasErrors(['payment' => PurchaseService::PAID_PENDING_MESSAGE]);

        // 保存できるようになってから、同じ画面で押し直す（支払方法を代金引換に変えて送っても、同じトークンなので注文は作られない）
        $lock->release();
        foreach (['customer_balance', 'cod', 'card'] as $method) {
            $this->post(route('shop.checkout.payment'), [...$data, 'payment_method' => $method])
                ->assertRedirect(route('shop.checkout.index'))
                ->assertSessionHasErrors(['order_token' => CheckoutService::STALE_MESSAGE]);
        }
        $this->assertSame(0, Purchase_history::count());

        // 購入ページへ戻ると、前の注文（支払い済み）が保存され、新しい注文は作られない
        $this->get(route('shop.checkout.index'))->assertRedirect(route('shop.checkout.thanks'));
        $this->assertSame(1, Purchase_history::count());
        $this->assertSame('customer_balance', Purchase_history::sole()->payment_method);
    }

    /**
     * 代金引換の保存で例外（DBエラー・デッドロックなど）が出たときも、保存待ちの注文を必ず消す。購入データは作らず、カートも減らさず、ロックも解放される
     */
    #[Test]
    public function 代金引換の保存で例外が出ても保存待ちは残らない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->mock(PurchaseHistoryRepositoryInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createWithItems')->once()->andThrow(new RuntimeException('Deadlock found'));
        });
        $data = $this->paymentData($user, [$items['potato']->id => 2], ['payment_method' => 'cod']);

        $this->actingAs($user)->post(route('shop.checkout.payment'), $data)->assertStatus(500);

        $this->assertNull(session('pending_order'));
        $this->assertSame(0, Purchase_history::count());
        $this->assertSame([$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], session('cart'));
        foreach (['purchase-begin:', 'purchase-complete:'] as $prefix) {
            $lock = Cache::lock($prefix.$user->id, 30);
            $this->assertTrue($lock->get());
            $lock->release();
        }
    }

    /**
     * 支払い済みの銀行振込の保存で例外が出たときは、支払い済みの注文を失わないよう、保存待ちの注文を残す
     */
    #[Test]
    public function 支払い済みの銀行振込の保存で例外が出ても保存待ちは残る(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->mock(PurchaseHistoryRepositoryInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createWithItems')->once()->andThrow(new RuntimeException('Deadlock found'));
        });
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn($this->bankIntent('pi_paid', 'succeeded', 3723, false));
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'customer_balance']))
            ->assertStatus(500);

        $this->assertSame('pi_paid', session('pending_order')['payment_intent_id']);
        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * 代金引換で保存のロックを取れなかったときは、保存待ちの注文を消し、押し直してよいと案内する
     */
    #[Test]
    public function 代金引換で保存のロックを取れなければ保存待ちを消して案内する(): void
    {
        config(['purchase.complete_lock_wait' => 0]);
        [$user, $items] = $this->makeCartScenario();
        $this->assertTrue(Cache::lock('purchase-complete:'.$user->id, 30)->get());

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2], ['payment_method' => 'cod']))
            ->assertSessionHasErrors(['payment' => 'ただいまご注文を処理しています。しばらく待ってから、もう一度お試しください']);

        $this->assertNull(session('pending_order'));
        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * ロックの有効期限は、1回の注文確定で起こりうる Stripe への問い合わせの合計時間より長い。
     * Stripe への通信のタイムアウトは、接続5秒・全体20秒
     */
    #[Test]
    public function ロックの有効期限は_stripeへの問い合わせの合計時間を上回る(): void
    {
        $timeout = (int) config('services.stripe.timeout');

        $this->assertSame(5, config('services.stripe.connect_timeout'));
        $this->assertSame(20, $timeout);
        $this->assertGreaterThan((int) config('purchase.begin_stripe_calls') * $timeout, (int) config('purchase.begin_lock_ttl'));
        $this->assertGreaterThan($timeout, (int) config('purchase.complete_lock_ttl'));
        $this->assertGreaterThan(0, (int) config('purchase.begin_lock_wait'));
    }

    /**
     * 「注文を確定する」は、同じセッションの同時リクエストを順番に処理する（セッションの排他。ロックの有効期限は確定のロックと同じ）
     */
    #[Test]
    public function 注文確定のルートはセッションを排他にする(): void
    {
        $route = Route::getRoutes()->getByName('shop.checkout.payment');

        $this->assertNotNull($route);
        $this->assertSame((int) config('purchase.begin_lock_ttl'), $route->locksFor());
        $this->assertSame((int) config('purchase.session_lock_wait'), $route->waitsFor());
    }

    /**
     * セッションの排他を待ちきれなかったとき（先のリクエストが終わらず、LockTimeoutException になる）は、500にせず「処理中」の案内（503）を返す
     */
    #[Test]
    public function セッションの排他を待ちきれなければ処理中を案内する(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $data = $this->paymentData($user, [$items['potato']->id => 2], ['payment_method' => 'cod']);
        $this->mock(PurchaseService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('beginCheckout')->once()->andThrow(new LockTimeoutException);
        });

        $this->actingAs($user)->post(route('shop.checkout.payment'), $data)
            ->assertStatus(503)
            ->assertSee('ただいまご注文を処理しています');

        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * 支払い方法の値は変わらない（代金引換は Stripe を通さない）
     */
    #[Test]
    public function 代金引換は決済サービスを呼ばない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('ensureCustomer');
            $mock->shouldNotReceive('createCheckoutSession');
            $mock->shouldNotReceive('createBankTransferIntent');
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2], ['payment_method' => PaymentMethod::Cod->value]))
            ->assertRedirect(route('shop.checkout.thanks'));
    }
}
