<?php

namespace App\Services;

use App\Models\Item;

/**
 * カートの1行（商品・数量・金額）
 */
final readonly class CartLine
{
    /** 金額（単価×数量、税込） */
    public int $amount;

    public function __construct(
        public Item $item,
        public int $quantity,
    ) {
        $this->amount = $item->price * $quantity;
    }
}
