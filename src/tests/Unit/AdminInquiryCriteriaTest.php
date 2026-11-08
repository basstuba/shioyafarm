<?php

namespace Tests\Unit;

use App\Services\AdminInquiryCriteria;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 管理画面のお問い合わせ一覧の検索条件（AdminInquiryCriteria）のテスト
 */
class AdminInquiryCriteriaTest extends TestCase
{
    /**
     * すべて空のときは、どれも絞り込まない
     */
    #[Test]
    public function すべて空のときは絞り込まない(): void
    {
        $criteria = AdminInquiryCriteria::fromConditions(['addressed' => '', 'date' => '']);

        $this->assertNull($criteria->addressed);
        $this->assertNull($criteria->from);
        $this->assertNull($criteria->to);
        $this->assertEquals($criteria, AdminInquiryCriteria::fromConditions([]));
    }

    /**
     * 対応状況は '1' が対応済み、'0' が未対応
     */
    #[Test]
    public function 対応状況は真偽値になる(): void
    {
        $this->assertTrue(AdminInquiryCriteria::fromConditions(['addressed' => '1'])->addressed);
        $this->assertFalse(AdminInquiryCriteria::fromConditions(['addressed' => '0'])->addressed);
    }

    /**
     * 日付は日本時間のその日の0時から翌日の0時の手前まで
     */
    #[Test]
    public function 日付は日本時間のその日の範囲になる(): void
    {
        $criteria = AdminInquiryCriteria::fromConditions(['date' => '2026-11-08']);

        $this->assertSame('2026-11-08 00:00:00', $criteria->from?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-09 00:00:00', $criteria->to?->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Tokyo', $criteria->from?->getTimezone()->getName());
    }

    /**
     * 決められた値でない対応状況・実在しない日付は、黙って絞り込みを外さず例外にする
     */
    #[Test]
    public function 不正な値は例外になる(): void
    {
        foreach ([['addressed' => 'abc'], ['addressed' => '2'], ['date' => 'abc'], ['date' => '2026-02-30']] as $conditions) {
            try {
                AdminInquiryCriteria::fromConditions($conditions);
                $this->fail('例外になるはず：'.json_encode($conditions));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
