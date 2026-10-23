<?php

namespace App\Mail;

use App\Enums\PaymentMethod;
use App\Models\Purchase_history;
use App\Models\Purchase_history_item;
use App\Support\MailText;
use Illuminate\Mail\Mailables\Content;

/**
 * 購入のサンクスメール
 *
 * 購入履歴を保存した直後に、会員登録のメールアドレスへ送る。金額は保存済みの値を使う。
 * 表示に使う会員・商品・振込先は、ここで Eager Load する（商品の数だけ問い合わせない）。
 */
class PurchaseThanksMail extends ThanksMail
{
    public function __construct(public readonly Purchase_history $purchase)
    {
        // 会員は保存した側が持っているもの（User）をそのまま使えるよう、無ければここで読み込む
        $this->paymentMethod = PaymentMethod::from($purchase->payment_method);
        // 振込先は銀行振込のときだけ読む
        $purchase->loadMissing(['user', 'purchaseHistoryItems.item']);
        if ($this->paymentMethod->usesBankTransfer()) {
            $purchase->loadMissing('purchaseBankTransfer');
        }
        $this->to($purchase->user->email);
    }

    protected function subjectLine(): string
    {
        return "【しおや農園】ご注文ありがとうございます（注文番号：{$this->purchase->id}）";
    }

    /**
     * 本文（Markdown メール）
     */
    public function content(): Content
    {
        $purchase = $this->purchase;
        $subtotal = (int) $purchase->purchaseHistoryItems->sum('subtotal');

        return new Content(
            markdown: 'mail.purchase-thanks',
            with: $this->orderData($purchase, $this->paymentMethod->usesBankTransfer() ? $purchase->purchaseBankTransfer : null) + [
                // 商品ごとの行（「じゃがいも 2kg × 2　　¥660」）。HTML版は1つの段落の中で <br> 区切り
                'itemLines' => $this->joinLines($purchase->purchaseHistoryItems
                    ->map(fn (Purchase_history_item $line): string => MailText::safe($line->item->display_name).' × '.$line->quantity.'　　'.MailText::yen($line->subtotal))
                    ->all()),
                'subtotal' => MailText::yen($subtotal),
                // 代引手数料の行は代金引換のときだけ出す
                'codFee' => $this->paymentMethod === PaymentMethod::Cod ? MailText::yen($purchase->cod_fee) : null,
            ],
        );
    }
}
