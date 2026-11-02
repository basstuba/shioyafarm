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

    /**
     * xl は高さ60px、md は200×40で文字16px
     */
    #[Test]
    public function xlとmdの大きさを指定できる(): void
    {
        $xl = Blade::render('<x-outline-button size="xl" type="button">カートに入れる</x-outline-button>');
        $md = Blade::render('<x-outline-button size="md" href="/cart">カートの中を確認する</x-outline-button>');

        $this->assertStringContainsString('h-15 w-50', $xl);
        $this->assertStringContainsString('rounded-[40px]', $xl);
        $this->assertStringContainsString('h-11 w-50', $md);
        $this->assertStringContainsString('text-base', $md);
        $this->assertStringContainsString('md:h-10', $md);
    }

    /**
     * address は160×40で文字14px・角丸20px、SPは高さ44で幅は親いっぱい
     */
    #[Test]
    public function addressは160幅40高さで文字14pxになる(): void
    {
        $html = Blade::render('<x-outline-button size="address" type="button">住所を追加</x-outline-button>');

        $this->assertStringContainsString('<button type="button"', $html);
        $this->assertStringContainsString('h-11 w-full', $html);
        $this->assertStringContainsString('md:h-10', $html);
        $this->assertStringContainsString('md:w-40', $html);
        $this->assertStringContainsString('rounded-[20px]', $html);
        $this->assertStringContainsString('text-sm', $html);
        $this->assertStringContainsString('hover:bg-action-hover', $html);
    }
}
