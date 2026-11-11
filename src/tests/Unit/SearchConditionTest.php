<?php

namespace Tests\Unit;

use App\Support\SearchCondition;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SearchCondition（管理画面の一覧の、検索欄に戻す値の整形）のテスト
 */
class SearchConditionTest extends TestCase
{
    /**
     * 直前の入力（フラッシュされた old）を設定する
     *
     * @param  array<string, mixed>  $input
     */
    private function flashOld(array $input): void
    {
        request()->setLaravelSession(app('session.store'));
        session()->put('_old_input', $input);
    }

    /**
     * old がないときは、検索条件の値を戻す
     */
    #[Test]
    public function oldがないときは検索条件の値を戻す(): void
    {
        $conditions = ['name' => '山田', 'shipped' => '1', 'date' => '2026-11-04'];

        $this->assertSame('山田', SearchCondition::restore($conditions, 'name'));
        $this->assertSame('1', SearchCondition::restore($conditions, 'shipped'));
        $this->assertSame('2026-11-04', SearchCondition::restoreDate($conditions, false));
    }

    /**
     * old があるときは、検索条件ではなく old を戻す
     */
    #[Test]
    public function oldがあるときはoldを優先する(): void
    {
        $this->flashOld(['name' => '佐藤', 'date' => '2026-11-05']);
        $conditions = ['name' => '山田', 'date' => ''];

        $this->assertSame('佐藤', SearchCondition::restore($conditions, 'name'));
        $this->assertSame('2026-11-05', SearchCondition::restoreDate($conditions, false));
    }

    /**
     * 配列など文字列でない入力は戻さず、空にする（TypeError にしない）
     */
    #[Test]
    public function 配列の入力は空にする(): void
    {
        $this->flashOld(['name' => ['a', 'b'], 'date' => ['x']]);
        $conditions = ['name' => '山田', 'date' => '2026-11-04'];

        $this->assertSame('', SearchCondition::restore($conditions, 'name'));
        $this->assertSame('', SearchCondition::restoreDate($conditions, false));
    }

    /**
     * 検索条件の値が配列などでも、空にする
     */
    #[Test]
    public function 検索条件の値が文字列でなくても空にする(): void
    {
        $this->assertSame('', SearchCondition::restore(['name' => ['x']], 'name'));
        $this->assertSame('', SearchCondition::restore(['name' => null], 'name'));
    }

    /**
     * 日付の入力にエラーがあるときは、old や検索条件の値があっても空にする（不正な日付を type=date の欄に戻さない）
     */
    #[Test]
    public function 日付にエラーがあるときは空にする(): void
    {
        $this->flashOld(['date' => '2026-02-30']);

        $this->assertSame('', SearchCondition::restoreDate(['date' => '2026-02-30'], true));
        $this->assertSame('', SearchCondition::restoreDate(['date' => '2026-11-04'], true));
    }

    /**
     * 日付の欄の name は指定できる
     */
    #[Test]
    public function 日付の欄のnameを指定できる(): void
    {
        $conditions = ['date' => '2026-11-04', 'from' => '2026-10-01'];

        $this->assertSame('2026-10-01', SearchCondition::restoreDate($conditions, false, 'from'));
        $this->assertSame('', SearchCondition::restoreDate($conditions, true, 'from'));
    }
}
