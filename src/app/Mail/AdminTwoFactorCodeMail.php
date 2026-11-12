<?php

namespace App\Mail;

use App\Models\Admin;
use App\Support\MailText;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 管理者のログインの確認コード（二段階認証）のメール
 *
 * 管理者がパスワードでログインしたとき・確認コードを再送するとき、登録のメールアドレスへ送る。
 * 本文は Markdown メール（resources/views/mail/admin-two-factor-code.blade.php）。有効期限（分）は送る側（AdminTwoFactorService）から渡す。
 */
class AdminTwoFactorCodeMail extends Mailable
{
    public function __construct(
        public readonly Admin $admin,
        #[\SensitiveParameter] private readonly string $code,
        private readonly int $expireMinutes,
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
            subject: '【しおや農園】管理画面のログイン確認コード',
        );
    }

    /**
     * 本文（Markdown メール）
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.admin-two-factor-code',
            with: [
                'recipientName' => MailText::safe($this->admin->name),
                'code' => $this->code,
                'expireMinutes' => $this->expireMinutes,
                'senderName' => (string) config('mail.from.name'),
                'senderAddress' => (string) config('mail.from.address'),
            ],
        );
    }
}
