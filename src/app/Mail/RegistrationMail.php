<?php

namespace App\Mail;

use App\Models\User;
use App\Support\MailText;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 会員登録完了のメール
 *
 * 会員登録が成功したとき、登録したメールアドレスへ送る。本文は Markdown メール（resources/views/mail/registration.blade.php）。
 * 本文に載せるのはお名前とメールアドレスだけ（パスワード・住所・電話番号は載せない）。
 * 会員が入力した値は MailText::safe() で無害化する。
 */
class RegistrationMail extends Mailable
{
    public function __construct(
        public readonly User $user,
    ) {
        $this->to($user->email);
    }

    /**
     * 差出人と件名
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), (string) config('mail.from.name')),
            subject: '【しおや農園】会員登録ありがとうございます',
        );
    }

    /**
     * 本文（Markdown メール）
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.registration',
            with: [
                'recipientName' => MailText::safe($this->user->last_name.' '.$this->user->first_name),
                'email' => MailText::safe($this->user->email),
                'myPageUrl' => route('shop.mypage.index'),
                'senderName' => (string) config('mail.from.name'),
                'senderAddress' => (string) config('mail.from.address'),
                'contactUrl' => route('top.inquiry'),
            ],
        );
    }
}
