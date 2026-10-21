<?php

namespace App\Services\Payment;

/**
 * 決済サービスの Checkout Session の情報（必要な項目だけ）
 */
final readonly class CheckoutSessionData
{
    public function __construct(
        public string $id,
        public ?string $url,
        /** Checkout Session の status（open / complete / expired） */
        public string $status,
        /** Checkout Session の payment_status（paid / unpaid / no_payment_required） */
        public ?string $paymentStatus = null,
    ) {}

    /**
     * 支払いが済んでいる（status が complete で、payment_status が paid）かどうか
     *
     * カードの Checkout だけが対象。銀行振込は Checkout を使わず、PaymentIntent で別に確認する。
     */
    public function isComplete(): bool
    {
        return $this->status === 'complete' && $this->paymentStatus === 'paid';
    }

    /**
     * まだ支払える（開いている）かどうか
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
