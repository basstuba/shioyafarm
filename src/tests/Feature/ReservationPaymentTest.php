<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Delivery_address;
use App\Models\Item;
use App\Models\Reservation;
use App\Models\Reservation_bank_transfer;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Payment\BankTransferInstructions;
use App\Services\Payment\BankTransferIntentData;
use App\Services\Payment\CheckoutSessionData;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\Concerns\ReservationTestHelpers;
use Tests\TestCase;

/**
 * 「予約を確定する」（POST /reservation/payment）のテスト。Stripe はモックに差し替え、実際のAPIは呼ばない
 */
class ReservationPaymentTest extends TestCase
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
     * Stripe を呼ばないことを保証する（呼ばれたらテスト失敗）
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
     * 銀行振込用の PaymentIntent の応答を作る
     */
    private function bankIntent(string $id = 'pi_test_bank', string $status = 'requires_action', int $amount = 2980, bool $withInstructions = true): BankTransferIntentData
    {
        return new BankTransferIntentData(
            $id,
            $status,
            $amount,
            $withInstructions ? new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', $amount) : null,
        );
    }

    /**
     * 未ログインはログインページへ移動し、予約されない
     */
    #[Test]
    public function 未ログインはログインページへ移動する(): void
    {
        $this->gatewayNeverCalled();

        $this->post(route('shop.reservation.payment'), [])->assertRedirect(route('login'));

        $this->assertSame(0, Reservation::count());
    }

    /**
     * 予約の確定は POST だけ（GET では実行できない）
     */
    #[Test]
    public function 予約の確定は_ge_tでは実行できない(): void
    {
        [$user] = $this->scenario();

        $this->actingAs($user)->get('/reservation/payment')->assertStatus(405);
    }

    /**
     * クレジットカード：Checkout Session を作って Stripe のページへ移動し、保存待ちの予約をセッションに入れる（AC-10, S-14）
     */
    #[Test]
    public function クレジットカードは_stripeの決済ページへ移動する(): void
    {
        [$user, $item] = $this->scenario();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($item): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->withArgs(function (PaymentMethod $method, string $customerId, array $lineItems, string $successUrl, string $cancelUrl) use ($item): bool {
                    return $method === PaymentMethod::Card
                        && $customerId === 'cus_test'
                        && $lineItems === [
                            ['name' => '北の恵みセット 10kg', 'unit_amount' => 1450, 'quantity' => 1],
                            ['name' => '送料', 'unit_amount' => config('shipping.fees.100.北海道'), 'quantity' => 1],
                        ]
                        && $successUrl === route('shop.reservation.success').'?session_id={CHECKOUT_SESSION_ID}'
                        && $cancelUrl === route('shop.reservation.index', ['item' => $item->id, 'restore' => 1]);
                })
                ->andReturn(new CheckoutSessionData('cs_test_res', 'https://checkout.stripe.test/pay/cs_test_res', 'open'));
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item))
            ->assertRedirect('https://checkout.stripe.test/pay/cs_test_res');

        $pending = session('pending_reservation');
        $this->assertSame('item', $pending['source']);
        $this->assertSame('card', $pending['payment_method']);
        $this->assertSame('cs_test_res', $pending['checkout_session_id']);
        $this->assertSame('塩谷太郎', $pending['delivery_name']);
        $this->assertSame('09012345678', $pending['delivery_tell']);
        $this->assertSame('北海道', $pending['delivery_prefecture']);
        $this->assertSame(1450, $pending['subtotal']);
        $this->assertSame(1530, $pending['shipping_fee']);
        $this->assertSame(0, $pending['cod_fee']);
        $this->assertSame(2980, $pending['total_price']);
        $this->assertSame([['item_id' => $item->id, 'name' => '北の恵みセット 10kg', 'quantity' => 1, 'price' => 1450, 'subtotal' => 1450]], $pending['items']);
        $this->assertNull(session('pending_order'));

        // ここでは予約データを保存しない
        $this->assertSame(0, Reservation::count());
    }

    /**
     * 数量2・東京都のお届け先でも、送料は購入と同じ計算（合計重量から）で、Stripe に渡す行に反映される
     */
    #[Test]
    public function 数量と住所に応じた送料でstripeに渡す(): void
    {
        [$user, $item] = $this->scenario();
        $tokyo = Delivery_address::factory()->create(['user_id' => $user->id, 'delivery_prefecture' => '東京都', 'delivery_city' => '千代田区1-1']);

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->withArgs(fn (PaymentMethod $method, string $customerId, array $lineItems): bool => $lineItems === [
                    ['name' => '北の恵みセット 10kg', 'unit_amount' => 1450, 'quantity' => 2],
                    ['name' => '送料', 'unit_amount' => config('shipping.fees.140.関東'), 'quantity' => 1],
                ])
                ->andReturn(new CheckoutSessionData('cs_test_res', 'https://checkout.stripe.test/pay/cs_test_res', 'open'));
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 2, ['address_id' => 'delivery_'.$tokyo->id]))
            ->assertRedirect('https://checkout.stripe.test/pay/cs_test_res');

        $pending = session('pending_reservation');
        $this->assertSame('東京都', $pending['delivery_prefecture']);
        $this->assertSame('千代田区1-1', $pending['delivery_city']);
        $this->assertSame(2900, $pending['subtotal']);
        $this->assertSame(config('shipping.fees.140.関東'), $pending['shipping_fee']);
        $this->assertSame(2900 + config('shipping.fees.140.関東'), $pending['total_price']);
    }

    /**
     * 銀行振込：Checkout は使わず PaymentIntent を作り、振込先を保存待ちの予約に入れて「振込先のご案内」へ移動する（AC-11, S-15）
     */
    #[Test]
    public function 銀行振込は_payment_intentを作って振込先のご案内へ移動する(): void
    {
        [$user, $item] = $this->scenario();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldNotReceive('createCheckoutSession');
            $mock->shouldReceive('createBankTransferIntent')->once()->with('cus_test', 2980)->andReturn($this->bankIntent());
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 1, ['payment_method' => 'customer_balance']))
            ->assertRedirect(route('shop.reservation.bank-transfer'));

        $pending = session('pending_reservation');
        $this->assertSame('customer_balance', $pending['payment_method']);
        $this->assertSame('pi_test_bank', $pending['payment_intent_id']);
        $this->assertNull($pending['checkout_session_id']);
        $this->assertSame(2980, $pending['total_price']);
        $this->assertSame('いろは銀行', $pending['bank_transfer']['bank_name']);
        $this->assertSame(2980, $pending['bank_transfer']['amount']);
        $this->assertSame(0, Reservation::count());
    }

    /**
     * 銀行振込：PaymentIntent を作った時点で顧客残高から支払い済みなら、振込先なしで予約を保存してサンクスページへ移動する
     */
    #[Test]
    public function 銀行振込が作成時点で支払い済みなら保存してサンクスページへ移動する(): void
    {
        [$user, $item] = $this->scenario();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn($this->bankIntent('pi_paid', 'succeeded', 2980, false));
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 1, ['payment_method' => 'customer_balance']))
            ->assertRedirect(route('shop.reservation.thanks'))
            ->assertSessionHas(ReservationService::THANKS_FLASH_KEY, true)
            ->assertSessionHasNoErrors();

        $reservation = Reservation::sole();
        $this->assertSame('customer_balance', $reservation->payment_method);
        $this->assertSame(2980, $reservation->total_price);

        $transfer = Reservation_bank_transfer::sole();
        $this->assertSame($reservation->id, $transfer->reservation_id);
        $this->assertSame('pi_paid', $transfer->stripe_payment_intent_id);
        $this->assertSame(2980, $transfer->amount);
        $this->assertNull($transfer->bank_name);
        $this->assertNull(session('pending_reservation'));
    }

    /**
     * 金額は送られた値を使わず、サーバーで再計算する
     */
    #[Test]
    public function 金額はサーバーで再計算する(): void
    {
        [$user, $item] = $this->scenario();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->with(\Mockery::any(), 2980)->andReturn($this->bankIntent());
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 1, [
                'payment_method' => 'customer_balance',
                'total_price' => 1,
                'amount' => 1,
                'shipping_fee' => 0,
                'price' => 1,
            ]))
            ->assertRedirect(route('shop.reservation.bank-transfer'));

        $this->assertSame(2980, session('pending_reservation')['total_price']);
    }

    /**
     * 入力に誤りがあるときはエラーになり、Stripe へ進まず、予約も作られない（AC-7, S-9, S-10）
     *
     * @param  array<string, mixed>  $overrides
     */
    #[Test]
    #[DataProvider('invalidInputs')]
    public function 入力に誤りがあるとエラーになる(array $overrides, string $errorKey): void
    {
        [$user, $item] = $this->scenario();
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->from(route('shop.reservation.index', $item))
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 1, $overrides))
            ->assertRedirect(route('shop.reservation.index', $item))
            ->assertSessionHasErrors($errorKey);

        $this->assertSame(0, Reservation::count());
        $this->assertNull(session('pending_reservation'));
    }

    /**
     * 誤った入力の一覧（入力 => エラーになる項目）
     *
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            '氏名が空' => [['delivery_name' => ''], 'delivery_name'],
            '氏名が256文字' => [['delivery_name' => str_repeat('あ', 256)], 'delivery_name'],
            '電話番号が空' => [['delivery_tell' => ''], 'delivery_tell'],
            '電話番号にハイフン' => [['delivery_tell' => '090-1234-5678'], 'delivery_tell'],
            '電話番号が9桁' => [['delivery_tell' => '123456789'], 'delivery_tell'],
            '電話番号が12桁' => [['delivery_tell' => '123456789012'], 'delivery_tell'],
            '数量が0' => [['quantity' => 0], 'quantity'],
            '数量が11' => [['quantity' => 11], 'quantity'],
            '数量が空' => [['quantity' => ''], 'quantity'],
            '数量が数字以外' => [['quantity' => 'abc'], 'quantity'],
            '数量が小数' => [['quantity' => '1.5'], 'quantity'],
            '支払方法が未選択' => [['payment_method' => ''], 'payment_method'],
            '支払方法が代金引換' => [['payment_method' => 'cod'], 'payment_method'],
            '支払方法が不正' => [['payment_method' => 'paypal'], 'payment_method'],
            '住所の形式が不正' => [['address_id' => 'foo'], 'address_id'],
            '商品IDが空' => [['item_id' => ''], 'item_id'],
            '存在しない商品' => [['item_id' => 99999], 'item_id'],
            '注文トークンが無い' => [['order_token' => ''], 'order_token'],
        ];
    }

    /**
     * 数量の境界値：1個と10個は受け付ける（予約可能数が十分あるとき）
     */
    #[Test]
    public function 数量の境界値は一個と十個まで受け付ける(): void
    {
        [$user, $item] = $this->scenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')->twice()->andReturn(new CheckoutSessionData('cs_a', 'https://checkout.stripe.test/a', 'open'));
            $mock->shouldReceive('retrieveCheckoutSession')->zeroOrMoreTimes()->andReturn(new CheckoutSessionData('cs_a', 'https://checkout.stripe.test/a', 'open'));
            $mock->shouldReceive('expireCheckoutSession')->zeroOrMoreTimes()->andReturn(new CheckoutSessionData('cs_a', null, 'expired'));
        });

        foreach ([1, 10] as $quantity) {
            $this->actingAs($user)
                ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, $quantity))
                ->assertRedirect('https://checkout.stripe.test/a');
            $this->assertSame($quantity, session('pending_reservation')['items'][0]['quantity']);
        }
    }

    /**
     * 予約限定でない商品は、直接送っても予約できない
     */
    #[Test]
    public function 予約限定でない商品は予約できない(): void
    {
        [$user] = $this->scenario();
        $normal = $this->makeItem('人参', 2, 418);
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $normal))
            ->assertSessionHasErrors('item_id');

        $this->assertSame(0, Reservation::count());
    }

    /**
     * 他人の配送先住所・存在しない住所は選べない
     */
    #[Test]
    public function 他人の住所では予約できない(): void
    {
        [$user, $item] = $this->scenario();
        $others = Delivery_address::factory()->create(['delivery_prefecture' => '沖縄県', 'delivery_city' => '那覇市9-9']);
        $this->gatewayNeverCalled();

        foreach (['delivery_'.$others->id, 'delivery_9999', 'user_'.($user->id + 1)] as $addressId) {
            $this->actingAs($user)
                ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 1, ['address_id' => $addressId]))
                ->assertSessionHasErrors(['address_id' => 'お届け先住所が正しくありません']);
        }

        $this->assertNull(session('pending_reservation'));
    }

    /**
     * 送料を計算できない住所（設定にない都道府県）では予約できない
     */
    #[Test]
    public function 送料を計算できない住所では予約できない(): void
    {
        $this->makeAreas();
        $user = $this->makeUser('存在県');
        $item = $this->makeReservable();
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item))
            ->assertSessionHasErrors(['address_id' => 'お届け先住所の都道府県が正しくありません']);
    }

    /**
     * 予約可能数を超える数量は、エラーにして先へ進ませない。例：残り2個（予約可能数3、予約済み1）に3個（AC-8, S-12）
     */
    #[Test]
    public function 予約可能数を超えるとエラーになる(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable(5, 1000, 3);
        $this->makeReservedBy($item, 1);
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->from(route('shop.reservation.index', $item))
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 3))
            ->assertRedirect(route('shop.reservation.index', $item))
            ->assertSessionHasErrors(['quantity' => '予約可能数を超えています（残り2個）']);

        $this->assertSame(1, Reservation::count());
        $this->assertNull(session('pending_reservation'));
    }

    /**
     * 予約可能数ちょうどまでは予約できる（境界値）。エラーでトークンは使われず、数量を直してそのまま送り直せる
     */
    #[Test]
    public function 予約可能数ちょうどまでは予約できる(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable(5, 1000, 3);
        $this->makeReservedBy($item, 1);
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_a', 'https://checkout.stripe.test/a', 'open'));
        });
        $token = $this->newReservationToken($user);

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 3, ['order_token' => $token]))
            ->assertSessionHasErrors('quantity');

        $this->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 2, ['order_token' => $token]))
            ->assertRedirect('https://checkout.stripe.test/a')
            ->assertSessionHasNoErrors();
    }

    /**
     * 予約可能数に達している商品（残り0個）は、予約受付が終了した旨のエラーにする
     */
    #[Test]
    public function 予約可能数に達していれば受付終了のエラーになる(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable(5, 1000, 3);
        $this->makeReservedBy($item, 3);
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 1))
            ->assertSessionHasErrors(['quantity' => 'この商品の予約受付は終了しました']);
    }

    /**
     * 予約可能数が未設定の商品は、予約済みの数量にかかわらず予約できる
     */
    #[Test]
    public function 予約可能数が未設定なら制限なく予約できる(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable(5, 1000, null);
        $this->makeReservedBy($item, 5000);
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_a', 'https://checkout.stripe.test/a', 'open'));
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 10))
            ->assertRedirect('https://checkout.stripe.test/a');
    }

    /**
     * 予約可能数は商品ごとに数える（別の商品の予約は影響しない）
     */
    #[Test]
    public function 予約可能数は商品ごとに数える(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable(5, 1000, 3);
        $other = $this->makeReservable(5, 1000, 3, '別のセット');
        $this->makeReservedBy($other, 3);
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_a', 'https://checkout.stripe.test/a', 'open'));
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 3))
            ->assertRedirect('https://checkout.stripe.test/a');
    }

    /**
     * カートは参照も変更もしない（AC-14）
     */
    #[Test]
    public function カートは変わらない(): void
    {
        [$user, $item] = $this->scenario();
        $carrot = $this->makeItem('人参', 2, 418);
        session(['cart' => [$carrot->id => 3]]);
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->withArgs(fn (PaymentMethod $method, string $customerId, array $lineItems): bool => $lineItems[0]['name'] === '北の恵みセット 10kg' && count($lineItems) === 2)
                ->andReturn(new CheckoutSessionData('cs_a', 'https://checkout.stripe.test/a', 'open'));
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item))
            ->assertRedirect('https://checkout.stripe.test/a');

        $this->assertSame([$carrot->id => 3], session('cart'));
    }

    /**
     * Stripe の決済ページを作れなかったときは、入力を残して予約ページへ戻し、エラーを表示する
     */
    #[Test]
    public function 決済ページを作れなければエラーになる(): void
    {
        [$user, $item] = $this->scenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')->once()->andThrow(new PaymentGatewayException('通信エラー'));
        });

        $this->actingAs($user)
            ->from(route('shop.reservation.index', $item))
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item))
            ->assertRedirect(route('shop.reservation.index', $item))
            ->assertSessionHasErrors('payment')
            ->assertSessionHasInput('delivery_name', '塩谷太郎');

        $this->assertSame(0, Reservation::count());
    }

    /**
     * 同じ注文トークンでの2回目の送信は、予約を作らず、予約ページへ戻して「画面が古くなっています」と案内する（AC-12, S-20）
     */
    #[Test]
    public function 同じ注文トークンの再送信は受け付けない(): void
    {
        [$user, $item] = $this->scenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_a', 'https://checkout.stripe.test/a', 'open'));
            $mock->shouldReceive('retrieveCheckoutSession')->zeroOrMoreTimes();
        });
        $data = $this->reservationData($user, $item);

        $this->actingAs($user)->post(route('shop.reservation.payment'), $data)->assertRedirect('https://checkout.stripe.test/a');

        $this->post(route('shop.reservation.payment'), $data)
            ->assertRedirect(route('shop.reservation.index', $item))
            ->assertSessionHasErrors(['order_token' => CheckoutService::STALE_MESSAGE]);
    }

    /**
     * 購入ページが発行した注文トークンは、予約では使えない（購入と予約でトークンを分けている）
     */
    #[Test]
    public function 購入の注文トークンは予約では使えない(): void
    {
        [$user, $item] = $this->scenario();
        $this->gatewayNeverCalled();
        $purchaseToken = $this->newOrderToken($user);

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 1, ['order_token' => $purchaseToken]))
            ->assertRedirect(route('shop.reservation.index', $item))
            ->assertSessionHasErrors('order_token');

        $this->assertSame(0, Reservation::count());
    }

    /**
     * 作成時点で支払い済みの銀行振込を保存したあと、同じ画面から再送信されても、予約を重複させずサンクスページへ進む
     */
    #[Test]
    public function 保存済みの注文トークンの再送信はサンクスページへ進む(): void
    {
        [$user, $item] = $this->scenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn($this->bankIntent('pi_paid', 'succeeded', 2980, false));
        });
        $data = $this->reservationData($user, $item, 1, ['payment_method' => 'customer_balance']);

        $this->actingAs($user)->post(route('shop.reservation.payment'), $data)->assertRedirect(route('shop.reservation.thanks'));
        $this->post(route('shop.reservation.payment'), $data)
            ->assertRedirect(route('shop.reservation.thanks'))
            ->assertSessionHas(ReservationService::THANKS_FLASH_KEY, true);

        $this->assertSame(1, Reservation::count());
    }

    /**
     * 別のリクエストが処理中（会員ごとのロックを取れない）のときは、予約を作らずエラーを表示する。購入のロックとは別
     */
    #[Test]
    public function 処理中のときはエラーになる(): void
    {
        config(['purchase.begin_lock_wait' => 0]);
        [$user, $item] = $this->scenario();
        $this->gatewayNeverCalled();

        // 購入のロックは予約に影響しない
        $purchaseLock = Cache::lock('purchase-begin:'.$user->id, 30);
        $this->assertTrue($purchaseLock->get());
        $lock = Cache::lock('reservation-begin:'.$user->id, 30);
        $this->assertTrue($lock->get());

        $token = $this->newReservationToken($user);
        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item, 1, ['order_token' => $token]))
            ->assertSessionHasErrors(['payment' => 'ただいまご予約を処理しています。しばらく待ってから、もう一度お試しください']);

        $this->assertSame(0, Reservation::count());
        $lock->release();
        $purchaseLock->release();
    }

    /**
     * 前の予約の Checkout Session が開いたままなら、新しい予約を進める前に失効させる
     */
    #[Test]
    public function 前の予約の決済ページは失効させる(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::Card, 'cs_previous');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_previous')->andReturn(new CheckoutSessionData('cs_previous', 'https://checkout.stripe.test/p', 'open'));
            $mock->shouldReceive('expireCheckoutSession')->once()->with('cs_previous')->andReturn(new CheckoutSessionData('cs_previous', null, 'expired'));
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_new', 'https://checkout.stripe.test/n', 'open'));
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item))
            ->assertRedirect('https://checkout.stripe.test/n');

        $this->assertSame('cs_new', session('pending_reservation')['checkout_session_id']);
    }

    /**
     * 購入の保存待ちの注文は、予約の確定で片づけたり上書きしたりしない
     */
    #[Test]
    public function 購入の保存待ちの注文には触れない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $item = $this->makeReservable();
        $this->putPending($user, $items, PaymentMethod::Card, 'cs_purchase');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldNotReceive('retrieveCheckoutSession');
            $mock->shouldNotReceive('expireCheckoutSession');
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_res', 'https://checkout.stripe.test/r', 'open'));
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item))
            ->assertRedirect('https://checkout.stripe.test/r');

        $this->assertSame('cs_purchase', session('pending_order')['checkout_session_id']);
        $this->assertSame('cs_res', session('pending_reservation')['checkout_session_id']);
    }

    /**
     * 同じ会員の Stripe Customer を購入と予約で使い回す（保存済みの Customer ID があればそれを使う）
     */
    #[Test]
    public function 保存済みのstripe_customerを使い回す(): void
    {
        [$user, $item] = $this->scenario();
        $user->forceFill(['stripe_customer_id' => 'cus_saved'])->save();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->withArgs(fn (PaymentMethod $method, string $customerId): bool => $customerId === 'cus_saved')
                ->andReturn(new CheckoutSessionData('cs_a', 'https://checkout.stripe.test/a', 'open'));
        });

        $this->actingAs($user)
            ->post(route('shop.reservation.payment'), $this->reservationData($user, $item))
            ->assertRedirect('https://checkout.stripe.test/a');
    }
}
