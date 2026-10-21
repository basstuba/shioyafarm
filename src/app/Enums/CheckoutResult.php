<?php

namespace App\Enums;

/**
 * 購入完了処理（カードの GET /purchase/success、代引の購入完了、銀行振込の「振込み」）の結果
 */
enum CheckoutResult
{
    /** 購入データを保存した */
    case Completed;

    /** 同時に届いた別のリクエストがすでに保存していた（二重に保存しない） */
    case AlreadyCompleted;

    /** 保存待ちの注文がない（再読み込み・直接アクセス・別の会員の注文） */
    case NoPendingOrder;

    /** StripeのセッションIDが、保存待ちの注文のものと一致しない */
    case SessionMismatch;

    /** Stripeに問い合わせた結果、まだ支払われていない（決済が完了していない・キャンセル済み・金額の不一致など） */
    case NotPaid;

    /** Stripeに問い合わせられなかった（通信エラーなど）。支払い済みかどうかは不明なので、再決済はさせず、保存待ちの注文を残す */
    case VerificationFailed;

    /** 同時に届いた別のリクエストが処理中で、待っても終わらなかった */
    case Busy;

    /** 銀行振込の注文で、購入データの保存は案内ページの「振込み」（POST）でだけ行う */
    case UseBankTransferConfirmation;

    /**
     * 購入データが保存済み（今回または同時リクエストで）かどうか
     */
    public function isSaved(): bool
    {
        return $this === self::Completed || $this === self::AlreadyCompleted;
    }
}
