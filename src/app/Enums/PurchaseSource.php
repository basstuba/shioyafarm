<?php

namespace App\Enums;

/**
 * 購入の入り口（カートから購入するか、商品詳細からその商品だけを購入するか）
 */
enum PurchaseSource: string
{
    /** カートの中身を購入する（購入完了後にカートを空にする） */
    case Cart = 'cart';

    /** 商品詳細から1商品を購入する（カートは変えない） */
    case Item = 'item';

    /**
     * 値の一覧（バリデーションの in ルール用）
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
