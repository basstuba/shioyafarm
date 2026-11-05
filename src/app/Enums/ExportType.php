<?php

namespace App\Enums;

/**
 * 管理画面のエクスポートの種類（POST /admin/export の type）
 *
 * 予約の出力は、予約一覧の実装のときに足す。
 */
enum ExportType: string
{
    /** 注文一覧 */
    case Purchase = 'purchase';
}
