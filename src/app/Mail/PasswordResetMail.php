<?php

namespace App\Mail;

use App\Models\User;
use App\Support\MailText;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * パスワード再設定のメール
 *
 * 会員が再設定メールの送信ページでメールアドレスを入力したとき、登録のメールアドレスへ送る。
 * 本文は Markdown メール（resources/views/mail/password-reset.blade.php）。リンクの有効期限は config('auth.passwords.users.expire')（分）。
 */
class PasswordResetMail extends Mailable
{
    public function __construct(
        public readonly User $user,
        #[\SensitiveParameter] private readonly string $token,
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
            subject: '【しおや農園】パスワード再設定のご案内',
        );
    }

    /**
     * 本文（Markdown メール）
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.password-reset',
            with: [
                'recipientName' => MailText::safe($this->user->last_name.' '.$this->user->first_name),
                'resetUrl' => route('password.reset', ['token' => $this->token, 'email' => $this->user->email]),
                'expireMinutes' => (int) config('auth.passwords.users.expire'),
                'senderName' => (string) config('mail.from.name'),
                'senderAddress' => (string) config('mail.from.address'),
                'contactUrl' => route('top.inquiry'),
            ],
        );
    }
}
