<?php

namespace Database\Factories;

use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    /**
     * 資材の既定の値
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => '化粧箱 '.fake()->unique()->numerify('####').'kg用',
            'stock' => fake()->numberBetween(20, 200),
            'location' => fake()->randomElement(['作業小屋の棚 上段', '倉庫1階 入口横', '倉庫2階 奥']),
            'threshold' => fake()->numberBetween(0, 19),
        ];
    }
}
