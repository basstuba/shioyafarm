<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 管理者のログイン・ログアウトと、会員のログインとの分離のテスト
 */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * テスト用の管理者を作成する
     */
    private function createAdmin(): Admin
    {
        return Admin::factory()->create([
            'email' => 'admin@admin.com',
            'password' => Hash::make('admin1234'),
        ]);
    }

    /**
     * 管理者用ログインページが認証ページ用の構成で表示される
     */
    #[Test]
    public function 未ログインで管理者用ログインページが表示される(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk();
        $response->assertViewIs('auth.admin.login');
        $response->assertSee('<title>管理者用ログイン | Shioya Farm</title>', false);
        $response->assertSee('管理者用ログイン');
        $response->assertSee('しおや農園');
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('placeholder="例：sample@example.com"', false);
        $response->assertSee('placeholder="例：demo12345"', false);
        $response->assertSee('action="'.route('admin.login.store').'"', false);
        $response->assertSee('&copy;Shioya farm', false);
        // パスワード欄には表示切り替えが付く
        $response->assertSee('x-on:click="shown = ! shown"', false);
        $response->assertSee('lg:flex-row-reverse', false);
    }

    /**
     * 管理者用ログインページには、会員向けの案内・共通ヘッダー・フッターがなく、ロゴはリンクではない
     */
    #[Test]
    public function 管理者用ログインページは会員向けの案内とロゴのリンクを出さない(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertDontSee('パスワードをお忘れの方');
        $response->assertDontSee('会員登録');
        $response->assertDontSee('お知らせ');
        $response->assertDontSee('送料案内');
        $response->assertDontSee('href="'.route('top.index').'"', false);
        $response->assertDontSee('href="'.route('password.request').'"', false);
        $response->assertDontSee('href="'.route('register').'"', false);
    }

    /**
     * 背景画像は管理画面用で、見出しは黒、ボタンは緑
     */
    #[Test]
    public function 管理者用ログインページは管理画面用の背景画像と配色を使う(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertSee('storage/image/tool/admin-login-page-back-image.webp', false);
        $response->assertDontSee('tool/login-page-back-image.webp', false);
        $response->assertSee('<h1 class="text-[28px] font-medium text-ink lg:text-[32px]">管理者用ログイン</h1>', false);
        $response->assertSee('bg-accent text-ink hover:bg-accent-hover hover:text-white', false);
        $response->assertDontSee('text-accent lg:text-[32px]', false);
    }

    /**
     * 公開側のログイン・会員登録は、今までの背景画像とロゴのリンクのまま
     */
    #[Test]
    public function 公開側の認証ページは今までの背景画像とロゴのリンクを保つ(): void
    {
        foreach (['login', 'register', 'password.request'] as $name) {
            $response = $this->get(route($name));

            $response->assertOk();
            $response->assertSee('storage/image/tool/login-page-back-image.webp', false);
            $response->assertDontSee('admin-login-page-back-image.webp', false);
            $response->assertSee('<a class="flex items-center gap-1 no-underline" href="'.route('top.index').'">', false);
        }

        // 会員のログインは左にフォーム・右にパネル（左パネルの指定は管理者用だけ）
        $this->get(route('login'))
            ->assertDontSee('lg:flex-row-reverse', false)
            ->assertSee('text-accent lg:text-[32px]', false)
            ->assertSee('bg-primary text-white hover:bg-primary-hover', false);
    }

    /**
     * 正しい情報でログインすると /admin へ移動し、admin ガードで認証される
     */
    #[Test]
    public function 正しい情報でログインすると管理画面のトップへ遷移する(): void
    {
        $admin = $this->createAdmin();

        $response = $this->post(route('admin.login.store'), [
            'email' => 'admin@admin.com',
            'password' => 'admin1234',
        ]);

        $response->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('web');
    }

    /**
     * ログイン時にセッションIDを作り直す
     */
    #[Test]
    public function ログインするとセッション_i_dを作り直す(): void
    {
        $this->createAdmin();
        $this->get(route('admin.login'));
        $before = session()->getId();

        $this->post(route('admin.login.store'), [
            'email' => 'admin@admin.com',
            'password' => 'admin1234',
        ]);

        $this->assertNotSame($before, session()->getId());
    }

    /**
     * ログイン後の戻り先（url.intended）は使わない（会員のログインの戻り先だから）
     */
    #[Test]
    public function 管理者のログイン後は会員のログインの戻り先を使わない(): void
    {
        $this->createAdmin();

        $response = $this->withSession(['url.intended' => route('shop.items.index')])
            ->post(route('admin.login.store'), [
                'email' => 'admin@admin.com',
                'password' => 'admin1234',
            ]);

        $response->assertRedirect(route('admin.home'));
        $this->assertSame(route('shop.items.index'), session('url.intended'));
    }

    /**
     * メールアドレスが未入力の場合はエラーになる
     */
    #[Test]
    public function メールアドレスが未入力の場合はエラーになる(): void
    {
        $response = $this->post(route('admin.login.store'), [
            'email' => '',
            'password' => 'admin1234',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
        $this->assertGuest('admin');
    }

    /**
     * メールアドレスの形式が正しくない場合はエラーになり、メールアドレスは残りパスワードは残らない
     */
    #[Test]
    public function メールアドレスの形式が正しくない場合はエラーになる(): void
    {
        $response = $this->post(route('admin.login.store'), [
            'email' => 'admin',
            'password' => 'admin1234',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors(['email' => 'メールアドレスは有効なメールアドレス形式で入力してください']);
        $response->assertSessionHasInput('email', 'admin');
        $response->assertSessionMissing('_old_input.password');
        $this->assertGuest('admin');
    }

    /**
     * パスワードが未入力の場合はエラーになる
     */
    #[Test]
    public function パスワードが未入力の場合はエラーになる(): void
    {
        $response = $this->post(route('admin.login.store'), [
            'email' => 'admin@admin.com',
            'password' => '',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
        $this->assertGuest('admin');
    }

    /**
     * 文字列でない入力でも500にならず、エラーになる
     */
    #[Test]
    public function 配列の入力はエラーになる(): void
    {
        $response = $this->post(route('admin.login.store'), [
            'email' => ['admin@admin.com'],
            'password' => ['admin1234'],
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest('admin');
    }

    /**
     * パスワードが違う場合は共通のメッセージが出て、メールアドレスだけが残る
     */
    #[Test]
    public function パスワードが違う場合はログインできない(): void
    {
        $this->createAdmin();

        $response = $this->post(route('admin.login.store'), [
            'email' => 'admin@admin.com',
            'password' => 'wrongpass',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        $response->assertSessionHasInput('email', 'admin@admin.com');
        $response->assertSessionMissing('_old_input.password');
        $this->assertGuest('admin');

        $this->get(route('admin.login'))
            ->assertSee('メールアドレスまたはパスワードが正しくありません。')
            ->assertSee('value="admin@admin.com"', false);
    }

    /**
     * 会員のメールアドレス・パスワードでは管理者としてログインできない
     */
    #[Test]
    public function 会員の情報では管理者としてログインできない(): void
    {
        User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post(route('admin.login.store'), [
            'email' => 'member@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        $this->assertGuest('admin');
        $this->assertGuest('web');
    }

    /**
     * 管理者のメールアドレス・パスワードでは、公開側の /login からログインできない
     */
    #[Test]
    public function 管理者の情報では公開側のログインができない(): void
    {
        $this->createAdmin();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'admin@admin.com',
            'password' => 'admin1234',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        $this->assertGuest('web');
        $this->assertGuest('admin');
    }

    /**
     * 同じメールアドレスで5回失敗すると、6回目は待ち秒数を含むメッセージになる
     */
    #[Test]
    public function ログインの試行が1分に5回を超えると待ち時間を含むメッセージが出る(): void
    {
        $this->createAdmin();

        foreach (range(1, 5) as $i) {
            $this->post(route('admin.login.store'), ['email' => 'admin@admin.com', 'password' => 'wrongpass'])
                ->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        }

        $response = $this->post(route('admin.login.store'), ['email' => 'admin@admin.com', 'password' => 'admin1234']);

        $response->assertRedirect(route('admin.login'));
        $this->assertMatchesRegularExpression(
            '/^ログイン試行の規定数に達しました。\d+秒後に再度お試しください。$/u',
            session('errors')->first('email')
        );
        $this->assertGuest('admin');
    }

    /**
     * 管理者の試行回数は、別のメールアドレスや会員のログインとは数えない
     */
    #[Test]
    public function ログインの試行回数はメールアドレスごと・会員のログインとは別に数える(): void
    {
        $this->createAdmin();

        foreach (range(1, 5) as $i) {
            $this->post(route('admin.login.store'), ['email' => 'other@example.com', 'password' => 'wrongpass']);
            $this->post(route('login.store'), ['email' => 'admin@admin.com', 'password' => 'wrongpass']);
        }

        $this->post(route('admin.login.store'), ['email' => 'admin@admin.com', 'password' => 'admin1234'])
            ->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs(Admin::firstOrFail(), 'admin');
    }

    /**
     * 未ログインで /admin を開くと /admin/login へ移動する（会員の /login ではない）
     */
    #[Test]
    public function 未ログインで管理画面を開くと管理者用ログインへ移動する(): void
    {
        $this->get(route('admin.home'))->assertRedirect(route('admin.login'));
    }

    /**
     * 管理画面へのリダイレクトは、会員のログインの戻り先（url.intended）を書き換えない
     */
    #[Test]
    public function 管理画面へのリダイレクトは会員のログインの戻り先を残す(): void
    {
        $this->withSession(['url.intended' => route('shop.items.index')])
            ->get(route('admin.home'))
            ->assertRedirect(route('admin.login'));

        $this->assertSame(route('shop.items.index'), session('url.intended'));
    }

    /**
     * 会員としてログインしているだけでは /admin に入れない
     */
    #[Test]
    public function 会員のログインだけでは管理画面に入れない(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.home'))->assertRedirect(route('admin.login'));
        $this->actingAs($user)->post(route('admin.logout'))->assertRedirect(route('admin.login'));
    }

    /**
     * 管理者としてログイン中は /admin が開ける
     */
    #[Test]
    public function 管理者は管理画面のトップを開ける(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.home'));

        $response->assertOk();
        $response->assertViewIs('admin.index');
        $response->assertSee('<title>管理画面 | Shioya Farm</title>', false);
        $response->assertSee('管理画面');
        $response->assertSee('action="'.route('admin.logout').'"', false);
        $response->assertSee('ログアウト');
    }

    /**
     * 管理者としてログイン中に /admin/login を開くと /admin へ移動する
     */
    #[Test]
    public function ログイン中に管理者用ログインページを開くと管理画面のトップへ移動する(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')->get(route('admin.login'))->assertRedirect(route('admin.home'));
        $this->actingAs($admin, 'admin')
            ->post(route('admin.login.store'), ['email' => 'admin@admin.com', 'password' => 'admin1234'])
            ->assertRedirect(route('admin.home'));
    }

    /**
     * 会員のログイン中に公開側の /login を開く挙動（/ へ移動）は今までどおり
     */
    #[Test]
    public function 会員のログイン中に会員のログインページを開くと従来どおりトップへ移動する(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('login'))->assertRedirect('/');
    }

    /**
     * ログアウトすると /admin/login へ移動し、管理画面に入れなくなる
     */
    #[Test]
    public function ログアウトすると管理者用ログインへ移動する(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
        $this->get(route('admin.home'))->assertRedirect(route('admin.login'));
    }

    /**
     * 同じブラウザで会員と管理者の両方がログインしている状態にする（実際のログインを通す）
     */
    private function loginMemberAndAdmin(): void
    {
        User::factory()->create(['email' => 'member@example.com', 'password' => Hash::make('password123')]);
        $this->createAdmin();
        $this->post(route('login.store'), ['email' => 'member@example.com', 'password' => 'password123']);
        $this->post(route('admin.login.store'), ['email' => 'admin@admin.com', 'password' => 'admin1234']);
    }

    /**
     * 次のリクエストとして調べるため、メモリ上のログイン状態を捨てる（セッションの中身だけで判定する）
     */
    private function startNextRequest(): void
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
    }

    /**
     * 管理者のログアウトは、Laravel標準どおりセッション全体を破棄する（同じブラウザの会員もログアウトされる）
     */
    #[Test]
    public function 管理者のログアウトでセッション全体を破棄する(): void
    {
        $this->loginMemberAndAdmin();
        $before = session()->getId();

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $this->startNextRequest();
        $this->assertNotSame($before, session()->getId());
        $this->assertGuest('admin');
        $this->assertGuest('web');
        $this->assertNull(session('password_hash_admin'));
    }

    /**
     * 会員のログアウト（Fortify標準）でも、セッション全体を破棄する（同じブラウザの管理者もログアウトされる）
     */
    #[Test]
    public function 会員のログアウトでも管理者はログアウトされる(): void
    {
        $this->loginMemberAndAdmin();

        $this->post(route('logout'))->assertRedirect(route('top.index'));

        $this->startNextRequest();
        $this->assertGuest('web');
        $this->assertGuest('admin');
        $this->get(route('admin.home'))->assertRedirect(route('admin.login'));
    }

    /**
     * 会員の退会でも、セッション全体を破棄する（同じブラウザの管理者もログアウトされる）
     */
    #[Test]
    public function 会員の退会でも管理者はログアウトされる(): void
    {
        $this->loginMemberAndAdmin();

        $this->delete(route('shop.mypage.delete'))->assertRedirect(route('top.index'));

        $this->startNextRequest();
        $this->assertGuest('web');
        $this->assertGuest('admin');
    }

    /**
     * 管理者Aがログアウトした後に管理者Bがログインしても、強制ログアウトされない（実際のログイン・ログアウトを通す）
     */
    #[Test]
    public function 別の管理者がログインし直してもセッションは破棄されない(): void
    {
        Admin::factory()->create(['email' => 'a@admin.com', 'password' => Hash::make('admin1234')]);
        $adminB = Admin::factory()->create(['email' => 'b@admin.com', 'password' => Hash::make('other5678')]);

        $this->post(route('admin.login.store'), ['email' => 'a@admin.com', 'password' => 'admin1234'])
            ->assertRedirect(route('admin.home'));
        $this->get(route('admin.home'))->assertOk();
        $this->assertTrue(session()->has('password_hash_admin'));

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertFalse(session()->has('password_hash_admin'));

        $this->post(route('admin.login.store'), ['email' => 'b@admin.com', 'password' => 'other5678'])
            ->assertRedirect(route('admin.home'));
        $this->get(route('admin.home'))->assertOk();
        $this->assertAuthenticatedAs($adminB, 'admin');
    }

    /**
     * ログアウトはPOSTだけ（GETでは404）
     */
    #[Test]
    public function ログアウトは_ge_tでは行えない(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')->get('/admin/logout')->assertNotFound();
        $this->assertTrue(Auth::guard('admin')->check());
    }

    /**
     * 管理者のパスワードは暗号化して保存され、hidden で隠れる
     */
    #[Test]
    public function 管理者のパスワードはハッシュ化されて保存される(): void
    {
        $admin = Admin::factory()->create(['password' => 'plain-password']);

        $this->assertNotSame('plain-password', $admin->getRawOriginal('password'));
        $this->assertTrue(Hash::check('plain-password', $admin->getRawOriginal('password')));
        $this->assertArrayNotHasKey('password', $admin->toArray());
    }

    /**
     * CSRFトークンの検証を有効にする（テストでは既定でスキップされるため、環境名を変えて回避する）
     */
    private function enableCsrfCheck(): void
    {
        $this->app['env'] = 'local';
    }

    /**
     * 管理者のログインでトークンが古いとき、ログインページへ戻り、案内とメールアドレスだけが残る
     */
    #[Test]
    public function 管理者のログインでトークンが古いと案内つきでログインページへ戻る(): void
    {
        $this->createAdmin();
        $this->enableCsrfCheck();

        $response = $this->withSession(['_token' => 'valid-token'])
            ->post(route('admin.login.store'), ['_token' => 'stale-token', 'email' => 'admin@admin.com', 'password' => 'admin1234']);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('status', 'ページの有効期限が切れました。もう一度ログインしてください。');
        $response->assertSessionHasInput('email', 'admin@admin.com');
        $response->assertSessionMissing('_old_input.password');
        $this->assertGuest('admin');

        $page = $this->get(route('admin.login'))
            ->assertSee('ページの有効期限が切れました。もう一度ログインしてください。')
            ->assertSee('value="admin@admin.com"', false)
            ->getContent();
        // 案内の枠は管理画面の配色（グレー系）で、クリーム系のクラスは出ない
        $this->assertStringContainsString('border border-line-strong bg-surface', $page);
        $this->assertStringNotContainsString('cream', $page);
    }

    /**
     * 会員のログインでもトークンが古いとき、同じ案内つきでログインページへ戻る
     */
    #[Test]
    public function 会員のログインでトークンが古いと案内つきでログインページへ戻る(): void
    {
        $this->enableCsrfCheck();

        $response = $this->withSession(['_token' => 'valid-token'])
            ->post(route('login.store'), ['_token' => 'stale-token', 'email' => 'member@example.com', 'password' => 'password123']);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'ページの有効期限が切れました。もう一度ログインしてください。');
        $response->assertSessionHasInput('email', 'member@example.com');
        $response->assertSessionMissing('_old_input.password');

        // 公開側の案内の枠は今までどおりクリーム色
        $this->get(route('login'))
            ->assertSee('ページの有効期限が切れました。もう一度ログインしてください。')
            ->assertSee('bg-cream-200', false);
    }

    /**
     * 管理者のログアウトでトークンが古いとき、ログイン中なら管理画面のトップへ戻って案内を出す
     */
    #[Test]
    public function 管理者のログアウトでトークンが古いとログイン中なら管理画面のトップへ戻る(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');
        $this->enableCsrfCheck();

        $response = $this->withSession(['_token' => 'valid-token'])
            ->post(route('admin.logout'), ['_token' => 'stale-token']);

        $response->assertRedirect(route('admin.home'));
        $response->assertSessionHas('status', 'ページの有効期限が切れました。もう一度ログアウトしてください。');
        $this->assertTrue(Auth::guard('admin')->check());

        $page = $this->get(route('admin.home'))->assertOk()
            ->assertSee('ページの有効期限が切れました。もう一度ログアウトしてください。')
            ->getContent();
        $this->assertStringContainsString('border border-line-strong bg-surface', $page);
        $this->assertStringNotContainsString('cream', $page);
    }

    /**
     * 管理者のログアウトでトークンが古く、すでにログアウトしているときは、ログインページへ移動する
     */
    #[Test]
    public function 管理者のログアウトでトークンが古く未ログインならログインページへ移動する(): void
    {
        $this->enableCsrfCheck();

        $this->withSession(['_token' => 'valid-token'])
            ->post(route('admin.logout'), ['_token' => 'stale-token'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionMissing('status');
    }

    /**
     * 対象外のPOSTや、JSONを期待するリクエストは、従来どおり419になる
     */
    #[Test]
    public function 対象外のリクエストでトークンが古いと419のままになる(): void
    {
        $this->enableCsrfCheck();

        $this->withSession(['_token' => 'valid-token'])
            ->post(route('top.inquiry.store'), ['_token' => 'stale-token'])
            ->assertStatus(419);

        $this->withSession(['_token' => 'valid-token'])
            ->postJson(route('admin.login.store'), ['_token' => 'stale-token'])
            ->assertStatus(419);

        $this->withSession(['_token' => 'valid-token'])
            ->withHeader('X-Livewire', 'true')
            ->post(route('login.store'), ['_token' => 'stale-token'])
            ->assertStatus(419);
    }

    /**
     * 管理者用ログインの入力欄は、グレーの枠線・ink のフォーカス枠・muted の「表示」の文字色になる
     */
    #[Test]
    public function 管理者用ログインの入力欄はグレー系の配色になる(): void
    {
        $html = $this->get(route('admin.login'))->getContent();

        $this->assertStringContainsString('border border-line-strong', $html);
        $this->assertStringContainsString('focus-visible:outline-ink', $html);
        $this->assertStringContainsString('text-muted', $html);
        $this->assertStringContainsString('placeholder:text-line-strong', $html);
        $this->assertStringNotContainsString('border-primary-dark', $html);
        $this->assertStringNotContainsString('outline-primary-dark', $html);
        $this->assertStringNotContainsString('text-primary-text', $html);
    }
}
