<?php

namespace Database\Factories;

use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase_bank_transfer>
 */
class Purchase_bank_transferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_history_id' => Purchase_history::factory(),
            'stripe_payment_intent_id' => 'pi_'.fake()->unique()->bothify('test_##########'),
            'bank_name' => 'いろは銀行',
            'branch_name' => '中央支店',
            'account_type' => 'futsu',
            'account_number' => '1234567',
            'account_holder_name' => 'ストライプジャパン',
            'amount' => 1270,
        ];
    }
}
