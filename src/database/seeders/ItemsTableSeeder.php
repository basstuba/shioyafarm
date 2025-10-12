<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('items')->insert([
            [
                'name' => 'じゃがいも',
                'weight' => 2,
                'price' => 330,
                'img_url' => 'storage/images/potato.jpg', // 仮の画像パス
                'detail' => 'ホクホク食感と優しい甘みが特徴の新鮮なじゃがいもです',
                'recommend' => true,
                'is_reserved_only' => false,
            ],
            [
                'name' => 'じゃがいも',
                'weight' => 5,
                'price' => 750,
                'img_url' => 'storage/images/potato.jpg', // 仮の画像パス
                'detail' => 'ホクホク食感と優しい甘みが特徴の新鮮なじゃがいもです',
                'recommend' => false,
                'is_reserved_only' => false,
            ],
            [
                'name' => 'じゃがいも',
                'weight' => 10,
                'price' => 1320,
                'img_url' => 'storage/images/potato.jpg', // 仮の画像パス
                'detail' => 'ホクホク食感と優しい甘みが特徴の新鮮なじゃがいもです',
                'recommend' => false,
                'is_reserved_only' => false,
            ],
            [
                'name' => '玉ねぎ',
                'weight' => 2,
                'price' => 355,
                'img_url' => 'storage/images/onion.jpg', // 仮の画像パス
                'detail' => '瑞々しく甘みたっぷり、煮込み料理にもぴったりな玉ねぎです',
                'recommend' => true,
                'is_reserved_only' => false,
            ],
            [
                'name' => '玉ねぎ',
                'weight' => 5,
                'price' => 795,
                'img_url' => 'storage/images/onion.jpg', // 仮の画像パス
                'detail' => '瑞々しく甘みたっぷり、煮込み料理にもぴったりな玉ねぎです',
                'recommend' => false,
                'is_reserved_only' => false,
            ],
            [
                'name' => '玉ねぎ',
                'weight' => 10,
                'price' => 1410,
                'img_url' => 'storage/images/onion.jpg', // 仮の画像パス
                'detail' => '瑞々しく甘みたっぷり、煮込み料理にもぴったりな玉ねぎです',
                'recommend' => false,
                'is_reserved_only' => false,
            ],
            [
                'name' => '人参',
                'weight' => 2,
                'price' => 418,
                'img_url' => 'storage/images/carrot.jpg', // 仮の画像パス
                'detail' => '甘みが強くみずみずしい新鮮なにんじんです',
                'recommend' => true,
                'is_reserved_only' => false,
            ],
            [
                'name' => '人参',
                'weight' => 5,
                'price' => 840,
                'img_url' => 'storage/images/carrot.jpg', // 仮の画像パス
                'detail' => '甘みが強くみずみずしい新鮮なにんじんです',
                'recommend' => false,
                'is_reserved_only' => false,
            ],
            [
                'name' => '人参',
                'weight' => 10,
                'price' => 1675,
                'img_url' => 'storage/images/carrot.jpg', // 仮の画像パス
                'detail' => '甘みが強くみずみずしい新鮮なにんじんです',
                'recommend' => false,
                'is_reserved_only' => false,
            ],
            [
                'name' => '北の恵みセット',
                'weight' => 5,
                'price' => 825,
                'img_url' => 'storage/images/mix.jpg', // 仮の画像パス
                'detail' => '甘み豊かなにんじん、玉ねぎ、じゃがいもの詰め合わせです',
                'recommend' => true,
                'is_reserved_only' => true,
                'reservation_limit' => 300,
            ],
            [
                'name' => '北の恵みセット',
                'weight' => 10,
                'price' => 1450,
                'img_url' => 'storage/images/mix.jpg', // 仮の画像パス
                'detail' => '甘み豊かなにんじん、玉ねぎ、じゃがいもの詰め合わせです',
                'recommend' => false,
                'is_reserved_only' => true,
                'reservation_limit' => 200,
            ],
        ]);
    }
}
