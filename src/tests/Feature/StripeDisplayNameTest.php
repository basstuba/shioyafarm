<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Services\Payment\PaymentGatewayInterface;
use PHPUnit\Framework\Attributes\Test;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Tests\TestCase;

/**
 * Stripe Checkout に出す店名の設定
 *
 * Stripe の通信は記録用のHTTPクライアントに差し替え、コンテナから作った決済サービスが送るパラメーターで確かめる。
 */
class StripeDisplayNameTest extends TestCase
{
    /** @var list<array<string, mixed>> Checkout Session の作成で送られたパラメーター */
    private array $sessionParams = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionParams = [];
        config(['services.stripe.secret' => 'sk_test_dummy']);
        ApiRequestor::setHttpClient(new class($this->sessionParams) implements ClientInterface
        {
            /** @param list<array<string, mixed>> $sessionParams */
            public function __construct(private array &$sessionParams) {}

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                $this->sessionParams[] = $params;

                return [json_encode(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1', 'status' => 'open', 'payment_status' => 'unpaid']), 200, []];
            }
        });
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        CurlClient::instance()->setTimeout(CurlClient::DEFAULT_TIMEOUT)->setConnectTimeout(CurlClient::DEFAULT_CONNECT_TIMEOUT);
        parent::tearDown();
    }

    /**
     * Checkout Session を作る
     */
    private function createSession(): void
    {
        $this->app->make(PaymentGatewayInterface::class)->createCheckoutSession(
            PaymentMethod::Card,
            'cus_test_1',
            [['name' => 'じゃがいも 2kg', 'unit_amount' => 330, 'quantity' => 1]],
            'https://example.test/success',
            'https://example.test/cancel',
        );
    }

    /**
     * 店名は「しおや農園」の固定値で、Checkout Session の作成時に branding_settings.display_name として送られる
     */
    #[Test]
    public function 店名はしおや農園でcheckout_sessionの作成時に送られる(): void
    {
        $this->assertSame('しおや農園', config('services.stripe.display_name'));

        $this->createSession();

        $this->assertCount(1, $this->sessionParams);
        $this->assertSame(['display_name' => 'しおや農園'], $this->sessionParams[0]['branding_settings']);
    }

    /**
     * 設定を別の値にすると、送られる店名も変わる
     */
    #[Test]
    public function 設定を変えると送られる店名も変わる(): void
    {
        config(['services.stripe.display_name' => 'テスト店名']);

        $this->createSession();

        $this->assertSame(['display_name' => 'テスト店名'], $this->sessionParams[0]['branding_settings']);
    }
}
