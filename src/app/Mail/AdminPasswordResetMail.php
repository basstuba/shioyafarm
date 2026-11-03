<?php

namespace App\Mail;

use App\Models\Admin;
use App\Support\MailText;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 管理者のパスワード再設定のメール
 *
 * 管理者が再設定メールの送信ページ（/admin/forgot-password）でメールアドレスを入力したとき、登録のメールアドレスへ送る。
 * 本文は Markdown メール（resources/views/mail/admin-password-reset.blade.php）。リンクの有効期限は config('auth.passwords.admins.expire')（分）。
 */
class AdminPasswordResetMail extends Mailable
{
    public function __construct(
        public readonly Admin $admin,
        #[\SensitiveParameter] private readonly string $token,
    ) {
        $this->to($admin->email);
    }

    /**
     * 差出人と件名
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), (string) config('mail.from.name')),
            subject: '【しおや農園】管理画面のパスワード再設定のご案内',
        );
    }

    /**
     * 本文（Markdown メール）
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.admin-password-reset',
            with: [
                'recipientName' => MailText::safe($this->admin->name),
                'resetUrl' => route('admin.password.reset', ['token' => $this->token, 'email' => $this->admin->email]),
                'expireMinutes' => (int) config('auth.passwords.admins.expire'),
                'senderName' => (string) config('mail.from.name'),
                'senderAddress' => (string) config('mail.from.address'),
            ],
        );
    }
}
