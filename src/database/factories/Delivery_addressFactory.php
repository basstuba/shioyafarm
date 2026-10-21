<?php

namespace Database\Factories;

use App\Models\Delivery_address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery_address>
 */
class Delivery_addressFactory extends Factory
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
            'delivery_prefecture' => '東京都',
            'delivery_city' => fake()->city().'1-2-3',
        ];
    }
}
