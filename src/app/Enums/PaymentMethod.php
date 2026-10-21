<?php

namespace App\Enums;

/**
 * 支払方法（値は purchase_histories.payment_method に保存する）
 */
enum PaymentMethod: string
{
    /** クレジットカード（Stripe Checkout） */
    case Card = 'card';

    /** 銀行振込（Stripe の PaymentIntent の customer_balance。振込先を自サイトで案内する） */
    case CustomerBalance = 'customer_balance';

    /** 代金引換（Stripeを通さない。手数料が加算される） */
    case Cod = 'cod';

    /**
     * 値の一覧（バリデーションの in ルール用）
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Stripe Checkout（Stripeのページ）で支払う方法かどうか
     */
    public function usesCheckout(): bool
    {
        return $this === self::Card;
    }

    /**
     * 振込先のご案内ページを経由する銀行振込かどうか（Stripe Checkout は使わず、PaymentIntent を作る）
     */
    public function usesBankTransfer(): bool
    {
        return $this === self::CustomerBalance;
    }
}
