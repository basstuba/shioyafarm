<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\Reservation_bank_transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation_bank_transfer>
 */
class Reservation_bank_transferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'stripe_payment_intent_id' => 'pi_'.fake()->unique()->bothify('test_##########'),
            'bank_name' => 'いろは銀行',
            'branch_name' => '中央支店',
            'account_type' => 'futsu',
            'account_number' => '1234567',
            'account_holder_name' => 'ストライプジャパン',
            'amount' => 2980,
        ];
    }
}
