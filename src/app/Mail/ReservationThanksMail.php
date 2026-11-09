<?php

namespace App\Mail;

use App\Enums\PaymentMethod;
use App\Models\Reservation;
use App\Support\MailText;
use Illuminate\Mail\Mailables\Content;
use Override;

/**
 * 予約のサンクスメール
 *
 * 予約を保存した直後に、会員登録のメールアドレスへ送る。金額は保存済みの値を使う。
 * 表示に使う会員・振込先は、作る側（ReservationService）が Eager Load しておく。商品名・重さは予約した時点の値（item_name・item_weight）。
 */
class ReservationThanksMail extends ThanksMail
{
    public function __construct(public readonly Reservation $reservation)
    {
        $this->paymentMethod = PaymentMethod::from($reservation->payment_method);
        $this->to($reservation->user->email);
    }

    #[Override]
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
                'itemName' => MailText::safe($reservation->itemLabel()),
                'quantity' => $reservation->quantity,
                'subtotal' => MailText::yen($reservation->subtotal),
            ],
        );
    }
}
