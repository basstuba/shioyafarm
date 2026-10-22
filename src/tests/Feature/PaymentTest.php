<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Delivery_address;
use App\Models\Item;
use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use App\Models\User;
use App\Services\Payment\BankTransferInstructions;
use App\Services\Payment\BankTransferIntentData;
use App\Services\Payment\CheckoutSessionData;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * 「注文を確定する」（POST /payment）のテスト。Stripe はモックに差し替え、実際のAPIは呼ばない
 */
class PaymentTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * シナリオ S-2 のカート（じゃがいも2kg×2、玉ねぎ5kg×1、人参2kg×1）を作る
     *
     * @return array{User, array<string, Item>}
     */
    private function cartScenario(): array
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $items = [
            'potato' => $this->makeItem('じゃがいも', 2, 330),
            'onion' => $this->makeItem('玉ねぎ', 5, 795),
            'carrot' => $this->makeItem('人参', 2, 418),
        ];
        session(['cart' => [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1]]);

        return [$user, $items];
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

    /**
     * Stripe のモックを使わないことを保証する（呼ばれたらテスト失敗）
     */
    private function gatewayNeverCalled(): void
    {
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createCheckoutSession');
            $mock->shouldNotReceive('retrieveCheckoutSession');
            $mock->shouldNotReceive('createBankTransferIntent');
            $mock->shouldNotReceive('retrieveBankTransferIntent');
            $mock->shouldNotReceive('cancelPaymentIntent');
            $mock->shouldNotReceive('expireCheckoutSession');
            $mock->shouldNotReceive('ensureCustomer');
        });
    }

    /**
     * 未ログインはログインページへ移動し、注文されない
     */
    #[Test]
    public function 未ログインはログインページへ移動する(): void
    {
        $this->gatewayNeverCalled();

        $this->post(route('shop.checkout.payment'), [])->assertRedirect(route('login'));

        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * クレジットカード：Checkout Session を作って Stripe のページへ移動し、保存待ちの注文をセッションに入れる（S-15）
     */
    #[Test]
    public function クレジットカードは_stripeの決済ページへ移動する(): void
    {
        [$user, $items] = $this->cartScenario();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->withArgs(function (PaymentMethod $method, string $customerId, array $lineItems, string $successUrl, string $cancelUrl): bool {
                    return $method === PaymentMethod::Card
                        && $customerId === 'cus_test'
                        && $lineItems === [
                            ['name' => 'じゃがいも 2kg', 'unit_amount' => 330, 'quantity' => 2],
                            ['name' => '玉ねぎ 5kg', 'unit_amount' => 795, 'quantity' => 1],
                            ['name' => '人参 2kg', 'unit_amount' => 418, 'quantity' => 1],
                            ['name' => '送料', 'unit_amount' => 1850, 'quantity' => 1],
                        ]
                        && $successUrl === route('shop.checkout.success').'?session_id={CHECKOUT_SESSION_ID}'
                        && $cancelUrl === route('shop.checkout.index', ['restore' => 1]);
                })
                ->andReturn(new CheckoutSessionData('cs_test_card', 'https://checkout.stripe.test/pay/cs_test_card', 'open'));
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items)))
            ->assertRedirect('https://checkout.stripe.test/pay/cs_test_card');

        $pending = session('pending_order');
        $this->assertSame('cart', $pending['source']);
        $this->assertSame('card', $pending['payment_method']);
        $this->assertSame('cs_test_card', $pending['checkout_session_id']);
        $this->assertSame('塩谷太郎', $pending['delivery_name']);
        $this->assertSame('09012345678', $pending['delivery_tell']);
        $this->assertSame('北海道', $pending['delivery_prefecture']);
        $this->assertSame(1873, $pending['subtotal']);
        $this->assertSame(1850, $pending['shipping_fee']);
        $this->assertSame(0, $pending['cod_fee']);
        $this->assertSame(3723, $pending['total_price']);
        $this->assertCount(3, $pending['items']);

        // ここでは購入データを保存しない
        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * 銀行振込用の PaymentIntent の応答（振込先つき）を作る
     */
    private function bankIntent(string $id = 'pi_test_bank', string $status = 'requires_action', int $amount = 3723, bool $withInstructions = true): BankTransferIntentData
    {
        return new BankTransferIntentData(
            $id,
            $status,
            $amount,
            $withInstructions ? new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', $amount) : null,
        );
    }

    /**
     * 銀行振込：Checkout は使わず PaymentIntent を作り、振込先と PaymentIntent ID を保存待ちの注文に入れて「振込先のご案内」へ移動する（S-16, AC-9）
     */
    #[Test]
    public function 銀行振込は_payment_intentを作って振込先のご案内へ移動する(): void
    {
        [$user, $items] = $this->cartScenario();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldNotReceive('createCheckoutSession');
            $mock->shouldReceive('createBankTransferIntent')
                ->once()
                ->with('cus_test', 3723)
                ->andReturn($this->bankIntent());
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'customer_balance']))
            ->assertRedirect(route('shop.checkout.bank-transfer'));

        $pending = session('pending_order');
        $this->assertSame('customer_balance', $pending['payment_method']);
        $this->assertSame('pi_test_bank', $pending['payment_intent_id']);
        $this->assertNull($pending['checkout_session_id']);
        $this->assertSame(0, $pending['cod_fee']);
        $this->assertSame(1850, $pending['shipping_fee']);
        $this->assertSame(3723, $pending['total_price']);
        $this->assertSame('いろは銀行', $pending['bank_transfer']['bank_name']);
        $this->assertSame('1234567', $pending['bank_transfer']['account_number']);
        $this->assertSame(3723, $pending['bank_transfer']['amount']);
        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * 銀行振込：PaymentIntent を作った時点で Stripe の顧客残高から支払い済み（succeeded・processing）になっていたときは、
     * 金額が合っていれば取り消さず、振込先なしで購入データを保存してサンクスページ（/purchase/thanks）へ移動する
     */
    #[Test]
    public function 銀行振込が作成時点で支払い済みなら保存してサンクスページへ移動する(): void
    {
        foreach (['succeeded', 'processing'] as $status) {
            [$user, $items] = $this->cartScenario();
            $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($status): void {
                $this->stubCustomer($mock, 'cus_'.$status);
                $mock->shouldReceive('createBankTransferIntent')->once()->with('cus_'.$status, 3723)->andReturn($this->bankIntent('pi_paid_'.$status, $status, 3723, false));
                $mock->shouldNotReceive('cancelPaymentIntent');
            });

            $this->actingAs($user)
                ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'customer_balance']))
                ->assertRedirect(route('shop.checkout.thanks'))
                ->assertSessionHas(PurchaseService::THANKS_FLASH_KEY, true)
                ->assertSessionHasNoErrors();

            $history = Purchase_history::where('user_id', $user->id)->sole();
            $this->assertSame('customer_balance', $history->payment_method);
            $this->assertSame(3723, $history->total_price);
            $this->assertSame(3, $history->purchaseHistoryItems()->count());

            // 振込先の案内が無いため、振込先の各項目は null で PaymentIntent ID と金額だけ保存する
            $transfer = Purchase_bank_transfer::where('purchase_history_id', $history->id)->sole();
            $this->assertSame('pi_paid_'.$status, $transfer->stripe_payment_intent_id);
            $this->assertSame(3723, $transfer->amount);
            $this->assertNull($transfer->bank_name);
            $this->assertNull($transfer->account_number);

            // 保存待ちの注文は残らず、購入したカートの商品は減る
            $this->assertNull(session('pending_order'));
            $this->assertEmpty(session('cart'));
            $this->get(route('shop.checkout.thanks'))->assertOk()->assertViewIs('shop.purchase_thanks');
        }
    }

    /**
     * 銀行振込が作成時点で支払い済みでも、金額が合わなければ保存せず、PaymentIntent を取り消してエラーにする
     */
    #[Test]
    public function 銀行振込が作成時点で支払い済みでも金額が違えば保存しない(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn($this->bankIntent('pi_paid', 'succeeded', 100, false));
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_paid');
        });

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'customer_balance']))
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors('payment');

        $this->assertSame(0, Purchase_history::count());
        $this->assertNull(session('pending_order'));
    }

    /**
     * 作成時点で支払い済みの銀行振込を保存できなかった（ロックを取れない）ときは、保存待ちの注文を残して、押し直しを促さないエラーにする。
     * 購入ページを開いたときに保存されるが（PurchaseOrderTokenTest）、別のタブなど、開き直さずに別の画面（別のトークン）から確定したときも、
     * 前の注文として支払い済みを確認して保存される（顧客残高が使われたのに注文が消えない）
     */
    #[Test]
    public function 支払い済みの銀行振込を保存できなければ次の確定で保存される(): void
    {
        config(['purchase.complete_lock_wait' => 0]);
        [$user, $items] = $this->cartScenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn($this->bankIntent('pi_paid', 'succeeded', 3723, false));
            // 次の確定：前の銀行振込のキャンセルは失敗し（入金済み）、状態を確認して保存する
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_paid')->andThrow(new PaymentGatewayException('入金済み'));
            $mock->shouldReceive('retrieveBankTransferIntent')->twice()->with('pi_paid')->andReturn($this->bankIntent('pi_paid', 'succeeded', 3723, false));
        });
        $lock = Cache::lock('purchase-complete:'.$user->id, 30);
        $this->assertTrue($lock->get());

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'customer_balance']))
            ->assertSessionHasErrors(['payment' => PurchaseService::PAID_PENDING_MESSAGE]);

        $this->assertSame(0, Purchase_history::count());
        $this->assertSame('pi_paid', session('pending_order')['payment_intent_id']);

        $lock->release();
        $this->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'cod']))
            ->assertRedirect(route('shop.checkout.thanks'));

        $this->assertSame(1, Purchase_bank_transfer::where('stripe_payment_intent_id', 'pi_paid')->count());
        $this->assertSame(2, Purchase_history::count());
    }

    /**
     * 銀行振込：PaymentIntent に渡す金額は、サーバーで再計算した合計（改ざんされた金額は使わない）
     */
    #[Test]
    public function 銀行振込の金額はサーバーで再計算する(): void
    {
        [$user, $items] = $this->cartScenario();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->with(\Mockery::any(), 3723)->andReturn($this->bankIntent());
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'customer_balance', 'total_price' => 1, 'amount' => 1]))
            ->assertRedirect(route('shop.checkout.bank-transfer'));
    }

    /**
     * 銀行振込：振込先を取得できなかった（status が想定外・振込先なし・金額の不一致）ときは、作った PaymentIntent を取り消して購入ページへ戻す
     */
    #[Test]
    public function 銀行振込の振込先を取得できなければキャンセルしてエラーになる(): void
    {
        [$user, $items] = $this->cartScenario();

        foreach ([
            $this->bankIntent('pi_a', 'requires_action', 3723, false),
            $this->bankIntent('pi_b', 'canceled', 3723, false),
            $this->bankIntent('pi_c', 'requires_action', 100, true),
        ] as $intent) {
            $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($intent): void {
                $this->stubCustomer($mock);
                $mock->shouldReceive('createBankTransferIntent')->once()->andReturn($intent);
                $mock->shouldReceive('cancelPaymentIntent')->once()->with($intent->id);
            });

            $this->actingAs($user)
                ->from(route('shop.checkout.index'))
                ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'customer_balance']))
                ->assertRedirect(route('shop.checkout.index'))
                ->assertSessionHasErrors(['payment']);

            $this->assertNull(session('pending_order'));
        }
    }

    /**
     * 銀行振込：PaymentIntent を作れなかったときは、購入ページへ戻してエラーを表示し、入力を残す
     */
    #[Test]
    public function 銀行振込の_payment_intentを作れなければ購入ページへ戻る(): void
    {
        [$user, $items] = $this->cartScenario();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->andThrow(new PaymentGatewayException('通信エラー'));
        });

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'customer_balance', 'delivery_name' => '山田花子']))
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors(['payment'])
            ->assertSessionHasInput('delivery_name', '山田花子');

        $this->assertNull(session('pending_order'));
    }

    /**
     * 銀行振込をやり直したとき（案内ページから戻らず、もう一度「注文を確定する」）は、前の PaymentIntent をキャンセルしてから新しく作る
     */
    #[Test]
    public function 銀行振込をやり直すと前の_payment_intentをキャンセルする(): void
    {
        [$user, $items] = $this->cartScenario();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->twice()->andReturn($this->bankIntent('pi_first'), $this->bankIntent('pi_second'));
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_first');
        });
        $data = $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'customer_balance']);

        $this->actingAs($user)->post(route('shop.checkout.payment'), $data)->assertRedirect(route('shop.checkout.bank-transfer'));
        $this->post(route('shop.checkout.payment'), [...$data, 'order_token' => $this->newOrderToken($user)])->assertRedirect(route('shop.checkout.bank-transfer'));

        $this->assertSame('pi_second', session('pending_order')['payment_intent_id']);
    }

    /**
     * 銀行振込からカードに変えて確定し直したときも、前の銀行振込の PaymentIntent をキャンセルし、振込先の情報は残さない
     */
    #[Test]
    public function 銀行振込からカードに変えると前の_payment_intentをキャンセルする(): void
    {
        [$user, $items] = $this->cartScenario();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn($this->bankIntent('pi_first'));
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_first');
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_test_card', 'https://checkout.stripe.test/pay/cs_test_card', 'open'));
        });

        $this->actingAs($user)->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'customer_balance']));
        $this->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'card']))
            ->assertRedirect('https://checkout.stripe.test/pay/cs_test_card');

        $this->assertNull(session('pending_order')['payment_intent_id']);
        $this->assertNull(session('pending_order')['bank_transfer']);
    }

    /**
     * 代金引換：Stripe を通さず、ここで手数料込みの購入データを保存してサンクスページへ移動する（S-17）
     */
    #[Test]
    public function 代金引換は_stripeを通さず購入データを保存してサンクスページへ移動する(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['payment_method' => 'cod']))
            ->assertRedirect(route('shop.checkout.thanks'))
            ->assertSessionHas(PurchaseService::THANKS_FLASH_KEY, true);

        $history = Purchase_history::sole();
        $this->assertSame('cod', $history->payment_method);
        $this->assertSame(1850, $history->shipping_fee);
        $this->assertSame(config('shipping.cod_fee'), $history->cod_fee);
        $this->assertSame(4053, $history->total_price);
        $this->assertNull(session('pending_order'));
    }

    /**
     * 商品詳細からの購入：その商品だけを、cancel_url は商品つきの購入ページにする
     */
    #[Test]
    public function 商品詳細からの購入はキャンセル時にその商品の購入ページへ戻る(): void
    {
        [$user, $items] = $this->cartScenario();
        $carrot = $items['carrot'];

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($carrot): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->withArgs(function (PaymentMethod $method, string $customerId, array $lineItems, string $successUrl, string $cancelUrl) use ($carrot): bool {
                    return $cancelUrl === route('shop.checkout.index', ['item' => $carrot, 'restore' => 1])
                        && $lineItems === [
                            ['name' => '人参 2kg', 'unit_amount' => 418, 'quantity' => 1],
                            ['name' => '送料', 'unit_amount' => 940, 'quantity' => 1],
                        ];
                })
                ->andReturn(new CheckoutSessionData('cs_test_item', 'https://checkout.stripe.test/pay/cs_test_item', 'open'));
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$carrot->id => 1], ['source' => 'item']))
            ->assertRedirect('https://checkout.stripe.test/pay/cs_test_item');

        $this->assertSame('item', session('pending_order')['source']);
        $this->assertSame(1358, session('pending_order')['total_price']);
    }

    /**
     * 金額はフォームの値を信用せず、サーバー側で再計算する（改ざんされた価格・送料・合計は無視される）
     */
    #[Test]
    public function 改ざんされた金額は無視してサーバーで再計算する(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), [
                'payment_method' => 'cod',
                'price' => 1,
                'shipping_fee' => 0,
                'cod_fee' => 0,
                'total_price' => 1,
                'subtotal' => 1,
                'items' => [
                    ['item_id' => $items['potato']->id, 'quantity' => 2, 'price' => 1, 'subtotal' => 1],
                    ['item_id' => $items['onion']->id, 'quantity' => 1, 'price' => 1],
                    ['item_id' => $items['carrot']->id, 'quantity' => 1, 'price' => 1],
                ],
            ]))
            ->assertRedirect(route('shop.checkout.thanks'));

        $history = Purchase_history::sole();
        $this->assertSame(4053, $history->total_price);
        $this->assertSame(1850, $history->shipping_fee);
        $this->assertSame([330, 795, 418], $history->purchaseHistoryItems->pluck('price')->all());
        $this->assertSame([660, 795, 418], $history->purchaseHistoryItems->pluck('subtotal')->all());
    }

    /**
     * 他人の配送先住所は指定できない（認可）
     */
    #[Test]
    public function 他人の配送先住所は指定できない(): void
    {
        [$user, $items] = $this->cartScenario();
        $others = Delivery_address::factory()->create(['delivery_prefecture' => '沖縄県', 'delivery_city' => '那覇市9-9']);
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['address_id' => 'delivery_'.$others->id]))
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors(['address_id' => 'お届け先住所が正しくありません']);

        $this->assertNull(session('pending_order'));
    }

    /**
     * 他の会員の会員登録の住所（user_{他人のID}）も指定できない
     */
    #[Test]
    public function 他の会員の登録住所は指定できない(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['address_id' => 'user_'.($user->id + 1)]))
            ->assertSessionHasErrors(['address_id']);

        $this->assertNull(session('pending_order'));
    }

    /**
     * 自分の配送先住所を選ぶと、その都道府県の送料で計算される（S-7）
     */
    #[Test]
    public function 配送先住所の都道府県の送料で計算する(): void
    {
        [$user, $items] = $this->cartScenario();
        $tokyo = Delivery_address::factory()->create(['user_id' => $user->id, 'delivery_prefecture' => '東京都', 'delivery_city' => '千代田区1-1']);
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), [
                'address_id' => 'delivery_'.$tokyo->id,
                'payment_method' => 'cod',
            ]))
            ->assertRedirect(route('shop.checkout.thanks'));

        $history = Purchase_history::sole();
        $this->assertSame('東京都', $history->delivery_prefecture);
        $this->assertSame('千代田区1-1', $history->delivery_city);
        $this->assertSame(2370, $history->shipping_fee);
        $this->assertSame(1873 + 2370 + 330, $history->total_price);
    }

    /**
     * 入力に誤りがあるとエラーになり、Stripeへ移動せず、注文もされない（S-13, S-14, AC-7）
     *
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function 誤った入力(): array
    {
        return [
            '氏名が空' => [['delivery_name' => ''], 'delivery_name'],
            '氏名が256文字' => [['delivery_name' => str_repeat('あ', 256)], 'delivery_name'],
            '電話番号が空' => [['delivery_tell' => ''], 'delivery_tell'],
            '電話番号にハイフン' => [['delivery_tell' => '090-1234-5678'], 'delivery_tell'],
            '電話番号が9桁' => [['delivery_tell' => '123456789'], 'delivery_tell'],
            '電話番号が12桁' => [['delivery_tell' => '123456789012'], 'delivery_tell'],
            '電話番号に全角数字' => [['delivery_tell' => '０９０１２３４５６７８'], 'delivery_tell'],
            '住所が空' => [['address_id' => ''], 'address_id'],
            '住所の形式が不正' => [['address_id' => 'abc'], 'address_id'],
            '住所の接頭辞が不正' => [['address_id' => 'admin_1'], 'address_id'],
            '住所に余計な文字' => [['address_id' => 'user_1 OR 1=1'], 'address_id'],
            '支払方法が空' => [['payment_method' => ''], 'payment_method'],
            '支払方法が指定外' => [['payment_method' => 'paypay'], 'payment_method'],
            '購入元が指定外' => [['source' => 'other'], 'source'],
            '購入元がない' => [['source' => null], 'source'],
            '商品がない' => [['items' => []], 'items'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[Test]
    #[DataProvider('誤った入力')]
    public function 誤った入力はエラーになり注文されない(array $overrides, string $errorKey): void
    {
        [$user, $items] = $this->cartScenario();
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), $overrides))
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors($errorKey);

        $this->assertNull(session('pending_order'));
        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * 氏名255文字・電話番号10桁と11桁は受け付ける（境界値）
     */
    #[Test]
    public function 氏名と電話番号の境界値は受け付ける(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->gatewayNeverCalled();

        foreach ([str_repeat('あ', 255) => '0123456789', '塩谷太郎' => '01234567890'] as $name => $tell) {
            $this->actingAs($user)
                ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), [
                    'delivery_name' => (string) $name,
                    'delivery_tell' => $tell,
                    'payment_method' => 'cod',
                ]))
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('shop.checkout.thanks'));
        }
    }

    /**
     * 数量が範囲外・数字以外のときはエラーになる（0・11・小数・文字）
     */
    #[Test]
    public function 数量が範囲外ならエラーになる(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->gatewayNeverCalled();

        foreach ([0, 11, -1, 1.5, 'abc', ''] as $quantity) {
            $this->actingAs($user)
                ->from(route('shop.checkout.index'))
                ->post(route('shop.checkout.payment'), [
                    ...$this->paymentData($user, []),
                    'items' => [['item_id' => $items['potato']->id, 'quantity' => $quantity]],
                ])
                ->assertSessionHasErrors(['items.0.quantity']);
        }

        $this->assertNull(session('pending_order'));
    }

    /**
     * 数量の境界値：1個と10個は受け付ける
     */
    #[Test]
    public function 数量一個と十個は受け付ける(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->gatewayNeverCalled();

        foreach ([1, 10] as $quantity) {
            $this->actingAs($user)
                ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => $quantity], ['payment_method' => 'cod']))
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('shop.checkout.thanks'));
        }
    }

    /**
     * 存在しない商品・予約限定の商品・重複した商品は購入できない
     */
    #[Test]
    public function 購入できない商品はエラーになる(): void
    {
        [$user, $items] = $this->cartScenario();
        $reserved = $this->makeReservedItem();
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$reserved->id => 1]))
            ->assertSessionHasErrors(['items.0.item_id']);

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [9999 => 1]))
            ->assertSessionHasErrors(['items.0.item_id']);

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), [
                ...$this->paymentData($user, []),
                'items' => [
                    ['item_id' => $items['potato']->id, 'quantity' => 1],
                    ['item_id' => $items['potato']->id, 'quantity' => 2],
                ],
            ])
            ->assertSessionHasErrors(['items.0.item_id', 'items.1.item_id']);

        $this->assertNull(session('pending_order'));
    }

    /**
     * 商品詳細からの購入（source=item）で複数の商品を送っても受け付けない
     */
    #[Test]
    public function 商品詳細からの購入に複数の商品は送れない(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['source' => 'item']))
            ->assertSessionHasErrors(['items']);

        $this->assertNull(session('pending_order'));
    }

    /**
     * Stripe の Checkout Session を作れなかったときは、購入ページへ戻してエラーを表示し、入力を残す
     */
    #[Test]
    public function stripeでエラーになったときは購入ページへ戻る(): void
    {
        [$user, $items] = $this->cartScenario();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')->once()->andThrow(new PaymentGatewayException('通信エラー'));
        });

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items), ['delivery_name' => '山田花子']))
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors(['payment'])
            ->assertSessionHasInput('delivery_name', '山田花子');

        $this->assertNull(session('pending_order'));
        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * Checkout Session にURLがなかったときもエラーとして扱う
     */
    #[Test]
    public function 決済ページの_ur_lがないときはエラーになる(): void
    {
        [$user, $items] = $this->cartScenario();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_test_nourl', null, 'open'));
        });

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $this->cartQuantities($items)))
            ->assertSessionHasErrors(['payment']);

        $this->assertNull(session('pending_order'));
    }
}
