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
        ];
    }
}
