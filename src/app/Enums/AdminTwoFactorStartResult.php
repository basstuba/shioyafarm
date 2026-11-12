<?php

namespace App\Enums;

/**
 * 管理者のログイン（パスワードの確認と確認コードの送信）の結果
 */
enum AdminTwoFactorStartResult
{
    /** メールアドレスかパスワードが正しくない */
    case InvalidCredentials;

    /** 確認コードの間違いが続いていて、ログインできない状態のため、コードを送らなかった（パスワードが正しいときだけ） */
    case Suspended;

    /** メールの送信に失敗した（コードは削除済み） */
    case MailFailed;

    /** 確認コードを送り、入力待ちにした */
    case Sent;
}
