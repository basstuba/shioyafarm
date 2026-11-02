<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 404ページ（errors/404.blade.php）のテスト
 */
class NotFoundPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 存在しないURLは404で、共通ヘッダー・フッター付きの案内ページを出す（AC-5）
     */
    #[Test]
    public function 存在しない_ur_lは404の案内ページを表示する(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('<title>ページが見つかりません | Shioya Farm</title>', false)
            ->assertSee('ページが見つかりませんでした')
            ->assertSee('お探しのページは移動または削除された可能性があります。')
            ->assertSee('href="'.route('top.index').'"', false)
            ->assertSee('トップページへ')
            ->assertSee('オンラインショップ')
            ->assertSee('利用規約');
    }

    /**
     * 存在しないURLでもセッションが効き、ログイン中はヘッダーにマイページが出る（未ログインでは出ない）
     */
    #[Test]
    public function 存在しない_ur_lでもログイン中のヘッダーになる(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertDontSee('マイページ');

        $this->actingAs(User::factory()->create())
            ->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('ページが見つかりませんでした')
            ->assertSee('マイページ');
    }

    /**
     * ルートはあるがモデルが見つからない404（存在しない商品）も同じページを出す
     */
    #[Test]
    public function 存在しない商品も同じ404ページを表示する(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('shop.items.show', 999999))
            ->assertNotFound()
            ->assertSee('ページが見つかりませんでした')
            ->assertSee('マイページ');
    }

    /**
     * 存在しないURLへのPOSTなども404になる
     */
    #[Test]
    public function 存在しない_ur_lへの_pos_tも404になる(): void
    {
        $this->post('/no-such-page')->assertNotFound();
        $this->put('/no-such-page')->assertNotFound();
    }

    /**
     * POST専用のURLをGETで開いたときも、同じ404ページを出す（fallback は GET に一致するため）
     */
    #[Test]
    public function pos_t専用の_ur_lを_ge_tで開くと404ページになる(): void
    {
        $this->get(route('shop.checkout.payment'))
            ->assertNotFound()
            ->assertSee('ページが見つかりませんでした');
    }
}
