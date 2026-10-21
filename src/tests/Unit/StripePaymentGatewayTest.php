<?php

namespace Tests\Unit;

use App\Enums\PaymentMethod;
use App\Services\Payment\CheckoutSessionData;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\StripePaymentGateway;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * Stripe の決済サービスのテスト
 *
 * 実際の Stripe API は呼ばず、HTTPクライアントを記録用のものに差し替えて、送るパラメーターを確かめる。
 */
class StripePaymentGatewayTest extends TestCase
{
    /** @var list<array{method: string, url: string, params: array<string, mixed>}> */
    private array $requests = [];

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    /**
     * Stripe へのリクエストを記録し、決まった応答を返すHTTPクライアントに差し替える
     *
     * @param  array<string, array<string, mixed>>  $responses  「メソッド URLの末尾」 => 応答のJSON（`__status` でHTTPステータスを指定できる。省略は200）
     */
    private function fakeStripe(array $responses): void
    {
        $this->requests = [];
        $recorder = function (string $method, string $url, array $params) use ($responses): array {
            $this->requests[] = ['method' => $method, 'url' => $url, 'params' => $params];
            foreach ($responses as $key => $body) {
                [$m, $suffix] = explode(' ', $key, 2);
                if ($m === $method && str_ends_with($url, $suffix)) {
                    $status = $body['__status'] ?? 200;
                    unset($body['__status']);

                    return [json_encode($body), $status, []];
                }
            }

            return [json_encode(['error' => ['message' => '想定外のリクエスト', 'type' => 'invalid_request_error']]), 404, []];
        };

        ApiRequestor::setHttpClient(new class($recorder) implements ClientInterface
        {
            public function __construct(private readonly \Closure $recorder) {}

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                return ($this->recorder)($method, $absUrl, $params);
            }
        });
    }

    /**
     * PaymentIntent の応答（振込先つきなら next_action に display_bank_transfer_instructions を含める）
     *
     * @return array<string, mixed>
     */
    private function intentResponse(string $id, string $status, int $amount, bool $withInstructions): array
    {
        return [
            'id' => $id,
            'object' => 'payment_intent',
            'status' => $status,
            'amount' => $amount,
            'currency' => 'jpy',
            'next_action' => $withInstructions ? [
                'type' => 'display_bank_transfer_instructions',
                'display_bank_transfer_instructions' => [
                    'type' => 'jp_bank_transfer',
                    'amount_remaining' => $amount,
                    'currency' => 'jpy',
                    'reference' => 'ABC123',
                    'financial_addresses' => [[
                        'type' => 'zengin',
                        'supported_networks' => ['zengin'],
                        'zengin' => [
                            'bank_code' => '0001',
                            'bank_name' => 'いろは銀行',
                            'branch_code' => '001',
                            'branch_name' => '中央支店',
                            'account_type' => 'futsu',
                            'account_number' => '1234567',
                            'account_holder_name' => 'ストライプジャパン',
                        ],
                    ]],
                ],
            ] : null,
        ];
    }

    /**
     * 商品ごとの行と送料の行（テスト用）
     *
     * @return list<array{name: string, unit_amount: int, quantity: int}>
     */
    private function lineItems(): array
    {
        return [
            ['name' => 'じゃがいも 2kg', 'unit_amount' => 330, 'quantity' => 2],
            ['name' => '送料', 'unit_amount' => 940, 'quantity' => 1],
        ];
    }

    /**
     * クレジットカード：card の Checkout Session を、商品ごとの行と送料の行つきで作る
     */
    #[Test]
    public function クレジットカードの_checkout_sessionを作る(): void
    {
        $this->fakeStripe(['post /v1/checkout/sessions' => ['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1', 'status' => 'open', 'payment_status' => 'unpaid']]);

        $session = (new StripePaymentGateway('sk_test_dummy'))->createCheckoutSession(
            PaymentMethod::Card,
            'cus_test_1',
            $this->lineItems(),
            'https://example.test/purchase/success?session_id={CHECKOUT_SESSION_ID}',
            'https://example.test/purchase',
        );

        $this->assertSame('cs_test_1', $session->id);
        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_1', $session->url);
        $this->assertFalse($session->isComplete());

        // Customer は作らない
        $this->assertCount(1, $this->requests);
        $params = $this->requests[0]['params'];
        $this->assertSame('payment', $params['mode']);
        $this->assertSame(['card'], $params['allowed_payment_method_types']);
        $this->assertArrayNotHasKey('payment_method_types', $params);
        // 会員ごとに使い回す Customer で決済する（メールアドレスは Customer のものが使われる）
        $this->assertSame('cus_test_1', $params['customer']);
        $this->assertArrayNotHasKey('customer_email', $params);
        $this->assertSame('https://example.test/purchase/success?session_id={CHECKOUT_SESSION_ID}', $params['success_url']);
        $this->assertSame('https://example.test/purchase', $params['cancel_url']);
        // Adaptive Pricing は無効（常に円で表示する）
        // （Stripe SDK は真偽値を 'false' / 'true' の文字列にして送る）
        $this->assertSame(['enabled' => 'false'], $params['adaptive_pricing']);
        $this->assertArrayNotHasKey('payment_method_options', $params);

        $this->assertSame('jpy', $params['line_items'][0]['price_data']['currency']);
        $this->assertSame(330, $params['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame('じゃがいも 2kg', $params['line_items'][0]['price_data']['product_data']['name']);
        $this->assertSame(2, $params['line_items'][0]['quantity']);
        $this->assertSame('送料', $params['line_items'][1]['price_data']['product_data']['name']);
        $this->assertSame(940, $params['line_items'][1]['price_data']['unit_amount']);
    }

    /**
     * Checkout Session で支払えるのはクレジットカードだけ（銀行振込は PaymentIntent を使う）
     */
    #[Test]
    public function checkout_sessionはカード以外では作らない(): void
    {
        $this->fakeStripe([]);

        $this->expectException(\InvalidArgumentException::class);

        (new StripePaymentGateway('sk_test_dummy'))->createCheckoutSession(PaymentMethod::CustomerBalance, 'cus_a', $this->lineItems(), 'https://example.test/s', 'https://example.test/c');
    }

    /**
     * 銀行振込：会員の Customer で、customer_balance（jp_bank_transfer）の PaymentIntent を confirm して振込先を取り出す
     */
    #[Test]
    public function 銀行振込の_payment_intentを作って振込先を取り出す(): void
    {
        $this->fakeStripe([
            'post /v1/payment_intents' => $this->intentResponse('pi_test_1', 'requires_action', 3723, true),
        ]);

        $intent = (new StripePaymentGateway('sk_test_dummy'))->createBankTransferIntent('cus_test_1', 3723);

        // Customer は作らない（呼び出し側で用意した Customer を使う）
        $this->assertCount(1, $this->requests);

        $params = $this->requests[0]['params'];
        $this->assertSame(3723, $params['amount']);
        $this->assertSame('jpy', $params['currency']);
        $this->assertSame('cus_test_1', $params['customer']);
        $this->assertSame(['customer_balance'], $params['allowed_payment_method_types']);
        $this->assertArrayNotHasKey('payment_method_types', $params);
        $this->assertSame('customer_balance', $params['payment_method_data']['type']);
        $this->assertSame('bank_transfer', $params['payment_method_options']['customer_balance']['funding_type']);
        $this->assertSame('jp_bank_transfer', $params['payment_method_options']['customer_balance']['bank_transfer']['type']);
        $this->assertSame('true', $params['confirm']);

        $this->assertSame('pi_test_1', $intent->id);
        $this->assertSame('requires_action', $intent->status);
        $this->assertSame(3723, $intent->amount);
        $this->assertSame('いろは銀行', $intent->instructions->bankName);
        $this->assertSame('中央支店', $intent->instructions->branchName);
        $this->assertSame('futsu', $intent->instructions->accountType);
        $this->assertSame('普通', $intent->instructions->accountTypeLabel());
        $this->assertSame('1234567', $intent->instructions->accountNumber);
        $this->assertSame('ストライプジャパン', $intent->instructions->accountHolderName);
        $this->assertSame(3723, $intent->instructions->amount);
    }

    /**
     * 振込金額は amount_remaining（PaymentIntent の金額と違うときはこちらを案内する）
     */
    #[Test]
    public function 振込金額はamount_remainingを使う(): void
    {
        $response = $this->intentResponse('pi_test_2', 'requires_action', 3723, true);
        $response['next_action']['display_bank_transfer_instructions']['amount_remaining'] = 1000;
        $this->fakeStripe(['get /v1/payment_intents/pi_test_2' => $response]);

        $intent = (new StripePaymentGateway('sk_test_dummy'))->retrieveBankTransferIntent('pi_test_2');

        $this->assertSame(3723, $intent->amount);
        $this->assertSame(1000, $intent->instructions->amount);
    }

    /**
     * 振込先が無い PaymentIntent（キャンセル済みなど）は instructions が null
     */
    #[Test]
    public function 振込先がなければinstructionsはnullになる(): void
    {
        $this->fakeStripe(['get /v1/payment_intents/pi_test_3' => $this->intentResponse('pi_test_3', 'canceled', 3723, false)]);

        $intent = (new StripePaymentGateway('sk_test_dummy'))->retrieveBankTransferIntent('pi_test_3');

        $this->assertSame('canceled', $intent->status);
        $this->assertNull($intent->instructions);
    }

    /**
     * PaymentIntent をキャンセルする
     */
    #[Test]
    public function payment_intentをキャンセルする(): void
    {
        $this->fakeStripe(['post /v1/payment_intents/pi_test_4/cancel' => $this->intentResponse('pi_test_4', 'canceled', 3723, false)]);

        (new StripePaymentGateway('sk_test_dummy'))->cancelPaymentIntent('pi_test_4');

        $this->assertCount(1, $this->requests);
        $this->assertSame('post', $this->requests[0]['method']);
        $this->assertStringEndsWith('/v1/payment_intents/pi_test_4/cancel', $this->requests[0]['url']);
    }

    /**
     * PaymentIntent の取得・キャンセルでの Stripe のエラーは PaymentGatewayException にする
     */
    #[Test]
    public function payment_intentのエラーは例外に変換される(): void
    {
        $this->fakeStripe([]);
        $gateway = new StripePaymentGateway('sk_test_dummy');

        foreach ([
            fn () => $gateway->createBankTransferIntent('cus_a', 100),
            fn () => $gateway->retrieveBankTransferIntent('pi_missing'),
            fn () => $gateway->cancelPaymentIntent('pi_missing'),
        ] as $call) {
            try {
                $call();
                $this->fail('例外が発生するはず');
            } catch (PaymentGatewayException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * Checkout Session を失効させる（POST /v1/checkout/sessions/{id}/expire）
     */
    #[Test]
    public function checkout_sessionを失効させる(): void
    {
        $this->fakeStripe(['post /v1/checkout/sessions/cs_test_9/expire' => ['id' => 'cs_test_9', 'status' => 'expired', 'payment_status' => 'unpaid']]);

        $session = (new StripePaymentGateway('sk_test_dummy'))->expireCheckoutSession('cs_test_9');

        $this->assertSame('expired', $session->status);
        $this->assertFalse($session->isComplete());
        $this->assertSame('post', $this->requests[0]['method']);
    }

    /**
     * 支払い済みの Session は失効できない（Stripe のエラーは PaymentGatewayException）
     */
    #[Test]
    public function 支払い済みのsessionは失効できず例外になる(): void
    {
        $this->fakeStripe(['post /v1/checkout/sessions/cs_paid/expire' => ['__status' => 400, 'error' => ['type' => 'invalid_request_error', 'message' => 'Only Checkout Sessions with a status in open can be expired.']]]);

        $this->expectException(PaymentGatewayException::class);

        (new StripePaymentGateway('sk_test_dummy'))->expireCheckoutSession('cs_paid');
    }

    /**
     * 完了の判定は status が complete で、かつ payment_status が paid のときだけ
     */
    #[Test]
    public function 完了の判定はcompleteかつpaidだけ(): void
    {
        $this->assertTrue((new CheckoutSessionData('cs', null, 'complete', 'paid'))->isComplete());
        $this->assertFalse((new CheckoutSessionData('cs', null, 'complete', 'unpaid'))->isComplete());
        $this->assertFalse((new CheckoutSessionData('cs', null, 'complete', 'no_payment_required'))->isComplete());
        $this->assertFalse((new CheckoutSessionData('cs', null, 'complete'))->isComplete());
        $this->assertFalse((new CheckoutSessionData('cs', null, 'open', 'paid'))->isComplete());
        $this->assertFalse((new CheckoutSessionData('cs', null, 'expired', 'unpaid'))->isComplete());
        $this->assertTrue((new CheckoutSessionData('cs', null, 'open', 'unpaid'))->isOpen());
        $this->assertFalse((new CheckoutSessionData('cs', null, 'complete', 'paid'))->isOpen());
    }

    /**
     * 取得した Checkout Session の payment_status が isComplete に反映される（complete でも unpaid なら未完了）
     */
    #[Test]
    public function 取得したsessionのpayment_statusを見て判定する(): void
    {
        $this->fakeStripe(['get /v1/checkout/sessions/cs_unpaid' => ['id' => 'cs_unpaid', 'status' => 'complete', 'payment_status' => 'unpaid']]);

        $session = (new StripePaymentGateway('sk_test_dummy'))->retrieveCheckoutSession('cs_unpaid');

        $this->assertSame('unpaid', $session->paymentStatus);
        $this->assertFalse($session->isComplete());
    }

    /**
     * Customer ID が無いときは、会員のメールで新しく作る
     */
    #[Test]
    public function customerが無ければ作成する(): void
    {
        $this->fakeStripe(['post /v1/customers' => ['id' => 'cus_new']]);

        $id = (new StripePaymentGateway('sk_test_dummy'))->ensureCustomer('user@example.com', null);

        $this->assertSame('cus_new', $id);
        $this->assertCount(1, $this->requests);
        $this->assertSame('user@example.com', $this->requests[0]['params']['email']);
    }

    /**
     * 保存済みの Customer があり、メールも同じなら、再利用して新しく作らない・更新もしない
     */
    #[Test]
    public function 保存済みのcustomerを再利用する(): void
    {
        $this->fakeStripe(['get /v1/customers/cus_saved' => ['id' => 'cus_saved', 'email' => 'user@example.com']]);

        $id = (new StripePaymentGateway('sk_test_dummy'))->ensureCustomer('user@example.com', 'cus_saved');

        $this->assertSame('cus_saved', $id);
        $this->assertCount(1, $this->requests);
        $this->assertSame('get', $this->requests[0]['method']);
    }

    /**
     * 会員のメールアドレスが変わっているときは、Customer のメールを更新して再利用する
     */
    #[Test]
    public function メールが変わっていればcustomerを更新して再利用する(): void
    {
        $this->fakeStripe([
            'get /v1/customers/cus_saved' => ['id' => 'cus_saved', 'email' => 'old@example.com'],
            'post /v1/customers/cus_saved' => ['id' => 'cus_saved', 'email' => 'new@example.com'],
        ]);

        $id = (new StripePaymentGateway('sk_test_dummy'))->ensureCustomer('new@example.com', 'cus_saved');

        $this->assertSame('cus_saved', $id);
        $this->assertCount(2, $this->requests);
        $this->assertSame('new@example.com', $this->requests[1]['params']['email']);
    }

    /**
     * Stripe 側で削除された Customer（deleted: true）は、新しく作り直す
     */
    #[Test]
    public function 削除されたcustomerは作り直す(): void
    {
        $this->fakeStripe([
            'get /v1/customers/cus_deleted' => ['id' => 'cus_deleted', 'deleted' => true],
            'post /v1/customers' => ['id' => 'cus_recreated'],
        ]);

        $id = (new StripePaymentGateway('sk_test_dummy'))->ensureCustomer('user@example.com', 'cus_deleted');

        $this->assertSame('cus_recreated', $id);
    }

    /**
     * Stripe 側に存在しない Customer（resource_missing）も、新しく作り直す
     */
    #[Test]
    public function 存在しないcustomerは作り直す(): void
    {
        $this->fakeStripe([
            'get /v1/customers/cus_missing' => ['__status' => 404, 'error' => ['type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => 'No such customer']],
            'post /v1/customers' => ['id' => 'cus_recreated'],
        ]);

        $id = (new StripePaymentGateway('sk_test_dummy'))->ensureCustomer('user@example.com', 'cus_missing');

        $this->assertSame('cus_recreated', $id);
    }

    /**
     * resource_missing 以外のエラーのときは、作り直さずに例外にする（別の Customer を作ってしまわない）
     */
    #[Test]
    public function customerの取得で別のエラーなら例外になる(): void
    {
        $this->fakeStripe([
            'get /v1/customers/cus_saved' => ['__status' => 400, 'error' => ['type' => 'invalid_request_error', 'message' => 'Bad request']],
            'post /v1/customers' => ['id' => 'cus_should_not_be_created'],
        ]);

        try {
            (new StripePaymentGateway('sk_test_dummy'))->ensureCustomer('user@example.com', 'cus_saved');
            $this->fail('例外が発生するはず');
        } catch (PaymentGatewayException) {
            $this->assertCount(1, $this->requests);
        }
    }

    /**
     * Checkout Session の状態を取得できる（complete だけが完了）
     */
    #[Test]
    public function checkout_sessionの状態を取得する(): void
    {
        $this->fakeStripe(['get /v1/checkout/sessions/cs_test_3' => ['id' => 'cs_test_3', 'url' => null, 'status' => 'complete', 'payment_status' => 'paid']]);

        $session = (new StripePaymentGateway('sk_test_dummy'))->retrieveCheckoutSession('cs_test_3');

        $this->assertSame('cs_test_3', $session->id);
        $this->assertSame('complete', $session->status);
        $this->assertTrue($session->isComplete());
    }

    /**
     * Stripe がエラーを返したときは PaymentGatewayException にする
     */
    #[Test]
    public function stripeのエラーは例外に変換される(): void
    {
        $this->fakeStripe([]);
        $gateway = new StripePaymentGateway('sk_test_dummy');

        try {
            $gateway->retrieveCheckoutSession('cs_missing');
            $this->fail('例外が発生するはず');
        } catch (PaymentGatewayException $e) {
            $this->assertStringContainsString('取得に失敗', $e->getMessage());
        }

        $this->expectException(PaymentGatewayException::class);
        $gateway->createCheckoutSession(PaymentMethod::Card, 'cus_a', $this->lineItems(), 'https://example.test/s', 'https://example.test/c');
    }

    /**
     * キーが設定されていないときは、通信せずに PaymentGatewayException にする
     */
    #[Test]
    public function キー未設定なら例外になる(): void
    {
        $this->fakeStripe([]);

        foreach ([null, ''] as $secret) {
            try {
                (new StripePaymentGateway($secret))->retrieveCheckoutSession('cs_test');
                $this->fail('例外が発生するはず');
            } catch (PaymentGatewayException $e) {
                $this->assertStringContainsString('STRIPE_SECRET', $e->getMessage());
            }
        }

        $this->assertSame([], $this->requests);
    }
}
