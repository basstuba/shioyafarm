<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
                'じゃがいも',
                '玉ねぎ',
                '人参',
                '北の恵みセット',
            ]);

        return [
            'name' => $name,
            'weight' => fake()->randomElement([2, 5, 10]),
            'price' => fake()->randomElement([500, 800, 1000]),
            'img_url' => 'image/product/carrots-card.webp',
            'detail' => '商品説明です。',
            'recommend' => fake()->boolean(20),
            'is_reserved_only' => $name === '北の恵みセット',
            'reservation_limit' => $name === '北の恵みセット' ? 200 : null,
        ];
    }
}
