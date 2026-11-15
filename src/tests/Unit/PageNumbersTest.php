<?php

namespace Tests\Unit;

use App\Support\PageNumbers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ページ送りに出すページ番号の選び方のテスト
 */
class PageNumbersTest extends TestCase
{
    /**
     * 最初・最後と、現在のページの前後2つを出す
     *
     * @param  list<int>  $expected
     */
    #[Test]
    #[DataProvider('cases')]
    public function 出すページ番号を選ぶ(int $current, int $last, array $expected): void
    {
        $this->assertSame($expected, PageNumbers::visible($current, $last));
    }

    /**
     * 現在のページ・最後のページと、出す番号
     *
     * @return array<string, array{int, int, list<int>}>
     */
    public static function cases(): array
    {
        return [
            '1ページだけ' => [1, 1, [1]],
            '少ないときは全部' => [2, 5, [1, 2, 3, 4, 5]],
            '先頭付近は後ろが省かれる' => [1, 10, [1, 2, 3, 10]],
            '真ん中は前後2つと両端' => [6, 12, [1, 4, 5, 6, 7, 8, 12]],
            '末尾付近は前が省かれる' => [10, 10, [1, 8, 9, 10]],
        ];
    }
}
