<?php

namespace App\Mail;

use App\Enums\PaymentMethod;
use App\Models\Reservation;
use App\Support\MailText;
use Illuminate\Mail\Mailables\Content;

/**
 * 予約のサンクスメール
 *
 * 予約を保存した直後に、会員登録のメールアドレスへ送る。金額は保存済みの値を使う。
 * 表示に使う会員・商品・振込先は、ここで Eager Load する。
 */
class ReservationThanksMail extends ThanksMail
{
    public function __construct(public readonly Reservation $reservation)
    {
        $this->paymentMethod = PaymentMethod::from($reservation->payment_method);
        // 振込先は銀行振込のときだけ読む
        $reservation->loadMissing(['user', 'item']);
        if ($this->paymentMethod->usesBankTransfer()) {
            $reservation->loadMissing('reservationBankTransfer');
        }
        $this->to($reservation->user->email);
    }

    protected function subjectLine(): string
    {
        return "【しおや農園】ご予約ありがとうございます（予約番号：{$this->reservation->id}）";
    }

    /**
     * 本文（Markdown メール）
     */
    public function content(): Content
    {
        $reservation = $this->reservation;

        return new Content(
            markdown: 'mail.reservation-thanks',
            with: $this->orderData($reservation, $this->paymentMethod->usesBankTransfer() ? $reservation->reservationBankTransfer : null) + [
                'itemName' => MailText::safe($reservation->item->display_name),
                'quantity' => $reservation->quantity,
                'subtotal' => MailText::yen($reservation->subtotal),
            ],
        );
    }
}
