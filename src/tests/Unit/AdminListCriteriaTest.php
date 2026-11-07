<?php

namespace Tests\Unit;

use App\Enums\AdminListStatus;
use App\Services\AdminListCriteria;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 管理画面の一覧の検索条件（AdminListCriteria）のテスト
 */
class AdminListCriteriaTest extends TestCase
{
    /**
     * すべて空のときは、どれも絞り込まない
     */
    #[Test]
    public function すべて空のときは絞り込まない(): void
    {
        $criteria = AdminListCriteria::fromConditions(['shipped' => '', 'date' => '', 'name' => '']);

        $this->assertNull($criteria->status);
        $this->assertNull($criteria->from);
        $this->assertNull($criteria->to);
        $this->assertSame('', $criteria->name);
        $this->assertEquals($criteria, AdminListCriteria::fromConditions([]));
    }

    /**
     * 発送状況は '0' が未発送、'1' が発送済、'canceled' が取消済
     */
    #[Test]
    public function 発送状況は列挙型になる(): void
    {
        $this->assertSame(AdminListStatus::Unshipped, AdminListCriteria::fromConditions(['shipped' => '0'])->status);
        $this->assertSame(AdminListStatus::Shipped, AdminListCriteria::fromConditions(['shipped' => '1'])->status);
        $this->assertSame(AdminListStatus::Canceled, AdminListCriteria::fromConditions(['shipped' => 'canceled'])->status);
    }

    /**
     * 決められた値でない発送状況は、絞り込みなしにせず例外になる
     */
    #[Test]
    #[DataProvider('brokenStatuses')]
    public function 壊れた発送状況は例外になる(string $status): void
    {
        $this->expectException(InvalidArgumentException::class);

        AdminListCriteria::fromConditions(['shipped' => $status]);
    }

    /**
     * 決められた値でない発送状況
     *
     * @return array<string, array{string}>
     */
    public static function brokenStatuses(): array
    {
        return ['2' => ['2'], '01' => ['01'], '大文字' => ['Canceled'], 'true' => ['true'], '空白' => [' ']];
    }

    /**
     * 日付は日本時間のその日の0時から翌日の0時まで
     */
    #[Test]
    public function 日付は日本時間のその日の範囲になる(): void
    {
        $criteria = AdminListCriteria::fromConditions(['date' => '2026-11-04']);

        $this->assertSame('2026-11-04 00:00:00 Asia/Tokyo', $criteria->from?->format('Y-m-d H:i:s e'));
        $this->assertSame('2026-11-05 00:00:00 Asia/Tokyo', $criteria->to?->format('Y-m-d H:i:s e'));
    }

    /**
     * 名前は半角・全角のスペースを除く
     */
    #[Test]
    public function 名前はスペースを除く(): void
    {
        $this->assertSame('山田太郎', AdminListCriteria::fromConditions(['name' => " 山田 \u{3000}太郎 "])->name);
    }

    /**
     * 壊れた文字（不正な UTF-8）を含む名前は、空文字（絞り込みなし）にせず例外になる
     */
    #[Test]
    public function 壊れた名前は例外になる(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AdminListCriteria::fromConditions(['name' => "山田\xFF"]);
    }

    /**
     * 日付でない値・存在しない日付・形式違いは、絞り込みなしにせず例外になる
     *
     * @param  string  $date  不正な日付
     */
    #[Test]
    #[DataProvider('brokenDates')]
    public function 壊れた日付は例外になる(string $date): void
    {
        $this->expectException(InvalidArgumentException::class);

        AdminListCriteria::fromConditions(['date' => $date]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function brokenDates(): array
    {
        return [
            '日付でない' => ['abc'],
            '存在しない日付' => ['2026-02-30'],
            'スラッシュ区切り' => ['2026/11/04'],
            '桁が足りない' => ['2026-1-4x'],
        ];
    }
}
