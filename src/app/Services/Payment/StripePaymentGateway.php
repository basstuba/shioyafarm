<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use InvalidArgumentException;
use Override;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\HttpClient\CurlClient;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\StripeObject;

/**
 * Stripe を使った決済サービス（カードは Checkout、銀行振込は PaymentIntent）
 */
class StripePaymentGateway implements PaymentGatewayInterface
{
    /**
     * @param  int  $timeout  1回の通信の全体のタイムアウト（秒）
     * @param  int  $connectTimeout  接続のタイムアウト（秒）
     */
    public function __construct(
        private readonly ?string $secret,
        private readonly int $timeout = 20,
        private readonly int $connectTimeout = 5,
    ) {}

    /**
     * Checkout Session を作成する（クレジットカード専用）
     *
     * Adaptive Pricing は無効にし、金額は常に円で表示する。
     */
    #[Override]
    public function createCheckoutSession(
        PaymentMethod $method,
        string $customerId,
        array $lineItems,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSessionData {
        if ($method !== PaymentMethod::Card) {
            throw new InvalidArgumentException('Checkout Session で支払えるのはクレジットカードだけです');
        }

        $client = $this->client();

        $params = [
            'mode' => 'payment',
            'locale' => 'ja',
            // 新しいAPIバージョンでは payment_method_types は使えない。allowed_payment_method_types は
            // 「動的に決まる利用可能な支払方法」を絞り込むフィルターで、選んだ支払方法だけを提示する
            'allowed_payment_method_types' => [$method->value],
            // 他の通貨に換算して表示しない（常に円）
            'adaptive_pricing' => ['enabled' => false],
            'customer' => $customerId,
            'line_items' => array_map(fn (array $line): array => [
                'price_data' => [
                    'currency' => 'jpy',
                    'unit_amount' => $line['unit_amount'],
                    'product_data' => ['name' => $line['name']],
                ],
                'quantity' => $line['quantity'],
            ], $lineItems),
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];

        try {
            $session = $client->checkout->sessions->create($params);
        } catch (ApiErrorException $e) {
            throw new PaymentGatewayException('Checkout Session の作成に失敗しました：'.$e->getMessage(), 0, $e);
        }

        return $this->toSessionData($session);
    }

    /**
     * Checkout Session を失効させる
     */
    #[Override]
    public function expireCheckoutSession(string $sessionId): CheckoutSessionData
    {
        try {
            $session = $this->client()->checkout->sessions->expire($sessionId);
        } catch (ApiErrorException $e) {
            throw new PaymentGatewayException('Checkout Session の失効に失敗しました：'.$e->getMessage(), 0, $e);
        }

        return $this->toSessionData($session);
    }

    /**
     * 会員の Stripe Customer を用意する（再利用・メールの更新・作り直し）
     */
    #[Override]
    public function ensureCustomer(string $email, ?string $customerId): string
    {
        $client = $this->client();

        try {
            if ($customerId !== null && $customerId !== '') {
                try {
                    $customer = $client->customers->retrieve($customerId);
                    /** @var array<string, mixed> $data */
                    $data = $customer->toArray();

                    if (($data['deleted'] ?? false) !== true) {
                        if (($data['email'] ?? null) !== $email) {
                            $client->customers->update($customerId, ['email' => $email]);
                        }

                        return $customerId;
                    }
                } catch (InvalidRequestException $e) {
                    // Stripe 側に存在しないときは作り直す。それ以外のエラーはそのまま扱う
                    if ($e->getStripeCode() !== 'resource_missing') {
                        throw $e;
                    }
                }
            }

            return $client->customers->create(['email' => $email])->id;
        } catch (ApiErrorException $e) {
            throw new PaymentGatewayException('Stripe の Customer の準備に失敗しました：'.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Checkout Session を取得する
     */
    #[Override]
    public function retrieveCheckoutSession(string $sessionId): CheckoutSessionData
    {
        try {
            $session = $this->client()->checkout->sessions->retrieve($sessionId);
        } catch (ApiErrorException $e) {
            throw new PaymentGatewayException('Checkout Session の取得に失敗しました：'.$e->getMessage(), 0, $e);
        }

        return $this->toSessionData($session);
    }

    /**
     * 銀行振込の PaymentIntent を作って確定し、振込先を取得する
     *
     * 会員の Customer で、customer_balance（funding_type=bank_transfer、jp_bank_transfer）で confirm する。
     */
    #[Override]
    public function createBankTransferIntent(string $customerId, int $amount): BankTransferIntentData
    {
        $client = $this->client();

        try {
            $intent = $client->paymentIntents->create([
                'amount' => $amount,
                'currency' => 'jpy',
                'customer' => $customerId,
                'allowed_payment_method_types' => [PaymentMethod::CustomerBalance->value],
                'payment_method_data' => ['type' => PaymentMethod::CustomerBalance->value],
                'payment_method_options' => [
                    'customer_balance' => [
                        'funding_type' => 'bank_transfer',
                        'bank_transfer' => ['type' => 'jp_bank_transfer'],
                    ],
                ],
                'confirm' => true,
            ]);
        } catch (ApiErrorException $e) {
            throw new PaymentGatewayException('銀行振込の支払いの作成に失敗しました：'.$e->getMessage(), 0, $e);
        }

        return $this->toIntentData($intent);
    }

    /**
     * 銀行振込の PaymentIntent を取得する
     */
    #[Override]
    public function retrieveBankTransferIntent(string $paymentIntentId): BankTransferIntentData
    {
        try {
            $intent = $this->client()->paymentIntents->retrieve($paymentIntentId);
        } catch (ApiErrorException $e) {
            throw new PaymentGatewayException('銀行振込の支払いの取得に失敗しました：'.$e->getMessage(), 0, $e);
        }

        return $this->toIntentData($intent);
    }

    /**
     * 銀行振込の PaymentIntent をキャンセルする
     */
    #[Override]
    public function cancelPaymentIntent(string $paymentIntentId): void
    {
        try {
            $this->client()->paymentIntents->cancel($paymentIntentId);
        } catch (ApiErrorException $e) {
            throw new PaymentGatewayException('銀行振込の支払いのキャンセルに失敗しました：'.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Checkout Session から必要な項目（status・URL・payment_status）を取り出す
     */
    private function toSessionData(StripeObject $session): CheckoutSessionData
    {
        /** @var array<string, mixed> $data */
        $data = $session->toArray();

        return new CheckoutSessionData(
            (string) $data['id'],
            is_string($data['url'] ?? null) ? $data['url'] : null,
            (string) ($data['status'] ?? ''),
            is_string($data['payment_status'] ?? null) ? $data['payment_status'] : null,
        );
    }

    /**
     * PaymentIntent から必要な項目（status・金額・振込先）を取り出す
     *
     * 振込先は next_action.display_bank_transfer_instructions の financial_addresses のうち zengin を使う。
     * 振込金額は amount_remaining（無ければ PaymentIntent の金額）。
     */
    private function toIntentData(PaymentIntent $intent): BankTransferIntentData
    {
        /** @var array<string, mixed> $data */
        $data = $intent->toArray();

        $instructions = null;
        $display = $data['next_action']['display_bank_transfer_instructions'] ?? null;
        if (is_array($display)) {
            $zengin = null;
            foreach ($display['financial_addresses'] ?? [] as $address) {
                if (is_array($address) && is_array($address['zengin'] ?? null)) {
                    $zengin = $address['zengin'];
                    break;
                }
            }

            if ($zengin !== null) {
                $instructions = BankTransferInstructions::fromArray([
                    'bank_name' => $zengin['bank_name'] ?? null,
                    'branch_name' => $zengin['branch_name'] ?? null,
                    'account_type' => $zengin['account_type'] ?? null,
                    'account_number' => $zengin['account_number'] ?? null,
                    'account_holder_name' => $zengin['account_holder_name'] ?? null,
                    'amount' => $display['amount_remaining'] ?? $intent->amount,
                ]);
            }
        }

        return new BankTransferIntentData($intent->id, (string) $intent->status, (int) $intent->amount, $instructions);
    }

    /**
     * Stripe のクライアントを作る（キー未設定でも、使うときまではエラーにしない）
     *
     * 通信のタイムアウトは SDK の既定（接続30秒・全体80秒）より短くする。
     * 「注文を確定する」は Stripe への問い合わせを続けて行う間ずっとロックを持つため、1回ごとの待ち時間に上限を付けて、
     * ロックの有効期限（config/purchase.php の begin_lock_ttl）に収まるようにする。
     *
     * @throws PaymentGatewayException キーが設定されていないとき
     */
    private function client(): StripeClient
    {
        if ($this->secret === null || $this->secret === '') {
            throw new PaymentGatewayException('STRIPE_SECRET が設定されていません');
        }

        CurlClient::instance()->setTimeout($this->timeout)->setConnectTimeout($this->connectTimeout);

        return new StripeClient($this->secret);
    }
}
