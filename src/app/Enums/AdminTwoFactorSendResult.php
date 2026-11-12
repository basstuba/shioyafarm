<?php

namespace App\Enums;

/**
 * 管理者の確認コードの送信（ログイン時・再送）の結果
 */
enum AdminTwoFactorSendResult
{
    /** 新しいコードをメールで送った */
    case Sent;

    /** 前回の送信から間もないため、送らなかった（再送だけ） */
    case TooSoon;

    /** 再送するコードがない（無効になった・使われた・別の端末で入力待ちが解かれた）。入力待ちは解いた（再送のときだけ） */
    case NoCode;

    /** メールの送信に失敗した（コードは削除済み） */
    case MailFailed;
}
