<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 利用規約・プライバシーポリシー・特商法の表示とSPの余白のテスト
 */
class LegalPagesTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function pages(): array
    {
        return [
            '利用規約' => ['info.terms-of-service', '利用規約'],
            'プライバシーポリシー' => ['info.privacy-policy', 'プライバシーポリシー'],
            '特商法' => ['info.commercial-transaction', '特定商取引法に基づく表記'],
        ];
    }

    /**
     * 見出しがヘッダーに詰まらないよう、SPでは見出しの上に余白を足す（PCは今までのまま）
     */
    #[Test]
    #[DataProvider('pages')]
    public function 見出しの上に_s_p用の余白がある(string $route, string $heading): void
    {
        $this->get(route($route))
            ->assertOk()
            ->assertSee('<h2 class="my-5 text-center text-2xl font-semibold text-ink mt-10 md:mt-5">'.$heading.'</h2>', false);
    }

    /**
     * 特商法は、見出しの下の区切り線の余白がSPで40px、md以上で80px
     */
    #[Test]
    public function 特商法の区切り線の余白は_s_pで小さくなる(): void
    {
        $this->get(route('info.commercial-transaction'))
            ->assertOk()
            ->assertSee('<hr class="my-10 border-line-strong md:my-20">', false);
    }
}
