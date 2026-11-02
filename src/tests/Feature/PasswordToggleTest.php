<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * パスワード欄の表示／非表示の切り替えボタンのテスト
 */
class PasswordToggleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 切り替えボタンの数を数える（切り替えのクリック処理の式の数で数える）
     */
    private function toggleCount(string $html): int
    {
        return substr_count($html, 'x-on:click="shown = ! shown"');
    }

    /**
     * 指定の入力欄に対応する切り替えボタンがあるか調べる
     */
    private function assertHasToggle(string $html, string $inputId): void
    {
        $this->assertStringNotContainsString('aria-pressed', $html);
        $this->assertMatchesRegularExpression(
            '/<button[^>]*type="button"[^>]*aria-controls="'.preg_quote($inputId, '/').'"[^>]*>\s*<span x-text="shown \? \'非表示\' : \'表示\'">表示<\/span>\s*<\/button>/',
            $html
        );
    }

    /**
     * ログインページのパスワード欄だけに切り替えボタンが付く
     */
    #[Test]
    public function ログインページのパスワード欄に切り替えボタンが付く(): void
    {
        $response = $this->get(route('login'))->assertOk();
        $html = $response->getContent();

        $this->assertSame(1, $this->toggleCount($html));
        $this->assertHasToggle($html, 'password');
        $this->assertStringNotContainsString('aria-controls="email"', $html);
        // 初期の HTML は今までどおり type="password"
        $this->assertMatchesRegularExpression('/<input[^>]*id="password"[^>]*type="password"/', $html);
        // メールアドレス欄は包みの x-data の外
        $this->assertSame(1, substr_count($html, 'x-data="{ shown: false }"'));
    }

    /**
     * 会員登録ページはパスワードと確認用パスワードの2つに付く
     */
    #[Test]
    public function 会員登録ページの2つのパスワード欄に切り替えボタンが付く(): void
    {
        $html = $this->get(route('register'))->assertOk()->getContent();

        $this->assertSame(2, $this->toggleCount($html));
        $this->assertHasToggle($html, 'password');
        $this->assertHasToggle($html, 'password_confirmation');
        $this->assertSame(2, substr_count($html, 'x-data="{ shown: false }"'));
    }

    /**
     * パスワード再設定ページは2つの欄に付く
     */
    #[Test]
    public function パスワード再設定ページの2つのパスワード欄に切り替えボタンが付く(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $html = $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()->getContent();

        $this->assertSame(2, $this->toggleCount($html));
        $this->assertHasToggle($html, 'password');
        $this->assertHasToggle($html, 'password_confirmation');
    }

    /**
     * 登録情報変更ページは3つの欄に付き、メールアドレスなどには付かない
     */
    #[Test]
    public function 登録情報変更ページの3つのパスワード欄に切り替えボタンが付く(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('shop.mypage.edit'))->assertOk()->getContent();

        $this->assertSame(3, $this->toggleCount($html));
        $this->assertHasToggle($html, 'password');
        $this->assertHasToggle($html, 'password_confirmation');
        $this->assertHasToggle($html, 'current_password');
        $this->assertStringNotContainsString('aria-controls="email"', $html);
        $this->assertStringNotContainsString('aria-controls="tell"', $html);
    }

    /**
     * パスワード以外の欄にはボタンも x-data も付かない
     */
    #[Test]
    public function パスワード以外の欄には切り替えボタンが付かない(): void
    {
        $this->withViewErrors([]);
        $html = Blade::render('<x-auth-input name="email" label="メールアドレス" type="email" />');

        $this->assertStringNotContainsString('<button', $html);
        $this->assertStringNotContainsString('x-data', $html);
        $this->assertStringNotContainsString('x-bind:type', $html);
    }

    /**
     * パスワード欄は value を出さず、ボタンの名前は「表示」だけでラベル名を含まない
     */
    #[Test]
    public function パスワード欄は値を出さずボタンの名前にラベル名を含まない(): void
    {
        $this->withViewErrors([]);
        $html = Blade::render('<x-auth-input name="password" label="パスワード" type="password" />');

        $this->assertStringNotContainsString('value=', $html);
        $this->assertStringNotContainsString('aria-label', $html);
        $this->assertStringContainsString('type="password"', $html);
        $this->assertStringContainsString('x-bind:type="shown ? \'text\' : \'password\'"', $html);
    }

    /**
     * 伏せ字へ戻すのは、送信時と bfcache からの復元時だけ（初回の読み込み時の pageshow では戻さない）
     */
    #[Test]
    public function 伏せ字に戻すのは送信時と復元時だけ(): void
    {
        $this->withViewErrors([]);
        $html = Blade::render('<x-auth-input name="password" label="パスワード" type="password" />');

        $this->assertStringContainsString('x-on:pageshow.window="if ($event.persisted) shown = false"', $html);
        $this->assertStringContainsString('x-on:submit.window="shown = false;', $html);
    }

    /**
     * 認証レイアウトで Livewire のスタイルとスクリプト（Alpine.js 同梱）を読み込む
     */
    #[Test]
    public function 認証レイアウトで_livewireを読み込む(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        // スクリプトは body の最後（Livewire の出力の細かい文言には依存しない）
        $this->assertMatchesRegularExpression('#<script[^>]+src="[^"]*livewire[^"]*\.js#', $html);
        preg_match('#<script[^>]+src="[^"]*livewire[^"]*\.js#', $html, $matches, PREG_OFFSET_CAPTURE);
        $this->assertGreaterThan(strpos($html, '</main>'), $matches[0][1]);
    }
}
