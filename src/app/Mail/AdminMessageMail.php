<?php

namespace App\Mail;

use App\Services\MailRecipient;
use App\Support\MailText;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 管理画面から送るメール（会員宛て・お問い合わせへの返信）
 *
 * 件名は、管理者が入力したタイトルの先頭に「【しおや農園】」を付ける。本文は管理者が入力した文章で、宛名・署名は自動で付けない。
 * 入力した文字はエスケープし（MailText::multiline）、改行はそのまま出す。サンクスメールと同じ Markdown メール（resources/views/mail/admin-message.blade.php）。
 * お問い合わせへの返信だけ、本文の下に元のお問い合わせ（問い合わせ日・内容）を引用として付ける。
 * 差出人は config('mail.from')。送ったメールの控えを、差出人と同じ農園のアドレスに BCC で送る（お客様には見えない。送信履歴は保存しない）。
 */
class AdminMessageMail extends Mailable
{
    /** 件名の先頭に自動で付ける文字 */
    public const string SUBJECT_PREFIX = '【しおや農園】';

    public function __construct(
        public readonly MailRecipient $recipient,
        public readonly string $title,
        public readonly string $body,
    ) {
        $this->to($recipient->email);
    }

    /**
     * 差出人・BCC（控え）と件名（タイトルの改行は半角スペースにする。ヘッダーを増やされないため）
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), (string) config('mail.from.name')),
            bcc: [new Address((string) config('mail.from.address'), (string) config('mail.from.name'))],
            subject: self::SUBJECT_PREFIX.(preg_replace('/[\r\n]+/', ' ', $this->title) ?? $this->title),
        );
    }

    /**
     * 本文（Markdown メール）
     */
    public function content(): Content
    {
        $recipient = $this->recipient;

        return new Content(
            markdown: 'mail.admin-message',
            with: [
                'messageBody' => MailText::multiline($this->body),
                // 元のお問い合わせの引用（お問い合わせへの返信のときだけ。会員宛てには付けない）
                'quote' => $recipient->inquiryDetail === null ? null : [
                    'date' => $recipient->inquiredAt?->format('Y/m/d') ?? '',
                    'detail' => MailText::multiline($recipient->inquiryDetail),
                ],
            ],
        );
    }
}
