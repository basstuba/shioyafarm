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
     * 予約で選べる支払方法の値の一覧（予約は先払いのため、代金引換は選べない）
     *
     * @return list<string>
     */
    public static function reservationValues(): array
    {
        return [self::Card->value, self::CustomerBalance->value];
    }

    /**
     * 画面・メールに表示する名前
     */
    public function label(): string
    {
        return match ($this) {
            self::Card => 'クレジットカード',
            self::CustomerBalance => '銀行振込',
            self::Cod => '代金引換',
        };
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
