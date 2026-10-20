<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * outline-button コンポーネントのテスト
 */
class OutlineButtonTest extends TestCase
{
    /**
     * href を渡すとリンクとして出力し、ホバー・フォーカスの演出クラスを持つ
     */
    #[Test]
    public function hrefを渡すとリンクになる(): void
    {
        $html = Blade::render('<x-outline-button href="/register">会員登録</x-outline-button>');

        $this->assertStringContainsString('<a href="/register"', $html);
        $this->assertStringContainsString('会員登録</a>', $html);
        $this->assertStringNotContainsString('<button', $html);
        $this->assertStringContainsString('hover:bg-action-hover', $html);
        $this->assertStringContainsString('md:w-[130px]', $html);
    }

    /**
     * href を渡さないと submit のボタンとして出力し、リンクと同じ演出クラスを持つ
     */
    #[Test]
    public function hrefを渡さないとボタンになる(): void
    {
        $html = Blade::render('<x-outline-button size="lg">送信する</x-outline-button>');

        $this->assertStringContainsString('<button type="submit"', $html);
        $this->assertStringContainsString('送信する</button>', $html);
        $this->assertStringContainsString('hover:bg-action-hover', $html);
        $this->assertStringContainsString('focus-visible:bg-action-hover', $html);
        $this->assertStringContainsString('hover:shadow-button', $html);
        $this->assertStringContainsString('w-50', $html);
        // 無効（送信中）のときはホバーの演出を打ち消す
        $this->assertStringContainsString('disabled:hover:bg-white', $html);
        $this->assertStringContainsString('disabled:hover:shadow-none', $html);
    }
}
