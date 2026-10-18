<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Nav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ログイン・ログアウトのテスト（Fortifyのルートを使う）
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * テスト用のユーザーを作成する
     */
    private function createUser(): User
    {
        return User::factory()->create([
            'email' => 'test@test.com',
            'password' => Hash::make('test1234'),
        ]);
    }

    /**
     * 未ログインでログインページが表示される
     */
    #[Test]
    public function 未ログインでログインページが表示される(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertViewIs('auth.login');
        $response->assertSee('ログイン');
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('type="password"', false);
        $response->assertSee('会員登録');
        $response->assertSee('&copy;Shioya farm', false);
    }

    /**
     * ログインページは共通ヘッダー・フッターではなく認証ページ用の構成で表示される
     */
    #[Test]
    public function ログインページは認証ページ用のレイアウトで表示される(): void
    {
        $response = $this->get(route('login'));

        $response->assertSee('href="'.route('top.index').'"', false);
        $response->assertSee('href="'.route('register').'"', false);
        $response->assertDontSee('お知らせ');
        $response->assertDontSee('送料案内');
    }

    /**
     * 正しい情報でログインするとトップページへ遷移し、認証済みになる
     */
    #[Test]
    public function 正しい情報でログインするとトップページへ遷移する(): void
    {
        $user = $this->createUser();

        $response = $this->post(route('login.store'), [
            'email' => 'test@test.com',
            'password' => 'test1234',
        ]);

        $response->assertRedirect(route('top.index'));
        $this->assertAuthenticatedAs($user);
    }

    /**
     * メールアドレスが未入力の場合はエラーになる
     */
    #[Test]
    public function メールアドレスが未入力の場合はエラーになる(): void
    {
        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => '',
            'password' => 'test1234',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
        $this->assertGuest();
    }

    /**
     * メールアドレスの形式が正しくない場合はエラーになる
     */
    #[Test]
    public function メールアドレスの形式が正しくない場合はエラーになる(): void
    {
        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'test',
            'password' => 'test1234',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'メールアドレスは有効なメールアドレス形式で入力してください']);
        $this->assertGuest();
    }

    /**
     * パスワードが未入力の場合はエラーになる
     */
    #[Test]
    public function パスワードが未入力の場合はエラーになる(): void
    {
        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'test@test.com',
            'password' => '',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
        $this->assertGuest();
    }

    /**
     * 何も入力しない場合は両方の入力欄のエラーが表示される
     */
    #[Test]
    public function 何も入力しない場合は両方のエラーが表示される(): void
    {
        $response = $this->followingRedirects()->from(route('login'))->post(route('login.store'), []);

        $response->assertSee('メールアドレスを入力してください');
        $response->assertSee('パスワードを入力してください');
    }

    /**
     * パスワードが違う場合は認証失敗のメッセージが表示されログインしない
     */
    #[Test]
    public function パスワードが違う場合はログインできない(): void
    {
        $this->createUser();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'test@test.com',
            'password' => 'wrongpass',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        $this->assertGuest();
    }

    /**
     * 未登録のメールアドレスの場合は認証失敗のメッセージが表示されログインしない
     */
    #[Test]
    public function 未登録のメールアドレスではログインできない(): void
    {
        $this->createUser();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'nobody@example.com',
            'password' => 'test1234',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        $this->assertGuest();
    }

    /**
     * エラーで戻ったときメールアドレスは残り、パスワードは残らない
     */
    #[Test]
    public function エラーで戻ったときメールアドレスだけ保持される(): void
    {
        $this->createUser();

        $response = $this->followingRedirects()->from(route('login'))->post(route('login.store'), [
            'email' => 'test@test.com',
            'password' => 'wrongpass',
        ]);

        $response->assertSee('メールアドレスまたはパスワードが正しくありません。');
        $response->assertSee('value="test@test.com"', false);
        $response->assertDontSee('wrongpass');
    }

    /**
     * 1分間に5回失敗すると6回目はログインできず、待ち時間を含むメッセージが表示される
     */
    #[Test]
    public function ログインに5回失敗すると6回目はスロットリングされる(): void
    {
        $this->createUser();
        $credentials = ['email' => 'test@test.com', 'password' => 'wrongpass'];

        foreach (range(1, 5) as $attempt) {
            $this->from(route('login'))->post(route('login.store'), $credentials)
                ->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        }

        // 6回目は正しいパスワードでもログインできない
        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'test@test.com',
            'password' => 'test1234',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $this->assertStringContainsString('秒後に再度お試しください。', session('errors')->first('email'));
    }

    /**
     * パスワードを配列で送ると500にならず、文字で入力するようエラーになる
     */
    #[Test]
    public function パスワードを配列で送るとエラーになる(): void
    {
        $this->createUser();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'test@test.com',
            'password' => ['x'],
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['password' => 'パスワードは文字で入力してください']);
        $this->assertGuest();
    }

    /**
     * メールアドレスを配列で送ると500にならず、文字で入力するようエラーになる
     */
    #[Test]
    public function メールアドレスを配列で送るとエラーになる(): void
    {
        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => ['test@test.com'],
            'password' => 'test1234',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'メールアドレスは文字で入力してください']);
        $this->assertGuest();
    }

    /**
     * メールアドレスを配列で送っても、エラーで戻ったログインページが500にならず表示される
     */
    #[Test]
    public function メールアドレスを配列で送ってもログインページが表示される(): void
    {
        $response = $this->followingRedirects()->from(route('login'))->post(route('login.store'), [
            'email' => ['test@test.com'],
            'password' => 'test1234',
        ]);

        $response->assertOk();
        $response->assertViewIs('auth.login');
        $response->assertSee('メールアドレスは文字で入力してください');
    }

    /**
     * JSONを期待するリクエストは、6回目に入力エラーではなく429で返る
     */
    #[Test]
    public function 期待する形式がjsonのリクエストは6回目に429が返る(): void
    {
        $this->createUser();
        $credentials = ['email' => 'test@test.com', 'password' => 'wrongpass'];

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('login.store'), $credentials)->assertUnprocessable();
        }

        $response = $this->postJson(route('login.store'), $credentials);

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
        $this->assertStringContainsString('秒後に再度お試しください。', $response->json('message'));
        $this->assertGuest();
    }

    /**
     * ログイン済みで/loginを開くとトップページへ移動する
     */
    #[Test]
    public function ログイン済みでログインページを開くとトップページへ移動する(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('top.index'));
    }

    /**
     * ログアウトするとトップページへ遷移し、未認証になる
     */
    #[Test]
    public function ログアウトするとトップページへ遷移する(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('top.index'));
        $this->assertGuest();
    }

    /**
     * ヘッダーナビのログインはログインページ、ログアウトはPOSTのログアウトを指す
     */
    #[Test]
    public function ヘッダーナビのリンク先がログインとログアウトになっている(): void
    {
        $guestLabels = collect(Nav::shopGuest())->keyBy('label');
        $this->assertSame(route('login'), $guestLabels['ログイン']['url']);

        foreach ([Nav::shopAuth(), Nav::shopCart()] as $items) {
            $logout = collect($items)->firstWhere('label', 'ログアウト');
            $this->assertSame(route('logout'), $logout['url']);
            $this->assertSame('post', $logout['method']);
        }
    }

    /**
     * viewportでピンチ拡大を禁止していない（ログインページ・トップページ）
     */
    #[Test]
    public function viewportでユーザーの拡大を禁止していない(): void
    {
        foreach (['/login', '/'] as $path) {
            $content = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('name="viewport" content="width=device-width,initial-scale=1.0"', $content);
            $this->assertStringNotContainsString('user-scalable', $content);
        }
    }
}
