<?php

namespace App\Services;

/**
 * 注文の金額の内訳（小計・送料・代引手数料・合計）
 */
final readonly class OrderSummary
{
    /** 合計金額（小計 + 送料 + 代引手数料） */
    public int $total;

    public function __construct(
        public int $subtotal,
        public int $totalWeight,
        public int $shippingFee,
        public int $codFee,
    ) {
        $this->total = $subtotal + $shippingFee + $codFee;
    }
}
