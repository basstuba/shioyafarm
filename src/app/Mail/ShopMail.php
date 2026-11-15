<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

/**
 * しおや農園から送るメールの共通部分（差出人・件名の先頭・署名・お問い合わせページのURL）
 *
 * 差出人と署名の名前・メールアドレスは、config('mail.from')（MAIL_FROM_ADDRESS / MAIL_FROM_NAME。未設定のときの既定値は config/mail.php）から出す。
 * 本文は Markdown メール（resources/views/mail）。ヘッダー・フッター・色は resources/views/vendor/mail にある。
 */
abstract class ShopMail extends Mailable
{
    /** 件名の先頭に付ける文字 */
    public const string SUBJECT_PREFIX = '【しおや農園】';

    /**
     * 差出人（農園のアドレス）と件名の封筒
     *
     * 件名は、先頭の SUBJECT_PREFIX を付けたものを渡す。$bccSender を true にすると、差出人と同じ農園のアドレスに控えを BCC で送る（お客様には見えない）。
     */
    protected function shopEnvelope(string $subject, bool $bccSender = false): Envelope
    {
        return new Envelope(
            from: $this->senderAddressObject(),
            bcc: $bccSender ? [$this->senderAddressObject()] : [],
            subject: $subject,
        );
    }

    /**
     * 差出人の名前（署名にも使う）
     */
    protected function senderName(): string
    {
        return (string) config('mail.from.name');
    }

    /**
     * 差出人のメールアドレス（署名にも使う）
     */
    protected function senderAddress(): string
    {
        return (string) config('mail.from.address');
    }

    /**
     * 本文の署名の値（差出人の名前・メールアドレス）
     *
     * @return array{senderName: string, senderAddress: string}
     */
    protected function signatureData(): array
    {
        return [
            'senderName' => $this->senderName(),
            'senderAddress' => $this->senderAddress(),
        ];
    }

    /**
     * 本文の共通の値（署名・お問い合わせページのURL）
     *
     * @return array{senderName: string, senderAddress: string, contactUrl: string}
     */
    protected function commonData(): array
    {
        return $this->signatureData() + [
            'contactUrl' => route('top.inquiry'),
        ];
    }

    /**
     * 差出人の Address
     */
    private function senderAddressObject(): Address
    {
        return new Address($this->senderAddress(), $this->senderName());
    }
}
