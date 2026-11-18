<?php

namespace Database\Seeders;

use App\Models\Notice;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * お知らせの初期データ（5件）
 *
 * 本番のイメージは composer install --no-dev で作るため、faker（開発用の依存）に頼るファクトリは使わず、固定の文言で作る
 * （公開デモの毎日の作り直し `migrate:fresh --seed` でも動かすため）。
 * 作成日は、古いものから順に1日ずつずらす（一覧は作成日の新しい順に出るため、ここで並べた最後が先頭になる）。
 */
class NoticesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $notices = [
            ['title' => '年末年始の営業について', 'text' => 'いつもご利用いただきありがとうございます。年末年始は発送をお休みさせていただきます。'],
            ['title' => '収穫祭イベントのお知らせ', 'text' => '季節限定の商品をご用意しました。ぜひご覧ください。'],
            ['title' => '新商品の予約受付を開始しました', 'text' => '新鮮な野菜をお届けするため、予約販売を開始しました。'],
            ['title' => '夏野菜の販売を開始しました', 'text' => 'いつもご利用いただきありがとうございます。旬の野菜を取り揃えております。'],
            ['title' => '送料のご案内を更新しました', 'text' => '地域ごとの送料をご確認いただけるよう、送料のご案内ページを更新しました。'],
        ];

        foreach ($notices as $index => $notice) {
            $createdAt = now()->subDays(count($notices) - $index);
            $row = new Notice($notice);
            $row->created_at = $createdAt;
            $row->updated_at = $createdAt;
            $row->save();
        }
    }
}
