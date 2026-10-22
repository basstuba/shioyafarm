<?php

namespace App\Enums;

/**
 * 銀行振込の PaymentIntent をキャンセルした結果
 */
enum BankTransferCancelResult
{
    /** キャンセルできた（またはキャンセルするものがなかった・すでにキャンセル済み） */
    case Cancelled;

    /** キャンセルに失敗し、確認したところすでに入金済み・入金処理中だった（購入データを保存する） */
    case Paid;

    /** キャンセルに失敗し、状態も確認できなかった・キャンセルも入金もされていない（保存待ちの注文は変えない） */
    case Failed;
}
