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

    /**
     * 特商法に、代金引換の手数料（送料の設定から出した金額・税込）と、発送の目安（3〜5営業日）が書かれる
     */
    #[Test]
    public function 特商法に代金引換の手数料と発送の目安が載る(): void
    {
        $fee = number_format(config('shipping.cod_fee'));

        $response = $this->get(route('info.commercial-transaction'))->assertOk();

        $this->assertSame('330', $fee);
        $response->assertSee('別途送料がかかります。詳しくは送料案内ページを参照してください。');
        $response->assertSeeInOrder(['その他必要になる費用', '代金引換の場合は、別途手数料'.$fee.'円（税込）がかかります。', 'お支払い方法']);
        $response->assertSee('代金引換（手数料'.$fee.'円・税込）');
        $response->assertSee('お支払い確定後3〜5営業日以内に発送いたします。');
        $response->assertSee('代金引換はご注文確定後3〜5営業日以内に発送いたします。');
        $response->assertDontSee('3~5営業日');
    }
}
