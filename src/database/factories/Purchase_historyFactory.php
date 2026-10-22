<?php

namespace Database\Factories;

use App\Models\Purchase_history;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase_history>
 */
class Purchase_historyFactory extends Factory
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
            'delivery_name' => fake()->name(),
            'delivery_prefecture' => '北海道',
            'delivery_city' => fake()->city(),
            'delivery_tell' => '09012345678',
            'payment_method' => 'card',
            'shipping_fee' => 940,
            'cod_fee' => 0,
            'total_price' => 1270,
        ];
    }
}
