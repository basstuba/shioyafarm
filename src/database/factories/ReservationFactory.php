<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * 予約した時点の商品名・重さは、指定がなければ商品の今の値にする（削除済みの商品も引く。商品の取得は1回）
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Reservation $model): void {
            if ($model->getAttribute('item_name') === null || $model->getAttribute('item_weight') === null) {
                $item = Item::withTrashed()->findOrFail($model->item_id);
                $model->setAttribute('item_name', $model->getAttribute('item_name') ?? $item->name);
                $model->setAttribute('item_weight', $model->getAttribute('item_weight') ?? $item->weight);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'item_id' => Item::factory(),
            'delivery_name' => fake()->name(),
            'delivery_prefecture' => '北海道',
            'delivery_city' => fake()->city(),
            'delivery_tell' => '09012345678',
            'quantity' => 1,
            'price' => 1450,
            'subtotal' => 1450,
            'payment_method' => 'card',
            'shipping_fee' => 1530,
            'total_price' => 2980,
            'shipped' => false,
        ];
    }
}
