<?php

namespace Database\Factories;

use App\Models\Inquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inquiry>
 */
class InquiryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * メールアドレスは inquiries.email の長さ（30文字）以内にする。対応済みかどうかは未対応が既定。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'email' => 'inq'.fake()->unique()->numberBetween(1, 999999).'@example.com',
            'detail' => fake()->randomElement([
                '商品の配送日を教えてください。',
                '予約した商品の内容を変更したいです。',
                '農園の見学はできますか。',
            ]),
            'addressed' => false,
        ];
    }

    /**
     * 対応済みにする
     */
    public function addressed(): static
    {
        return $this->state(fn (): array => ['addressed' => true]);
    }
}
