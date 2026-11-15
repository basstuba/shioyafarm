<?php

namespace App\Support;

use App\Services\OrderSummary;
use App\Services\Payment\BankTransferInstructions;
use App\Services\PendingOrder;
use LogicException;

/**
 * 「振込先のご案内」ページ（shop/bank_transfer。購入と予約で共通）の表示用の値
 *
 * 購入と予約で違う「戻る」「振込み」の送信先と文言に、振込先の項目と金額の内訳を加えて、コントローラーがビューへ渡す。
 */
final class BankTransferPage
{
    /**
     * 購入のときのビューの値
     *
     * @return array{order: PendingOrder, rows: array<string, array{string, string}>, summary: OrderSummary, cancelRoute: string, confirmRoute: string, orderHeading: string, shippingNotice: string}
     */
    public static function purchase(PendingOrder $order): array
    {
        return [
            ...self::common($order),
            'cancelRoute' => 'shop.checkout.bank-transfer.cancel',
            'confirmRoute' => 'shop.checkout.bank-transfer.confirm',
            'orderHeading' => 'ご注文内容',
            'shippingNotice' => '下記の口座へお振込みください。お振込みの確認後、'.config('shipping.ship_days_text').'以内に発送いたします。',
        ];
    }

    /**
     * 予約のときのビューの値
     *
     * @return array{order: PendingOrder, rows: array<string, array{string, string}>, summary: OrderSummary, cancelRoute: string, confirmRoute: string, orderHeading: string, shippingNotice: string}
     */
    public static function reservation(PendingOrder $order): array
    {
        return [
            ...self::common($order),
            'cancelRoute' => 'shop.reservation.bank-transfer.cancel',
            'confirmRoute' => 'shop.reservation.bank-transfer.confirm',
            'orderHeading' => 'ご予約内容',
            'shippingNotice' => '下記の口座へお振込みください。お振込みの確認後、商品は'.config('shipping.reservation_ship_period_text').'に発送いたします。',
        ];
    }

    /**
     * 購入と予約で共通の値（注文・振込先の項目・金額の内訳）
     *
     * 振込先は、銀行振込の案内を表示できる注文（CheckoutService::getBankTransferOrder）にだけある。
     *
     * @return array{order: PendingOrder, rows: array<string, array{string, string}>, summary: OrderSummary}
     */
    private static function common(PendingOrder $order): array
    {
        $bankTransfer = $order->bankTransfer ?? throw new LogicException('振込先のない注文では「振込先のご案内」を表示できません。');

        return [
            'order' => $order,
            'rows' => self::rows($bankTransfer),
            'summary' => self::summary($order),
        ];
    }

    /**
     * 振込先の項目を返す（項目名 => [値, dd に付けるクラス]）
     *
     * @return array<string, array{string, string}>
     */
    private static function rows(BankTransferInstructions $bankTransfer): array
    {
        return [
            '銀行名' => [$bankTransfer->bankName, 'text-xl break-words'],
            '支店名' => [$bankTransfer->branchName, 'text-xl break-words'],
            '口座種別' => [$bankTransfer->accountTypeLabel(), 'text-xl'],
            '口座番号' => [$bankTransfer->accountNumber, 'text-xl'],
            '口座名義' => [$bankTransfer->accountHolderName, 'text-xl break-words'],
            '振込金額' => [number_format($bankTransfer->amount).'円', 'font-maru text-2xl text-primary-text'],
        ];
    }

    /**
     * 金額のカード（livewire/partials/order-summary）に渡す内訳を返す
     *
     * 購入ページと同じ OrderSummary を使う。銀行振込なので代引手数料は 0 円で行は出ない。
     */
    private static function summary(PendingOrder $order): OrderSummary
    {
        return new OrderSummary($order->subtotal, 0, $order->shippingFee, $order->codFee);
    }
}
