<?php

namespace Tests\Unit;

use App\Services\AdminNoticeCriteria;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 管理画面のお知らせ一覧の検索条件（AdminNoticeCriteria）のテスト
 */
class AdminNoticeCriteriaTest extends TestCase
{
    /**
     * 空のときは、絞り込まない
     */
    #[Test]
    public function 空のときは絞り込まない(): void
    {
        $criteria = AdminNoticeCriteria::fromConditions(['date' => '']);

        $this->assertNull($criteria->from);
        $this->assertNull($criteria->to);
        $this->assertEquals($criteria, AdminNoticeCriteria::fromConditions([]));
    }

    /**
     * 日付は日本時間のその日の0時から翌日の0時の手前まで
     */
    #[Test]
    public function 日付は日本時間のその日の範囲になる(): void
    {
        $criteria = AdminNoticeCriteria::fromConditions(['date' => '2026-11-08']);

        $this->assertSame('2026-11-08 00:00:00', $criteria->from?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-09 00:00:00', $criteria->to?->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Tokyo', $criteria->from?->getTimezone()->getName());
    }

    /**
     * 実在しない日付は、黙って絞り込みを外さず例外にする
     */
    #[Test]
    public function 不正な日付は例外になる(): void
    {
        foreach (['abc', '2026-02-30', '2026/11/08'] as $date) {
            try {
                AdminNoticeCriteria::fromConditions(['date' => $date]);
                $this->fail("{$date} が例外にならなかった");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
