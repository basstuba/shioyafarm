<?php

namespace App\Mail;

use App\Enums\BankAccountType;
use App\Enums\PaymentMethod;
use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use App\Models\Reservation;
use App\Models\Reservation_bank_transfer;
use App\Models\User;
use App\Support\MailText;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * 購入・予約のサンクスメールの共通部分（差出人・宛名・お問い合わせページのURL・お振込先の判定）
 *
 * 本文は Markdown メール（resources/views/mail）。ヘッダー・フッター・色は resources/views/vendor/mail にある。
 * 差出人と署名の名前・メールアドレスは、config('mail.from')（MAIL_FROM_ADDRESS / MAIL_FROM_NAME。未設定のときの既定値は config/mail.php）から出す。
 */
abstract class ThanksMail extends Mailable
{
    /** お支払方法（購入履歴・予約の payment_method。コンストラクタで設定する） */
    protected PaymentMethod $paymentMethod;

    /**
     * 件名
     */
    abstract protected function subjectLine(): string;

    /**
     * 差出人と件名
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->senderAddress(), $this->senderName()),
            subject: $this->subjectLine(),
        );
    }

    /**
     * 宛名（会員の「姓 名」）
     */
    protected function recipientName(User $user): string
    {
        return $user->last_name.' '.$user->first_name;
    }

    /**
     * 本文の「■ お振込先」に出す値（銀行振込以外は null）
     *
     * 振込先の行があり、5項目が null のとき（PaymentIntent を作った時点で顧客残高から支払い済み）は、口座の項目を出さず、支払い済みの案内にする（paid）。
     * 銀行振込なのに振込先の行が無いときは（通常は起きない）、支払い済みかどうかがわからないため、セクションを出さずに警告をログに残す。
     * 会員が入力した値は MailText::safe() で無害化する。
     *
     * @return array{paid: bool, bankName: HtmlString, branchName: HtmlString, accountType: HtmlString, accountNumber: HtmlString, accountHolderName: HtmlString, amount: string}|null
     */
    protected function bankTransferData(PaymentMethod $method, Purchase_bank_transfer|Reservation_bank_transfer|null $row, Purchase_history|Reservation $order): ?array
    {
        if (! $method->usesBankTransfer()) {
            return null;
        }

        if ($row === null) {
            Log::warning('銀行振込の振込先のデータがないため、メールにお振込先を載せませんでした', ['saved' => $order::class, 'id' => $order->id]);

            return null;
        }

        return [
            'paid' => $row->bank_name === null,
            'bankName' => MailText::safe($row->bank_name),
            'branchName' => MailText::safe($row->branch_name),
            'accountType' => MailText::safe(BankAccountType::labelFor((string) $row->account_type)),
            'accountNumber' => MailText::safe($row->account_number),
            'accountHolderName' => MailText::safe($row->account_holder_name),
            'amount' => MailText::yen($row->amount),
        ];
    }

    /**
     * 購入・予約で共通の本文の値（宛名・番号・日時・お支払方法・お届け先・お振込先・差出人・お問い合わせページのURL）
     *
     * @return array<string, mixed>
     */
    protected function orderData(Purchase_history|Reservation $order, Purchase_bank_transfer|Reservation_bank_transfer|null $bankTransfer): array
    {
        $method = $this->paymentMethod;

        return $this->commonData() + [
            'recipientName' => MailText::safe($this->recipientName($order->user)),
            'orderNumber' => $order->id,
            'orderedAt' => MailText::dateTime($order->created_at),
            'paymentLabel' => $method->label(),
            'total' => MailText::yen($order->total_price),
            'shippingFee' => MailText::yen($order->shipping_fee),
            'deliveryName' => MailText::safe($order->delivery_name),
            'deliveryAddress' => MailText::safe($order->delivery_prefecture.$order->delivery_city),
            'deliveryTell' => MailText::safe($order->delivery_tell),
            'bankTransfer' => $this->bankTransferData($method, $bankTransfer, $order),
        ];
    }

    /**
     * 複数の行を、HTML版では <br> で区切って1つの段落に、テキスト版では1行ずつに並べる
     *
     * 各行は MailText::safe() などで無害化済みの文字列を渡す。
     *
     * @param  list<HtmlString|string>  $lines
     */
    protected function joinLines(array $lines): HtmlString
    {
        return new HtmlString(implode("<br>\n", array_map(strval(...), $lines)));
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
     * 本文の共通の値（差出人・お問い合わせページのURL）
     *
     * @return array{senderName: string, senderAddress: string, contactUrl: string}
     */
    protected function commonData(): array
    {
        return [
            'senderName' => $this->senderName(),
            'senderAddress' => $this->senderAddress(),
            'contactUrl' => route('top.inquiry'),
        ];
    }
}
