<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Purchase_history;
use App\Models\Purchase_history_item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase_history_item>
 */
class Purchase_history_itemFactory extends Factory
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
            'item_id' => Item::factory(),
            'quantity' => 1,
            'price' => 330,
            'subtotal' => 330,
        ];
    }
}
