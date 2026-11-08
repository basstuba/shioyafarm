<?php

namespace App\Enums;

/**
 * 管理画面のメール送信の結果
 */
enum MailSendResult
{
    /** 送信した */
    case Sent;

    /** 同じ宛先に同じ内容を直前に送っているため、送らなかった（二重送信。送信済みとして扱う） */
    case AlreadySent;

    /** 宛先（会員・お問い合わせ）が存在しない */
    case NotFound;

    /** メールサーバーとの通信などで送信に失敗した（ログに残してある） */
    case Failed;
}
