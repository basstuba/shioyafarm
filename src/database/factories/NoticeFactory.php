<?php

namespace Database\Factories;

use App\Models\Notice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notice>
 */
class NoticeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement([
                '夏野菜の販売を開始しました',
                '新商品の予約受付を開始しました',
                '収穫祭イベントのお知らせ',
                '年末年始の営業について',
            ]),
            'text' => fake()->randomElement([
                'いつもご利用いただきありがとうございます。旬の野菜を取り揃えております。',
                '新鮮な野菜をお届けするため、予約販売を開始しました。',
                '季節限定の商品をご用意しました。ぜひご覧ください。',
            ]),
        ];
    }
}
