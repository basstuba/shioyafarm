<?php

namespace Tests\Concerns;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Models\Item;
use App\Models\Reservation;
use App\Models\User;
use App\Services\OrderTokenStore;
use App\Services\Payment\BankTransferInstructions;
use App\Services\PendingOrder;

/**
 * 予約機能のテストで使う、商品・予約・保存待ちの予約を作る補助
 *
 * 会員・都道府県・Stripe Customer のモックは PurchaseTestHelpers のものを使う（両方の trait を use する）。
 */
trait ReservationTestHelpers
{
    /**
     * 予約限定の商品を作る（既定は北の恵みセット10kg・1,450円・予約可能数200。シナリオ S-2 と同じ）
     */
    private function makeReservable(int $weight = 10, int $price = 1450, ?int $limit = 200, string $name = '北の恵みセット'): Item
    {
        return Item::factory()->create([
            'name' => $name,
            'weight' => $weight,
            'price' => $price,
            'img_url' => 'image/product/potato-card.webp',
            'is_reserved_only' => true,
            'reservation_limit' => $limit,
        ]);
    }

    /**
     * 別の会員の予約を、指定した数量で作る（予約可能数の残りを減らす）
     */
    private function makeReservedBy(Item $item, int $quantity): Reservation
    {
        return Reservation::factory()->create([
            'item_id' => $item->id,
            'quantity' => $quantity,
            'price' => $item->price,
            'subtotal' => $item->price * $quantity,
        ]);
    }

    /**
     * 予約ページが発行する1回限りの注文トークンを新しく発行する（購入とは別のキーに持つ）
     */
    private function newReservationToken(User $user): string
    {
        return (new OrderTokenStore(OrderTokenStore::RESERVATION_KEY_PREFIX))->issue($user->id);
    }

    /**
     * 「予約を確定する」で送る値（ReservationRequest が受け取る形）を組み立てる
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function reservationData(User $user, Item $item, int $quantity = 1, array $overrides = []): array
    {
        return array_merge([
            'item_id' => $item->id,
            'quantity' => $quantity,
            'delivery_name' => '塩谷太郎',
            'address_id' => 'user_'.$user->id,
            'delivery_tell' => '09012345678',
            'payment_method' => 'card',
            'order_token' => $this->newReservationToken($user),
        ], $overrides);
    }

    /**
     * 保存待ちの予約（北海道へ、北の恵みセット10kg×1）をセッションに入れて返す
     *
     * paymentIntentId を渡すと、案内ページに表示する振込先（いろは銀行・合計2,980円）も付ける。
     */
    private function putPendingReservation(
        User $user,
        Item $item,
        PaymentMethod $method = PaymentMethod::Card,
        ?string $sessionId = null,
        ?string $paymentIntentId = null,
        ?string $orderId = null,
    ): PendingOrder {
        $order = new PendingOrder(
            $user->id,
            PurchaseSource::Item,
            'user_'.$user->id,
            '山田花子',
            '08011112222',
            '北海道',
            '札幌市中央区1-1-1',
            $method,
            [['item_id' => $item->id, 'name' => $item->name.' '.$item->weight.'kg', 'quantity' => 1, 'price' => 1450, 'subtotal' => 1450]],
            1450,
            1530,
            0,
            2980,
            $sessionId,
            $paymentIntentId,
            $paymentIntentId === null ? null : new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 2980),
            $orderId,
        );
        session(['pending_reservation' => $order->toArray()]);

        return $order;
    }
}
