<?php

namespace Tests\Unit;

use App\View\Components\OutlineButton;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * OutlineButton（白地に橙の枠線のボタン）のクラスの組み立ての分岐のテスト
 *
 * クラスの文字列そのものは tests/Feature/OutlineButtonTest（Blade::render）で確認している。ここでは分岐だけを確認する。
 */
class OutlineButtonClassesTest extends TestCase
{
    /**
     * 大きさごとにクラスの配列が違う
     */
    #[Test]
    public function 大きさごとにクラスが違う(): void
    {
        $sizes = ['xl', 'address', 'lg', 'md', 'sm'];
        $all = array_map(fn (string $size): array => (new OutlineButton(size: $size))->classes(), $sizes);

        $this->assertCount(count($sizes), array_unique(array_map('serialize', $all)));
    }

    /**
     * 一覧にない大きさは sm と同じ
     */
    #[Test]
    public function 一覧にない大きさはsmと同じ(): void
    {
        $this->assertSame((new OutlineButton(size: 'sm'))->classes(), (new OutlineButton(size: 'unknown'))->classes());
    }

    /**
     * 引数の既定値は、リンクなし・submit・sm
     */
    #[Test]
    public function 引数の既定値(): void
    {
        $button = new OutlineButton;

        $this->assertNull($button->href);
        $this->assertSame('submit', $button->type);
        $this->assertSame('sm', $button->size);
        $this->assertSame((new OutlineButton(size: 'sm'))->classes(), $button->classes());
    }
}
