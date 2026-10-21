<?php

namespace App\Enums;

/**
 * カートに商品を追加した結果
 */
enum CartAddResult
{
    /** 追加できた */
    case Added;

    /** 1商品の上限に達していて、追加しなかった */
    case LimitReached;

    /** カートに入れられない商品（存在しない・予約限定）で、追加しなかった */
    case Rejected;
}
