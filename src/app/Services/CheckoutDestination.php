<?php

namespace App\Services;

/**
 * 「注文を確定する」の移動先
 */
final readonly class CheckoutDestination
{
    public function __construct(
        /** 移動先のURL */
        public string $url,
        /** 購入データを保存済みか（保存済みなら、サンクスページを1回だけ表示する合図を付けて移動する） */
        public bool $completed = false,
    ) {}
}
