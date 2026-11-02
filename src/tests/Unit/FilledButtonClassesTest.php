<?php

namespace Tests\Unit;

use App\View\Components\FilledButton;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * FilledButton（橙の塗りつぶしのボタン）のクラスの組み立ての分岐のテスト
 *
 * クラスの文字列そのものは tests/Feature/FilledButtonTest（Blade::render）で確認している。ここでは分岐だけを確認する。
 */
class FilledButtonClassesTest extends TestCase
{
    /**
     * 大きさごとの、影が付くかどうか
     *
     * @return array<string, array{string, bool}>
     */
    public static function shadowBySize(): array
    {
        return [
            'xl' => ['xl', true],
            '2xl' => ['2xl', true],
            'sm' => ['sm', true],
            'xs' => ['xs', false],
            'lg' => ['lg', false],
        ];
    }

    /**
     * 影（ホバー時の影と、無効のときの影の打ち消し）は xl・2xl・sm だけに付く
     */
    #[Test]
    #[DataProvider('shadowBySize')]
    public function 影はxlと2xlとsmだけに付く(string $size, bool $hasShadow): void
    {
        $button = new FilledButton(size: $size);

        $this->assertSame($hasShadow, $this->contains($button->classes(), 'hover:shadow-button'));
        $this->assertSame($hasShadow, $this->contains($button->disabledClasses(), 'disabled:hover:shadow-none'));
    }

    /**
     * 大きさごとにクラスの配列が違う
     */
    #[Test]
    public function 大きさごとにクラスが違う(): void
    {
        $sizes = ['xl', '2xl', 'sm', 'xs', 'lg'];
        $all = array_map(fn (string $size): array => (new FilledButton(size: $size))->classes(), $sizes);

        $this->assertCount(count($sizes), array_unique(array_map('serialize', $all)));
    }

    /**
     * 一覧にない大きさは lg と同じ
     */
    #[Test]
    public function 一覧にない大きさはlgと同じ(): void
    {
        $this->assertSame((new FilledButton(size: 'lg'))->classes(), (new FilledButton(size: 'unknown'))->classes());
        $this->assertSame((new FilledButton(size: 'lg'))->disabledClasses(), (new FilledButton(size: 'unknown'))->disabledClasses());
    }

    /**
     * 予約と購入で、色のクラスも無効のときのホバー打ち消しも違う
     */
    #[Test]
    public function 予約と購入で色が違う(): void
    {
        $action = new FilledButton;
        $reserve = new FilledButton(variant: 'reserve');

        $this->assertNotSame($action->classes(), $reserve->classes());
        $this->assertNotSame($action->disabledClasses(), $reserve->disabledClasses());
        $this->assertTrue($this->contains($reserve->classes(), 'bg-reserve'));
        $this->assertFalse($this->contains($reserve->classes(), 'bg-action'));
        $this->assertTrue($this->contains($action->classes(), 'bg-action'));
        $this->assertFalse($this->contains($action->classes(), 'bg-reserve'));
    }

    /**
     * 一覧にない色は購入（action）と同じ
     */
    #[Test]
    public function 一覧にない色は購入と同じ(): void
    {
        $this->assertSame((new FilledButton)->classes(), (new FilledButton(variant: 'foo'))->classes());
        $this->assertSame((new FilledButton)->disabledClasses(), (new FilledButton(variant: 'foo'))->disabledClasses());
    }

    /**
     * 引数の既定値は、リンクなし・submit・lg・action
     */
    #[Test]
    public function 引数の既定値(): void
    {
        $button = new FilledButton;

        $this->assertNull($button->href);
        $this->assertSame('submit', $button->type);
        $this->assertSame('lg', $button->size);
        $this->assertSame('action', $button->variant);
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
