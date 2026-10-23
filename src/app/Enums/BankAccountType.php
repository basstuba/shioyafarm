<?php

namespace App\Enums;

/**
 * 銀行振込の口座種別（値は Stripe の値のまま purchase_bank_transfers などに保存する）
 */
enum BankAccountType: string
{
    /** 普通 */
    case Futsu = 'futsu';

    /** 当座 */
    case Toza = 'toza';

    /**
     * 日本語表記
     */
    public function label(): string
    {
        return match ($this) {
            self::Futsu => '普通',
            self::Toza => '当座',
        };
    }

    /**
     * 保存された値の日本語表記（未知の値はそのまま返す）
     */
    public static function labelFor(string $value): string
    {
        return self::tryFrom($value)?->label() ?? $value;
    }
}
