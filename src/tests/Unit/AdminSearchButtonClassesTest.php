<?php

namespace Tests\Unit;

use App\View\Components\AdminSearchButton;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * AdminSearchButton（管理画面の検索欄まわりのボタン）のクラスの組み立ての分岐のテスト
 *
 * 描画（リンクかボタンか・属性）は tests/Feature/AdminSearchButtonTest（Blade::render）で確認している。ここでは分岐だけを確認する。
 */
class AdminSearchButtonClassesTest extends TestCase
{
    /**
     * variant ごとに色と幅が違う（search・reset は100px、export は200px）
     */
    #[Test]
    public function variantごとに色と幅が違う(): void
    {
        $search = (new AdminSearchButton(variant: 'search'))->classes();
        $reset = (new AdminSearchButton(variant: 'reset'))->classes();
        $export = (new AdminSearchButton(variant: 'export'))->classes();

        $this->assertCount(3, array_unique([serialize($search), serialize($reset), serialize($export)]));
        $this->assertTrue($this->contains($search, 'bg-accent'));
        $this->assertTrue($this->contains($search, 'lg:w-[100px]'));
        $this->assertTrue($this->contains($reset, 'bg-line-strong'));
        $this->assertTrue($this->contains($reset, 'lg:w-[100px]'));
        $this->assertTrue($this->contains($export, 'bg-admin-gray'));
        $this->assertTrue($this->contains($export, 'lg:w-[200px]'));
    }

    /**
     * 押せないときに薄くするクラスは export だけに付く
     */
    #[Test]
    public function 押せないときの見た目はexportだけに付く(): void
    {
        $this->assertTrue($this->contains((new AdminSearchButton(variant: 'export'))->classes(), 'disabled:opacity-40'));
        $this->assertFalse($this->contains((new AdminSearchButton(variant: 'search'))->classes(), 'disabled:opacity-40'));
        $this->assertFalse($this->contains((new AdminSearchButton(variant: 'reset'))->classes(), 'disabled:opacity-40'));
    }

    /**
     * 大きさ（SPは高さ48px・全幅、PCは高さ40px・文字14px）は variant によらず共通
     */
    #[Test]
    public function 大きさはどのvariantも共通(): void
    {
        foreach (['search', 'reset', 'export'] as $variant) {
            $classes = (new AdminSearchButton(variant: $variant))->classes();

            $this->assertTrue($this->contains($classes, 'h-12 w-full'), $variant);
            $this->assertTrue($this->contains($classes, 'lg:h-10 lg:text-sm'), $variant);
        }
    }

    /**
     * 一覧にない variant は search と同じ
     */
    #[Test]
    public function 一覧にないvariantはsearchと同じ(): void
    {
        $this->assertSame((new AdminSearchButton(variant: 'search'))->classes(), (new AdminSearchButton(variant: 'unknown'))->classes());
    }

    /**
     * 引数の既定値は、search・リンクなし・押せる
     */
    #[Test]
    public function 引数の既定値(): void
    {
        $button = new AdminSearchButton;

        $this->assertSame('search', $button->variant);
        $this->assertNull($button->href);
        $this->assertFalse($button->disabled);
    }

    /**
     * クラスの配列のどれかに、指定の文字列が含まれるか
     *
     * @param  list<string>  $classes
     */
    private function contains(array $classes, string $needle): bool
    {
        return str_contains(implode(' ', $classes), $needle);
    }
}
