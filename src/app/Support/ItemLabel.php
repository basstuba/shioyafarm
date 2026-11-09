<?php

namespace App\Support;

/**
 * 商品の表示名（「じゃがいも 2kg」）の組み立て
 *
 * 画面・メール・Stripe の行で同じ表記にするため、ここ1か所で組み立てる。
 */
final class ItemLabel
{
    /**
     * 商品名と重さ（kg）から「商品名 重さkg」にする
     */
    public static function of(string $name, int $weight): string
    {
        return $name.' '.$weight.'kg';
    }
}
