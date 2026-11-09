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
     * 注文した時点の商品名・重さは、指定がなければ商品の今の値にする（削除済みの商品も引く。商品の取得は1回）
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Purchase_history_item $model): void {
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
            'purchase_history_id' => Purchase_history::factory(),
            'item_id' => Item::factory(),
            'quantity' => 1,
            'price' => 330,
            'subtotal' => 330,
        ];
    }
}
