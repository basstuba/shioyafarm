<?php

namespace Tests\Feature;

use App\Models\Material;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MaterialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 資材の初期データ（MaterialSeeder）のテスト
 */
class MaterialSeederTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 化粧箱4種類が、それぞれ別の置き場所で入り、化粧箱 10kg用 だけ残りわずか。何度実行しても4件のまま
     */
    #[Test]
    public function 初期データは4件で何度実行しても増えない(): void
    {
        $this->seed(MaterialSeeder::class);
        $this->seed(MaterialSeeder::class);

        $this->assertDatabaseCount('materials', 4);
        $expected = [
            ['化粧箱 2kg用', 120, '作業小屋の棚 上段', 30],
            ['化粧箱 5kg用', 80, '作業小屋の棚 下段', 20],
            ['化粧箱 10kg用', 15, '倉庫1階 入口横', 20],
            ['化粧箱 15kg用', 40, '倉庫2階 奥', 10],
        ];
        foreach ($expected as [$name, $stock, $location, $threshold]) {
            $this->assertDatabaseHas('materials', ['name' => $name, 'stock' => $stock, 'location' => $location, 'threshold' => $threshold]);
        }
        $this->assertCount(4, Material::query()->pluck('location')->unique());
        $this->assertSame(['化粧箱 10kg用'], Material::query()->low()->pluck('name')->all());
    }

    /**
     * 管理画面で変えた値は、再実行しても初期値に戻らない（すでにある資材は変えず、4件のまま）。消した資材は作り直される
     */
    #[Test]
    public function 管理画面で変えた値は再実行しても戻らない(): void
    {
        $this->seed(MaterialSeeder::class);
        Material::query()->where('name', '化粧箱 2kg用')->update(['stock' => 1, 'location' => '変えた置き場所', 'threshold' => 7]);
        Material::query()->where('name', '化粧箱 5kg用')->delete();

        $this->seed(MaterialSeeder::class);

        $this->assertDatabaseCount('materials', 4);
        $this->assertDatabaseHas('materials', ['name' => '化粧箱 2kg用', 'stock' => 1, 'location' => '変えた置き場所', 'threshold' => 7]);
        $this->assertDatabaseHas('materials', ['name' => '化粧箱 5kg用', 'stock' => 80, 'location' => '作業小屋の棚 下段', 'threshold' => 20]);
    }

    /**
     * DatabaseSeeder から呼ばれ、一覧に4件・トップに「残りわずかの資材が 1件 あります。」が出る
     */
    #[Test]
    public function データベースシーダーから呼ばれ一覧とトップに出る(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $list = $this->get(route('admin.materials.index'))->assertOk();
        $list->assertSeeInOrder(['化粧箱 2kg用', '化粧箱 5kg用', '化粧箱 10kg用', '化粧箱 15kg用']);
        $this->assertSame(1, substr_count($list->getContent(), '残りわずか</span>'));
        $this->get(route('admin.home'))->assertOk()->assertSee('残りわずかの資材が 1件 あります。');
    }
}
