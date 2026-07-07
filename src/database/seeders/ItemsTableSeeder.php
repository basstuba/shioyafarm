<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
                'img_url' => 'image/product/potato-card.webp',
                'detail' => 'ホクホク食感と優しい甘みが特徴の新鮮なじゃがいもです',
                'recommend' => true,
                'is_reserved_only' => false,
                'reservation_limit' => null,
            ],
            [
                'name' => 'じゃがいも',
                'weight' => 5,
                'price' => 750,
                'img_url' => 'image/product/potato-card.webp',
                'detail' => 'ホクホク食感と優しい甘みが特徴の新鮮なじゃがいもです',
                'recommend' => false,
                'is_reserved_only' => false,
                'reservation_limit' => null,
            ],
            [
                'name' => 'じゃがいも',
                'weight' => 10,
                'price' => 1320,
                'img_url' => 'image/product/potato-card.webp',
                'detail' => 'ホクホク食感と優しい甘みが特徴の新鮮なじゃがいもです',
                'recommend' => false,
                'is_reserved_only' => false,
                'reservation_limit' => null,
            ],
            [
                'name' => '玉ねぎ',
                'weight' => 2,
                'price' => 355,
                'img_url' => 'image/product/onions-card.webp',
                'detail' => '瑞々しく甘みたっぷり、煮込み料理にもぴったりな玉ねぎです',
                'recommend' => true,
                'is_reserved_only' => false,
                'reservation_limit' => null,
            ],
            [
                'name' => '玉ねぎ',
                'weight' => 5,
                'price' => 795,
                'img_url' => 'image/product/onions-card.webp',
                'detail' => '瑞々しく甘みたっぷり、煮込み料理にもぴったりな玉ねぎです',
                'recommend' => false,
                'is_reserved_only' => false,
                'reservation_limit' => null,
            ],
            [
                'name' => '玉ねぎ',
                'weight' => 10,
                'price' => 1410,
                'img_url' => 'image/product/onions-card.webp',
                'detail' => '瑞々しく甘みたっぷり、煮込み料理にもぴったりな玉ねぎです',
                'recommend' => false,
                'is_reserved_only' => false,
                'reservation_limit' => null,
            ],
            [
                'name' => '人参',
                'weight' => 2,
                'price' => 418,
                'img_url' => 'image/product/carrots-card.webp',
                'detail' => '甘みが強くみずみずしい新鮮なにんじんです',
                'recommend' => true,
                'is_reserved_only' => false,
                'reservation_limit' => null,
            ],
            [
                'name' => '人参',
                'weight' => 5,
                'price' => 840,
                'img_url' => 'image/product/carrots-card.webp',
                'detail' => '甘みが強くみずみずしい新鮮なにんじんです',
                'recommend' => false,
                'is_reserved_only' => false,
                'reservation_limit' => null,
            ],
            [
                'name' => '人参',
                'weight' => 10,
                'price' => 1675,
                'img_url' => 'image/product/carrots-card.webp',
                'detail' => '甘みが強くみずみずしい新鮮なにんじんです',
                'recommend' => false,
                'is_reserved_only' => false,
                'reservation_limit' => null,
            ],
            [
                'name' => '北の恵みセット',
                'weight' => 5,
                'price' => 825,
                'img_url' => 'image/product/vegetables-set-card.webp',
                'detail' => '甘み豊かなにんじん、玉ねぎ、じゃがいもの詰め合わせです',
                'recommend' => true,
                'is_reserved_only' => true,
                'reservation_limit' => 300,
            ],
            [
                'name' => '北の恵みセット',
                'weight' => 10,
                'price' => 1450,
                'img_url' => 'image/product/vegetables-set-card.webp',
                'detail' => '甘み豊かなにんじん、玉ねぎ、じゃがいもの詰め合わせです',
                'recommend' => false,
                'is_reserved_only' => true,
                'reservation_limit' => 200,
            ],
        ]);
    }
}
