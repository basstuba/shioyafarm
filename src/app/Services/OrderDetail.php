<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use App\Models\Purchase_history_item;
use App\Models\Reservation;
use App\Models\Reservation_bank_transfer;
use Carbon\CarbonInterface;

/**
 * マイページの購入履歴・予約履歴の詳細（モーダルに表示する内容）
 *
 * 金額は保存済みの値を使う。購入履歴は Purchase_history_items、予約は予約そのものを1行の明細にする。
 */
final readonly class OrderDetail
{
    /**
     * 銀行振込のときは $bankTransfer にお振込先を持つ（paid が true のときは顧客残高で支払い済み）。取消済みのときは持たない（振込先を出さない）。
     *
     * @param  list<array{name: string, price: int, quantity: int, subtotal: int}>  $lines  商品の明細
     * @param  int|null  $codFee  代引手数料（代金引換のときだけ）
     * @param  int|null  $orderId  注文管理番号・予約管理番号（購入履歴・予約のID。管理画面の詳細だけ）
     * @param  string|null  $ordererName  注文者・予約者の姓名（管理画面の詳細だけ。会員を Eager Load 済みのときだけ入る）
     * @param  bool  $shipped  発送済みか
     * @param  CarbonInterface|null  $canceledAt  取消日時（取り消されていないときは null）
     * @param  PaymentMethod|null  $paymentMethod  支払方法（保存された値が決められた値でないときは null）
     */
    public function __construct(
        public ?CarbonInterface $orderedAt,
        public array $lines,
        public int $shippingFee,
        public ?int $codFee,
        public int $total,
        public string $deliveryName,
        public string $deliveryAddress,
        public string $deliveryTell,
        public string $paymentLabel,
        public ?BankTransferInfo $bankTransfer,
        public ?int $orderId = null,
        public ?string $ordererName = null,
        public bool $shipped = false,
        public ?CarbonInterface $canceledAt = null,
        public ?PaymentMethod $paymentMethod = null,
    ) {}

    /**
     * 取消済みか
     */
    public function isCanceled(): bool
    {
        return $this->canceledAt !== null;
    }

    /**
     * お客様に取消の案内（お問い合わせフォームから連絡する）を出す注文・予約か（未発送で未取消のもの）
     */
    public function canRequestCancel(): bool
    {
        return ! $this->shipped && ! $this->isCanceled();
    }

    /**
     * 購入履歴から作る（商品とその振込先を Eager Load 済みであること）
     */
    public static function fromPurchase(Purchase_history $purchase): self
    {
        $method = PaymentMethod::tryFrom($purchase->payment_method);

        $lines = $purchase->purchaseHistoryItems
            ->map(fn (Purchase_history_item $line): array => [
                'name' => $line->item->display_name,
                'price' => $line->price,
                'quantity' => $line->quantity,
                'subtotal' => $line->subtotal,
            ])
            ->values()
            ->all();

        return new self(
            orderedAt: $purchase->created_at,
            lines: $lines,
            shippingFee: $purchase->shipping_fee,
            codFee: $method === PaymentMethod::Cod ? $purchase->cod_fee : null,
            total: $purchase->total_price,
            deliveryName: $purchase->delivery_name,
            deliveryAddress: $purchase->delivery_prefecture.$purchase->delivery_city,
            deliveryTell: $purchase->delivery_tell,
            paymentLabel: $method?->label() ?? $purchase->payment_method,
            bankTransfer: $method?->usesBankTransfer() && $purchase->canceled_at === null ? self::bankTransferInfo($purchase->purchaseBankTransfer) : null,
            orderId: $purchase->id,
            ordererName: $purchase->relationLoaded('user') ? $purchase->user->full_name : null,
            shipped: $purchase->shipped,
            canceledAt: $purchase->canceled_at,
            paymentMethod: $method,
        );
    }

    /**
     * 予約から作る（商品とその振込先を Eager Load 済みであること）
     */
    public static function fromReservation(Reservation $reservation): self
    {
        $method = PaymentMethod::tryFrom($reservation->payment_method);

        return new self(
            orderedAt: $reservation->created_at,
            lines: [[
                'name' => $reservation->item->display_name,
                'price' => $reservation->price,
                'quantity' => $reservation->quantity,
                'subtotal' => $reservation->subtotal,
            ]],
            shippingFee: $reservation->shipping_fee,
            codFee: null,
            total: $reservation->total_price,
            deliveryName: $reservation->delivery_name,
            deliveryAddress: $reservation->delivery_prefecture.$reservation->delivery_city,
            deliveryTell: $reservation->delivery_tell,
            paymentLabel: $method?->label() ?? $reservation->payment_method,
            bankTransfer: $method?->usesBankTransfer() && $reservation->canceled_at === null ? self::bankTransferInfo($reservation->reservationBankTransfer) : null,
            orderId: $reservation->id,
            ordererName: $reservation->relationLoaded('user') ? $reservation->user->full_name : null,
            shipped: $reservation->shipped,
            canceledAt: $reservation->canceled_at,
            paymentMethod: $method,
        );
    }

    /**
     * お振込先の表示用の値（振込先の行が無いときは、支払い済みかどうかがわからないため null）
     */
    private static function bankTransferInfo(Purchase_bank_transfer|Reservation_bank_transfer|null $row): ?BankTransferInfo
    {
        return $row === null ? null : BankTransferInfo::fromRow($row);
    }
}
