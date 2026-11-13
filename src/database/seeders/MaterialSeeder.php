<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

/**
 * 資材の初期データ（化粧箱4種類を、それぞれ別の置き場所で入れる。置き場所・在庫数・基準値は仮の値）
 *
 * 化粧箱 10kg用 だけ在庫数が基準値を下回り、「残りわずか」になる。資材名で探し、なければ作るだけなので、何度実行しても4件のまま。
 * すでにある資材は変えない（管理画面で直した在庫数・置き場所・基準値を、実行のたびに仮の値へ戻さない）。
 */
class MaterialSeeder extends Seeder
{
    /**
     * 資材の初期データを入れる（すでにある資材名は変えない）
     */
    public function run(): void
    {
        $materials = [
            ['name' => '化粧箱 2kg用', 'stock' => 120, 'location' => '作業小屋の棚 上段', 'threshold' => 30],
            ['name' => '化粧箱 5kg用', 'stock' => 80, 'location' => '作業小屋の棚 下段', 'threshold' => 20],
            ['name' => '化粧箱 10kg用', 'stock' => 15, 'location' => '倉庫1階 入口横', 'threshold' => 20],
            ['name' => '化粧箱 15kg用', 'stock' => 40, 'location' => '倉庫2階 奥', 'threshold' => 10],
        ];

        foreach ($materials as $material) {
            Material::firstOrCreate(['name' => $material['name']], $material);
        }
    }
}
