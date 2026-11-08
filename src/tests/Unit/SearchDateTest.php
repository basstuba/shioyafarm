<?php

namespace Tests\Unit;

use App\Support\SearchDate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 管理画面の一覧の日付の検索欄（SearchDate）のテスト
 */
class SearchDateTest extends TestCase
{
    /**
     * 空は null（絞り込まない）
     */
    #[Test]
    public function 空はnullになる(): void
    {
        $this->assertNull(SearchDate::parse(''));
    }

    /**
     * Y-m-d の日付は、日本時間（アプリのタイムゾーン）のその日の0時になる
     */
    #[Test]
    public function 日本時間のその日の0時になる(): void
    {
        $parsed = SearchDate::parse('2026-11-08');

        $this->assertSame('2026-11-08 00:00:00', $parsed?->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Tokyo', $parsed?->getTimezone()->getName());
        // UTC では前日の 15:00
        $this->assertSame('2026-11-07 15:00:00', $parsed?->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * 実在しない日付・Y-m-d でない値は例外にする
     */
    #[Test]
    public function 不正な日付は例外になる(): void
    {
        foreach (['abc', '2026-02-30', '2026/11/08', '2026-1-8', '20261108'] as $date) {
            try {
                SearchDate::parse($date);
                $this->fail('例外になるはず：'.$date);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
