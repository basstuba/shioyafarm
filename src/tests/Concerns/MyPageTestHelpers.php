<?php

namespace Tests\Concerns;

use App\Enums\PaymentMethod;
use App\Models\Item;
use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use App\Models\Purchase_history_item;
use App\Models\Reservation;
use App\Models\Reservation_bank_transfer;
use App\Models\User;

/**
 * マイページのテストで使う、購入履歴・予約を作る補助
 */
trait MyPageTestHelpers
{
    /**
     * 購入履歴を明細つきで作る（金額は明細の小計 + 送料 + 代引手数料）
     *
     * @param  list<array{0: string, 1: int, 2: int, 3: int}>  $lines  [商品名, 重量, 単価, 数量] の配列
     * @param  array<string, mixed>  $overrides
     */
    private function makePurchase(User $user, array $lines = [['じゃがいも', 2, 330, 1]], string $method = 'card', array $overrides = []): Purchase_history
    {
        $subtotal = 0;
        foreach ($lines as [, , $price, $quantity]) {
            $subtotal += $price * $quantity;
        }
        $shippingFee = 940;
        $codFee = $method === PaymentMethod::Cod->value ? (int) config('shipping.cod_fee') : 0;

        $purchase = Purchase_history::factory()->create([
            'user_id' => $user->id,
            'payment_method' => $method,
            'shipping_fee' => $shippingFee,
            'cod_fee' => $codFee,
            'total_price' => $subtotal + $shippingFee + $codFee,
            ...$overrides,
        ]);

        foreach ($lines as [$name, $weight, $price, $quantity]) {
            Purchase_history_item::factory()->create([
                'purchase_history_id' => $purchase->id,
                'item_id' => Item::factory()->create(['name' => $name, 'weight' => $weight, 'price' => $price, 'is_reserved_only' => false])->id,
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $price * $quantity,
            ]);
        }

        return $purchase;
    }

    /**
     * 予約を作る（クレジットカード）
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeReservation(User $user, string $method = 'card', array $overrides = []): Reservation
    {
        $item = Item::factory()->create(['name' => '北の恵みセット', 'weight' => 10, 'price' => 1450, 'is_reserved_only' => true, 'reservation_limit' => 200]);

        return Reservation::factory()->create([
            'user_id' => $user->id,
            'item_id' => $item->id,
            'payment_method' => $method,
            ...$overrides,
        ]);
    }

    /**
     * 購入履歴に銀行振込の振込先を付ける（$paid が true のときは顧客残高で支払い済みで、口座の項目は null）
     */
    private function attachPurchaseBankTransfer(Purchase_history $purchase, bool $paid = false): void
    {
        Purchase_bank_transfer::factory()->create([
            'purchase_history_id' => $purchase->id,
            'amount' => $purchase->total_price,
            ...($paid ? ['bank_name' => null, 'branch_name' => null, 'account_type' => null, 'account_number' => null, 'account_holder_name' => null] : []),
        ]);
    }

    /**
     * 予約に銀行振込の振込先を付ける（$paid が true のときは顧客残高で支払い済みで、口座の項目は null）
     */
    private function attachReservationBankTransfer(Reservation $reservation, bool $paid = false): void
    {
        Reservation_bank_transfer::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => $reservation->total_price,
            ...($paid ? ['bank_name' => null, 'branch_name' => null, 'account_type' => null, 'account_number' => null, 'account_holder_name' => null] : []),
        ]);
    }
}
