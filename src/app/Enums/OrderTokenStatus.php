<?php

namespace App\Enums;

/**
 * 注文トークンの状態
 */
enum OrderTokenStatus: string
{
    /** 発行済みで、まだ使っていない */
    case Issued = 'issued';

    /** 「注文を確定する」で使用済み（注文は保存されていない、または保存したか不明） */
    case Used = 'used';

    /** 「注文を確定する」で使用済みで、購入データを保存した */
    case Completed = 'completed';
}
