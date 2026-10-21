<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;

/**
 * 決済サービス（Stripe Checkout）のインターフェース
 *
 * テストでは実際のAPIを呼ばないよう、このインターフェースをモックに差し替える。
 */
interface PaymentGatewayInterface
{
    /**
     * Checkout Session を作成する
     *
     * クレジットカード専用（銀行振込は createBankTransferIntent、代金引換は Stripe を通さない）。
     *
     * @param  PaymentMethod  $method  card
     * @param  string  $customerId  Stripe の Customer ID（ensureCustomer で用意したもの）
     * @param  list<array{name: string, unit_amount: int, quantity: int}>  $lineItems  商品ごとの行と送料の行（金額は税込の円）
     * @param  string  $successUrl  支払い後に戻るURL（`{CHECKOUT_SESSION_ID}` を含める）
     * @param  string  $cancelUrl  「戻る」で戻るURL
     *
     * @throws PaymentGatewayException 作成に失敗したとき
     */
    public function createCheckoutSession(
        PaymentMethod $method,
        string $customerId,
        array $lineItems,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSessionData;

    /**
     * Checkout Session を失効させる（それ以降は支払えなくなる）
     *
     * すでに支払い済み（complete）の Session は失効できず、PaymentGatewayException になる。
     *
     * @throws PaymentGatewayException 失効できなかったとき
     */
    public function expireCheckoutSession(string $sessionId): CheckoutSessionData;

    /**
     * 会員の Stripe Customer を用意する（会員ごとに使い回す）
     *
     * 既存の Customer ID があれば取得して再利用する（メールアドレスが変わっていれば更新する）。
     * Stripe 側で削除されている・存在しないときは、新しく作り直す。ID が無ければ新しく作る。
     *
     * @param  string  $email  会員のメールアドレス
     * @param  string|null  $customerId  保存済みの Customer ID
     * @return string 使う Customer ID（保存済みと違うときは、呼び出し側で保存し直す）
     *
     * @throws PaymentGatewayException 作成・取得に失敗したとき
     */
    public function ensureCustomer(string $email, ?string $customerId): string;

    /**
     * Checkout Session を取得する（決済状態の確認用）
     *
     * @throws PaymentGatewayException 取得に失敗したとき
     */
    public function retrieveCheckoutSession(string $sessionId): CheckoutSessionData;

    /**
     * 銀行振込の PaymentIntent を作って確定し、振込先を取得する
     *
     * 会員の Customer で、customer_balance（jp_bank_transfer）の PaymentIntent を confirm する。
     *
     * @param  string  $customerId  Stripe の Customer ID（ensureCustomer で用意したもの）
     * @param  int  $amount  合計金額（円）
     *
     * @throws PaymentGatewayException 作成に失敗したとき
     */
    public function createBankTransferIntent(string $customerId, int $amount): BankTransferIntentData;

    /**
     * 銀行振込の PaymentIntent を取得する（振込先の案内が有効かの確認用）
     *
     * @throws PaymentGatewayException 取得に失敗したとき
     */
    public function retrieveBankTransferIntent(string $paymentIntentId): BankTransferIntentData;

    /**
     * 銀行振込の PaymentIntent をキャンセルする
     *
     * @throws PaymentGatewayException キャンセルに失敗したとき
     */
    public function cancelPaymentIntent(string $paymentIntentId): void;
}
