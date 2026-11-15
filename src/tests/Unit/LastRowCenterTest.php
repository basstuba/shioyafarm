<?php

namespace Tests\Unit;

use App\Support\LastRowCenter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ご案内の最終行を中央に寄せるクラスの出し分けのテスト
 */
class LastRowCenterTest extends TestCase
{
    /**
     * @return array<string, array{int, array<int, string|null>}>
     */
    public static function totals(): array
    {
        return [
            '3項目（割り切れる）は付けない' => [3, [1 => null, 2 => null, 3 => null]],
            '4項目は最終行が1項目で、4つ目を3列目から' => [4, [1 => null, 2 => null, 3 => null, 4 => 'lg:col-start-3']],
            '5項目は最終行が2項目で、4つ目を2列目から' => [5, [1 => null, 2 => null, 3 => null, 4 => 'lg:col-start-2', 5 => null]],
            '6項目（割り切れる）は付けない' => [6, [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null]],
            '7項目は最終行が1項目で、7つ目を3列目から' => [7, [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => 'lg:col-start-3']],
            '8項目は最終行が2項目で、7つ目を2列目から' => [8, [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => 'lg:col-start-2', 8 => null]],
        ];
    }

    /**
     * @param  array<int, string|null>  $expected  項目の位置 => クラス
     */
    #[Test]
    #[DataProvider('totals')]
    public function 項目数に応じて最終行の最初の項目にだけクラスを付ける(int $total, array $expected): void
    {
        $actual = [];
        for ($position = 1; $position <= $total; $position++) {
            $actual[$position] = LastRowCenter::startClass($position, $total);
        }

        $this->assertSame($expected, $actual);
    }
}
