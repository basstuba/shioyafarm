<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * filled-button コンポーネントのテスト
 */
class FilledButtonTest extends TestCase
{
    /**
     * href を渡すとリンク、既定の大きさ・色（購入）で出す
     */
    #[Test]
    public function hrefを渡すとリンクになり既定の大きさと色で出る(): void
    {
        $html = Blade::render('<x-filled-button href="/buy">購入する</x-filled-button>');

        $this->assertStringContainsString('<a href="/buy"', $html);
        $this->assertStringContainsString('h-11 w-50', $html);
        $this->assertStringContainsString('md:h-10', $html);
        $this->assertStringContainsString('hover:bg-primary-hover', $html);
        // ホバーで枠線も背景とそろえる
        $this->assertStringContainsString('hover:border-primary-hover', $html);
        $this->assertStringNotContainsString('hover:shadow-button', $html);
    }

    /**
     * href を渡さないと submit のボタンになり、無効のときは薄くしてホバーを打ち消す
     */
    #[Test]
    public function hrefを渡さないとボタンになり無効の見た目を持つ(): void
    {
        $html = Blade::render('<x-filled-button>注文を確定する</x-filled-button>');

        $this->assertStringContainsString('<button type="submit"', $html);
        $this->assertStringContainsString('disabled:cursor-not-allowed', $html);
        $this->assertStringContainsString('disabled:opacity-60', $html);
        $this->assertStringContainsString('disabled:hover:bg-action', $html);
        $this->assertStringContainsString('disabled:hover:border-action', $html);
    }

    /**
     * xl は高さ60px・影つき、予約の色にすると色と無効時のホバー打ち消しも予約の色になる
     */
    #[Test]
    public function xlと予約の色を指定できる(): void
    {
        $html = Blade::render('<x-filled-button type="button" size="xl" variant="reserve" disabled>予約受付終了</x-filled-button>');

        $this->assertStringContainsString('h-15 w-50', $html);
        $this->assertStringContainsString('rounded-[40px]', $html);
        $this->assertStringContainsString('bg-reserve', $html);
        $this->assertStringContainsString('hover:bg-reserve-hover', $html);
        $this->assertStringContainsString('hover:shadow-button', $html);
        $this->assertStringContainsString('disabled:hover:bg-reserve', $html);
        $this->assertStringContainsString('disabled:hover:shadow-none', $html);
        $this->assertStringContainsString(' disabled', $html);
        $this->assertStringNotContainsString('bg-primary-hover', $html);
    }

    /**
     * sm はSPで全幅、PCで200×34
     */
    #[Test]
    public function smは_s_pで全幅_p_cで200幅34高さになる(): void
    {
        $html = Blade::render('<x-filled-button size="sm" href="/cart">購入する</x-filled-button>');

        $this->assertStringContainsString('w-full', $html);
        $this->assertStringContainsString('md:h-[34px]', $html);
        $this->assertStringContainsString('md:w-50', $html);
    }
}
