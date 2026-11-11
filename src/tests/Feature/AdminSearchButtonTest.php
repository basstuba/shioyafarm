<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * admin-search-button コンポーネントのテスト
 */
class AdminSearchButtonTest extends TestCase
{
    /**
     * 何も渡さないと、submit のボタンで、search の色になる
     */
    #[Test]
    public function 既定はsubmitのボタンでsearchの色になる(): void
    {
        $html = Blade::render('<x-admin-search-button>検索</x-admin-search-button>');

        $this->assertStringContainsString('<button ', $html);
        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('bg-accent', $html);
        $this->assertStringContainsString('lg:w-[100px]', $html);
        $this->assertStringContainsString('>検索</button>', $html);
        $this->assertStringNotContainsString(' disabled', $html);
    }

    /**
     * href を渡すとリンクになり、type や disabled は付かない
     */
    #[Test]
    public function hrefを渡すとリンクになる(): void
    {
        $html = Blade::render('<x-admin-search-button variant="reset" href="/admin/users">リセット</x-admin-search-button>');

        $this->assertStringContainsString('<a ', $html);
        $this->assertStringContainsString('href="/admin/users"', $html);
        $this->assertStringContainsString('bg-line-strong', $html);
        $this->assertStringNotContainsString('<button', $html);
        $this->assertStringNotContainsString('type=', $html);
    }

    /**
     * export で disabled を渡すと押せないボタンになる。type と追加のクラスは上書き・追加できる
     */
    #[Test]
    public function exportのdisabledとtypeの上書きとクラスの追加(): void
    {
        $html = Blade::render('<x-admin-search-button variant="export" :disabled="true" type="button" class="mt-2">エクスポート</x-admin-search-button>');

        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringNotContainsString('type="submit"', $html);
        $this->assertStringContainsString(' disabled>', $html);
        $this->assertStringContainsString('bg-admin-gray', $html);
        $this->assertStringContainsString('lg:w-[200px]', $html);
        $this->assertStringContainsString('mt-2', $html);
    }

    /**
     * disabled が false のときは押せる
     */
    #[Test]
    public function disabledがfalseなら押せる(): void
    {
        $html = Blade::render('<x-admin-search-button variant="export" :disabled="false">エクスポート</x-admin-search-button>');

        $this->assertStringNotContainsString(' disabled>', $html);
    }
}
