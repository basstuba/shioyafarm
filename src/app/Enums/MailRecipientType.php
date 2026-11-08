<?php

namespace App\Enums;

/**
 * 管理画面のメール作成の宛先の種類（会員宛て・お問い合わせへの返信）
 *
 * 値はメール作成ページの送信フォームの hidden（type）で送る。宛先のメールアドレスは送らず、種類とIDからサーバーで引き直す。
 */
enum MailRecipientType: string
{
    /** 会員宛て（会員登録のメールアドレス。退会済みの会員にも送れる） */
    case User = 'user';

    /** お問い合わせへの返信（お問い合わせのメールアドレス） */
    case Inquiry = 'inquiry';

    /**
     * メール作成ページのルート名
     */
    public function createRoute(): string
    {
        return match ($this) {
            self::User => 'admin.mail.user',
            self::Inquiry => 'admin.mail.inquiry',
        };
    }

    /**
     * メール作成ページのルートのパラメーター名
     */
    public function routeParameter(): string
    {
        return match ($this) {
            self::User => 'user_id',
            self::Inquiry => 'inquiry_id',
        };
    }

    /**
     * 送信後・「一覧へ戻る」で戻る一覧のルート名
     */
    public function indexRoute(): string
    {
        return match ($this) {
            self::User => 'admin.users.index',
            self::Inquiry => 'admin.inquiries.index',
        };
    }

    /**
     * 一覧の検索結果のルート名（検索条件つきで戻すときに、戻り先として認めるパス）
     */
    public function searchRoute(): string
    {
        return match ($this) {
            self::User => 'admin.users.search',
            self::Inquiry => 'admin.inquiries.search',
        };
    }
}
