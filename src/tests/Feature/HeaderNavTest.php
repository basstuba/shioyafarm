<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Nav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ヘッダーナビ（トップページ・静的ページ）のテスト
 */
class HeaderNavTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ナビを確認するトップページ・静的ページのルート名
     *
     * @return array<string, array{string}>
     */
    public static function topNavRoutes(): array
    {
        return [
            'トップページ' => ['top.index'],
            '送料案内' => ['info.shipping-fee'],
            '利用規約' => ['info.terms-of-service'],
            'プライバシーポリシー' => ['info.privacy-policy'],
            '特定商取引法に基づく表記' => ['info.commercial-transaction'],
        ];
    }

    /**
     * ログイン中はマイページを表示し、ログアウトは表示しない
     */
    #[Test]
    #[DataProvider('topNavRoutes')]
    public function ログイン中はマイページが表示されログアウトは表示されない(string $routeName): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route($routeName));

        $response->assertOk();
        $response->assertSeeInOrder(['お知らせ', 'しおや農園について', 'オンラインショップ', 'マイページ']);
        $response->assertDontSee('ログアウト');
    }

    /**
     * 未ログインのときはマイページを表示しない
     */
    #[Test]
    #[DataProvider('topNavRoutes')]
    public function 未ログインのときはマイページが表示されない(string $routeName): void
    {
        $response = $this->get(route($routeName));

        $response->assertOk();
        $response->assertSeeInOrder(['お知らせ', 'しおや農園について', 'オンラインショップ']);
        $response->assertDontSee('マイページ');
        $response->assertDontSee('ログアウト');
    }

    /**
     * ログイン中のトップ用ナビは未ログイン用に「マイページ」を足したものになる
     */
    #[Test]
    public function ログイン中のトップ用ナビは未ログイン用にマイページを足したものになる(): void
    {
        $guestLabels = collect(Nav::top())->pluck('label')->all();
        $authLabels = collect(Nav::topAuth())->pluck('label')->all();

        $this->assertSame([...$guestLabels, 'マイページ'], $authLabels);
    }
}
