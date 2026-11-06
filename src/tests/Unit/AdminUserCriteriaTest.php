<?php

namespace Tests\Unit;

use App\Services\AdminUserCriteria;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 管理画面の会員一覧の検索条件（AdminUserCriteria）のテスト
 */
class AdminUserCriteriaTest extends TestCase
{
    /**
     * すべて空のときは、どれも絞り込まない
     */
    #[Test]
    public function すべて空のときは絞り込まない(): void
    {
        $criteria = AdminUserCriteria::fromConditions(['status' => '', 'name' => '']);

        $this->assertNull($criteria->withdrawn);
        $this->assertSame('', $criteria->name);
        $this->assertEquals($criteria, AdminUserCriteria::fromConditions([]));
    }

    /**
     * 状態は 'member' が退会していない会員、'withdrawn' が退会済み
     */
    #[Test]
    public function 状態は真偽値になる(): void
    {
        $this->assertFalse(AdminUserCriteria::fromConditions(['status' => 'member'])->withdrawn);
        $this->assertTrue(AdminUserCriteria::fromConditions(['status' => 'withdrawn'])->withdrawn);
    }

    /**
     * 決められた値でない状態は、黙って絞り込みを外さず例外にする
     */
    #[Test]
    public function 不正な状態は例外になる(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AdminUserCriteria::fromConditions(['status' => 'abc']);
    }

    /**
     * 名前は、半角・全角のスペースを除く
     */
    #[Test]
    public function 名前のスペースは除かれる(): void
    {
        $this->assertSame('山田太郎', AdminUserCriteria::fromConditions(['name' => " 山田 \u{3000}太郎\t"])->name);
    }

    /**
     * 正しい UTF-8 でない名前は、黙って絞り込みを外さず例外にする
     */
    #[Test]
    public function 壊れた名前は例外になる(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AdminUserCriteria::fromConditions(['name' => "\xFF\xFE"]);
    }
}
