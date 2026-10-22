<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Services\Payment\BankTransferInstructions;
use Illuminate\Support\Str;

/**
 * 保存待ちの注文
 *
 * 「注文を確定する」の時点でサーバーが再計算した内容を、決済が済むまでセッションに持っておくためのもの。
 * 金額（単価・送料・代引手数料・合計）もここに含め、決済ページで請求した金額と保存する金額をそろえる。
 */
final readonly class PendingOrder
{
    /** 注文の識別子（同時リクエストで二重に保存しないための記録に使う。省略すると新しく採番する） */
    public string $orderId;

    /**
     * @param  list<array{item_id: int, name: string, quantity: int, price: int, subtotal: int}>  $items
     */
    public function __construct(
        public int $userId,
        public PurchaseSource $source,
        public string $addressId,
        public string $deliveryName,
        public string $deliveryTell,
        public string $deliveryPrefecture,
        public string $deliveryCity,
        public PaymentMethod $paymentMethod,
        public array $items,
        public int $subtotal,
        public int $shippingFee,
        public int $codFee,
        public int $totalPrice,
        public ?string $checkoutSessionId = null,
        public ?string $paymentIntentId = null,
        public ?BankTransferInstructions $bankTransfer = null,
        ?string $orderId = null,
    ) {
        $this->orderId = $orderId ?? (string) Str::uuid();
    }

    /**
     * Stripe の Checkout Session ID を付けた新しい注文を返す
     */
    public function withCheckoutSessionId(string $sessionId): self
    {
        return $this->copy($sessionId, $this->paymentIntentId, $this->bankTransfer);
    }

    /**
     * 銀行振込の PaymentIntent ID と振込先を付けた新しい注文を返す
     */
    public function withBankTransfer(string $paymentIntentId, BankTransferInstructions $bankTransfer): self
    {
        return $this->copy($this->checkoutSessionId, $paymentIntentId, $bankTransfer);
    }

    /**
     * 銀行振込の PaymentIntent ID だけを付けた新しい注文を返す（作った時点で支払い済みになり、振込先の案内が無いとき）
     */
    public function withPaymentIntentId(string $paymentIntentId): self
    {
        return $this->copy($this->checkoutSessionId, $paymentIntentId, null);
    }

    /**
     * 銀行振込の PaymentIntent ID と振込先を外した新しい注文を返す（「戻る」で PaymentIntent をキャンセルしたあと）
     */
    public function withoutBankTransfer(): self
    {
        return $this->copy($this->checkoutSessionId, null, null);
    }

    /**
     * 決済に関する項目だけを差し替えた新しい注文を作る
     */
    private function copy(?string $sessionId, ?string $paymentIntentId, ?BankTransferInstructions $bankTransfer): self
    {
        return new self(
            $this->userId,
            $this->source,
            $this->addressId,
            $this->deliveryName,
            $this->deliveryTell,
            $this->deliveryPrefecture,
            $this->deliveryCity,
            $this->paymentMethod,
            $this->items,
            $this->subtotal,
            $this->shippingFee,
            $this->codFee,
            $this->totalPrice,
            $sessionId,
            $paymentIntentId,
            $bankTransfer,
            $this->orderId,
        );
    }

    /**
     * 作った時点で支払い済みだった銀行振込の注文（PaymentIntent があり、振込先の案内がない）かどうか
     */
    public function isPaidBankTransfer(): bool
    {
        return $this->paymentMethod->usesBankTransfer() && $this->paymentIntentId !== null && $this->bankTransfer === null;
    }

    /**
     * 商品詳細から購入した場合の商品ID（カートから購入した場合は null）
     */
    public function itemId(): ?int
    {
        return $this->source === PurchaseSource::Item ? ($this->items[0]['item_id'] ?? null) : null;
    }

    /**
     * 指定した購入ページの注文かどうか
     */
    public function matches(PurchaseSource $source, ?int $itemId): bool
    {
        return $this->source === $source && $this->itemId() === $itemId;
    }

    /**
     * セッションに保存する配列にする
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'source' => $this->source->value,
            'address_id' => $this->addressId,
            'delivery_name' => $this->deliveryName,
            'delivery_tell' => $this->deliveryTell,
            'delivery_prefecture' => $this->deliveryPrefecture,
            'delivery_city' => $this->deliveryCity,
            'payment_method' => $this->paymentMethod->value,
            'items' => $this->items,
            'subtotal' => $this->subtotal,
            'shipping_fee' => $this->shippingFee,
            'cod_fee' => $this->codFee,
            'total_price' => $this->totalPrice,
            'checkout_session_id' => $this->checkoutSessionId,
            'payment_intent_id' => $this->paymentIntentId,
            'bank_transfer' => $this->bankTransfer?->toArray(),
            'order_id' => $this->orderId,
        ];
    }

    /**
     * セッションの配列から復元する（形式が正しくなければ null）
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $source = is_string($data['source'] ?? null) ? PurchaseSource::tryFrom($data['source']) : null;
        $method = is_string($data['payment_method'] ?? null) ? PaymentMethod::tryFrom($data['payment_method']) : null;
        $items = $data['items'] ?? null;

        if ($source === null || $method === null || ! is_array($items) || $items === []) {
            return null;
        }

        foreach (['user_id', 'subtotal', 'shipping_fee', 'cod_fee', 'total_price'] as $key) {
            if (! is_int($data[$key] ?? null)) {
                return null;
            }
        }
        foreach (['address_id', 'delivery_name', 'delivery_tell', 'delivery_prefecture', 'delivery_city'] as $key) {
            if (! is_string($data[$key] ?? null)) {
                return null;
            }
        }

        $sessionId = $data['checkout_session_id'] ?? null;
        $paymentIntentId = $data['payment_intent_id'] ?? null;
        $orderId = $data['order_id'] ?? null;
        $bankTransfer = is_array($data['bank_transfer'] ?? null) ? BankTransferInstructions::fromArray($data['bank_transfer']) : null;

        /** @var array<string, mixed> $data */
        return new self(
            $data['user_id'],
            $source,
            $data['address_id'],
            $data['delivery_name'],
            $data['delivery_tell'],
            $data['delivery_prefecture'],
            $data['delivery_city'],
            $method,
            array_values($items),
            $data['subtotal'],
            $data['shipping_fee'],
            $data['cod_fee'],
            $data['total_price'],
            is_string($sessionId) ? $sessionId : null,
            is_string($paymentIntentId) ? $paymentIntentId : null,
            $bankTransfer,
            is_string($orderId) ? $orderId : null,
        );
    }
}
