<?php

namespace Tests\Unit;

use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 資材モデルの「残りわずか」の判定（isLow と low スコープは同じ条件）のテスト
 */
class MaterialTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 在庫数 < 基準値 のときだけ残りわずか。同じ・上回る・基準値0は残りわずかではない
     */
    #[Test]
    #[DataProvider('stocks')]
    public function 在庫数が基準値を下回ったときだけ残りわずかになる(int $stock, int $threshold, bool $expected): void
    {
        $material = Material::factory()->create(['stock' => $stock, 'threshold' => $threshold]);

        $this->assertSame($expected, $material->isLow());
        $this->assertSame($expected, Material::query()->low()->whereKey($material->id)->exists(), 'low スコープは isLow() と同じ判定');
    }

    /**
     * 在庫数・基準値と、残りわずかかどうか
     *
     * @return array<string, array{int, int, bool}>
     */
    public static function stocks(): array
    {
        return [
            '下回る' => [9, 10, true],
            '基準値と同じ' => [10, 10, false],
            '上回る' => [40, 10, false],
            '在庫0で基準値あり' => [0, 1, true],
            '在庫0で基準値0' => [0, 0, false],
            '大きい数' => [12345, 20000, true],
            '上限' => [99999, 99999, false],
        ];
    }

    /**
     * 数字の列は整数として扱う
     */
    #[Test]
    public function 在庫数と基準値は整数で取り出せる(): void
    {
        $material = Material::factory()->create(['stock' => 7, 'threshold' => 3])->fresh();

        $this->assertSame(7, $material->stock);
        $this->assertSame(3, $material->threshold);
    }
}
