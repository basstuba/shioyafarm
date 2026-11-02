<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * thanks-page コンポーネント（購入完了・予約完了ページの共通部分）のテスト
 */
class ThanksPageComponentTest extends TestCase
{
    /**
     * 写真・見出し・本文・ホームへのリンクが出て、色は引数のクラスになる
     */
    #[Test]
    public function 写真と見出しと本文とホームへのリンクが出る(): void
    {
        $html = Blade::render('<x-thanks-page color-class="text-leaf"><x-slot:heading>ありがとうございました。<br class="md:hidden">お届けします。</x-slot:heading>本文です。</x-thanks-page>');

        $this->assertStringContainsString('storage/image/tool/thanks-page-photo.webp', $html);
        $this->assertMatchesRegularExpression('#<h1[^>]*text-leaf[^>]*>ありがとうございました。<br class="md:hidden">お届けします。</h1>#', $html);
        $this->assertStringContainsString('本文です。', $html);
        $this->assertStringContainsString('href="'.route('top.index').'"', $html);
        $this->assertStringContainsString('ホームに戻る', $html);
    }

    /**
     * 見出しを1行にし始める画面幅は、既定が md、lg を指定すると lg だけになる
     */
    #[Test]
    public function 見出しの改行なしにする画面幅を指定できる(): void
    {
        $md = Blade::render('<x-thanks-page color-class="text-leaf"><x-slot:heading>見出し</x-slot:heading>本文</x-thanks-page>');
        $lg = Blade::render('<x-thanks-page color-class="text-reserve-text" nowrap="lg"><x-slot:heading>見出し</x-slot:heading>本文</x-thanks-page>');

        $this->assertStringContainsString('md:whitespace-nowrap', $md);
        $this->assertStringNotContainsString('lg:whitespace-nowrap', $md);
        $this->assertStringContainsString('lg:whitespace-nowrap', $lg);
        $this->assertStringNotContainsString('md:whitespace-nowrap', $lg);
        $this->assertStringContainsString('text-reserve-text', $lg);
        $this->assertStringNotContainsString('text-leaf', $lg);
    }
}
