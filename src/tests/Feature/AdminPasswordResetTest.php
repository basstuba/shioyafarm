<?php

namespace Tests\Feature;

use App\Mail\AdminPasswordResetMail;
use App\Mail\PasswordResetMail;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * 管理者のパスワード再設定（再設定メールの送信・メールの内容・新しいパスワードの設定）のテスト
 *
 * 会員の再設定（Fortify。PasswordResetTest）とは別の仕組み（パスワードブローカー admins）なので、混ざらないことも確かめる。
 */
class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const string SENT_MESSAGE = 'ご登録のメールアドレス宛てに、パスワード再設定用のメールをお送りしました。';

    private const string INVALID_LINK_MESSAGE = 'このリンクは無効か、有効期限が切れています。もう一度再設定メールを送信してください。';

    private const string DONE_MESSAGE = 'パスワードを再設定しました。新しいパスワードでログインしてください。';

    private const string EXPIRED_MESSAGE = 'ページの有効期限が切れました。もう一度お試しください。';

    /**
     * 差出人は config('mail.from') から出るため、.env に左右されないよう、既定の値にそろえる
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.from.address' => 'shioyafarm@example.com', 'mail.from.name' => 'しおや農園']);
    }

    /**
     * 再設定のテスト用の管理者を作る
     */
    private function createAdmin(): Admin
    {
        return Admin::factory()->create([
            'name' => '再設定管理者',
            'email' => 'admin-reset@example.com',
            'password' => Hash::make('oldpass123'),
        ]);
    }

    /**
     * 有効な再設定トークンを作る（メールに載るものと同じ）
     */
    private function createToken(Admin $admin): string
    {
        return Password::broker('admins')->createToken($admin);
    }

    /**
     * 新しいパスワードの設定をPOSTする（再設定ページから送ったことにする）
     *
     * @param  array<string, mixed>  $overrides
     */
    private function postReset(string $token, array $overrides = []): TestResponse
    {
        return $this->from(route('admin.password.reset', ['token' => $token, 'email' => 'admin-reset@example.com']))
            ->post(route('admin.password.update'), $overrides + [
                'token' => $token,
                'email' => 'admin-reset@example.com',
                'password' => 'newpass123',
                'password_confirmation' => 'newpass123',
            ]);
    }

    /**
     * メールの本文のテキスト版
     */
    private function renderText(AdminPasswordResetMail $mail): string
    {
        $content = $mail->content();

        return app(Markdown::class)->renderText($content->markdown, $content->with)->toHtml();
    }

    /**
     * CSRFトークンの検証を有効にする（テストでは既定でスキップされるため、環境名を変えて回避する）
     */
    private function enableCsrfCheck(): void
    {
        $this->app['env'] = 'local';
    }

    // ---- 設定 ----

    /**
     * 管理者用のブローカーは会員とは別のテーブルで、有効期限60分・再送信の待ち時間60秒
     */
    #[Test]
    public function 管理者用のブローカーは別のテーブルで有効期限は60分で再送信は60秒待つ設定になっている(): void
    {
        $this->assertSame('admins', config('auth.passwords.admins.provider'));
        $this->assertSame('admin_password_reset_tokens', config('auth.passwords.admins.table'));
        $this->assertSame(60, config('auth.passwords.admins.expire'));
        $this->assertSame(60, config('auth.passwords.admins.throttle'));
        $this->assertNotSame(config('auth.passwords.users.table'), config('auth.passwords.admins.table'));
    }

    // ---- 送信ページ ----

    /**
     * 送信ページは管理者用ログインと同じ構成で、PCは左にフォーム・右にパネル
     */
    #[Test]
    public function 送信ページが表示される(): void
    {
        $response = $this->get(route('admin.password.request'));

        $response->assertOk();
        $response->assertViewIs('auth.admin.forgot-password');
        $response->assertSee('<title>パスワード再設定 | Shioya Farm</title>', false);
        $response->assertSee('<h1 class="text-[28px] font-medium text-ink lg:text-[32px]">パスワード再設定</h1>', false);
        $response->assertSee('ご登録のメールアドレスを');
        $response->assertSee('name="email"', false);
        $response->assertSee('action="'.route('admin.password.email').'"', false);
        $response->assertSee('再設定メールを送信');
        $response->assertSee('&copy;Shioya farm', false);
        // 管理画面用の背景画像、緑のボタン、グレー系の入力欄
        $response->assertSee('storage/image/tool/admin-login-page-back-image.webp', false);
        $response->assertDontSee('tool/login-page-back-image.webp', false);
        $response->assertSee('bg-accent text-ink hover:bg-accent-hover hover:text-white', false);
        $response->assertSee('border border-line-strong', false);
        $response->assertDontSee('border-primary-dark', false);
        $response->assertDontSee('text-accent lg:text-[32px]', false);
        // 左にフォーム・右にパネル（ログインの lg:flex-row-reverse は付けない）
        $response->assertDontSee('lg:flex-row-reverse', false);
        $response->assertDontSee(self::SENT_MESSAGE);
    }

    /**
     * 送信ページには、ログインへ戻る文字リンクがあり、ログインへ戻る枠線ボタン・ロゴのリンク・共通ヘッダー・フッター・会員向けの案内はない
     */
    #[Test]
    public function 送信ページはログインへ戻る文字リンクだけを出しロゴはリンクにしない(): void
    {
        $response = $this->get(route('admin.password.request'));

        $response->assertSee('ログインへ戻る');
        $response->assertSee('href="'.route('admin.login').'"', false);
        $response->assertDontSee('href="'.route('top.index').'"', false);
        $response->assertDontSee('href="'.route('login').'"', false);
        $response->assertDontSee('href="'.route('register').'"', false);
        $response->assertDontSee('border-primary');
        $response->assertDontSee('お知らせ');
        $response->assertDontSee('会員登録');
    }

    /**
     * ログインへ戻るリンクは、SPで高さ44px以上（min-h-11）
     */
    #[Test]
    public function ログインへ戻るリンクはスマホで高さ44px以上になる(): void
    {
        $this->get(route('admin.password.request'))
            ->assertSee('class="-mt-2 inline-flex min-h-11 items-center justify-center text-base text-ink underline lg:-mt-4" href="'.route('admin.login').'"', false);
    }

    // ---- メール送信 ----

    /**
     * 登録済みの管理者には再設定メールが1通届き、送信ページに案内が出る
     */
    #[Test]
    public function 登録済みのメールアドレスには再設定メールを送って案内を表示する(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();

        $response = $this->from(route('admin.password.request'))->post(route('admin.password.email'), ['email' => 'admin-reset@example.com']);

        $response->assertRedirect(route('admin.password.request'));
        $response->assertSessionHasNoErrors();
        Mail::assertSentCount(1);
        Mail::assertSent(AdminPasswordResetMail::class, fn (AdminPasswordResetMail $mail): bool => $mail->hasTo('admin-reset@example.com') && $mail->admin->is($admin));
        $this->assertDatabaseHas('admin_password_reset_tokens', ['email' => 'admin-reset@example.com']);
        $this->followRedirects($response)->assertSee(self::SENT_MESSAGE);
    }

    /**
     * 案内の枠はグレー
     */
    #[Test]
    public function 送信後の案内の枠はグレーで表示される(): void
    {
        Mail::fake();
        $this->createAdmin();

        $html = $this->followingRedirects()->from(route('admin.password.request'))
            ->post(route('admin.password.email'), ['email' => 'admin-reset@example.com'])
            ->getContent();

        $this->assertStringContainsString('class="rounded-[10px] border border-line-strong bg-surface px-4 py-3 text-base text-ink" role="status">'.self::SENT_MESSAGE, $html);
        $this->assertStringNotContainsString('bg-cream-200', $html);
    }

    /**
     * 管理者でないメールアドレス（未登録）でも同じ案内を表示し、メールは送らない
     */
    #[Test]
    public function 未登録のメールアドレスでも同じ案内を表示してメールは送らない(): void
    {
        Mail::fake();

        $response = $this->from(route('admin.password.request'))->post(route('admin.password.email'), ['email' => 'not-registered@example.com']);

        $response->assertRedirect(route('admin.password.request'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', self::SENT_MESSAGE);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('admin_password_reset_tokens', 0);
    }

    /**
     * 会員のメールアドレスでも同じ案内を表示し、メールは送らない
     */
    #[Test]
    public function 会員のメールアドレスでも同じ案内を表示してメールは送らない(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'member@example.com']);

        $response = $this->from(route('admin.password.request'))->post(route('admin.password.email'), ['email' => 'member@example.com']);

        $response->assertRedirect(route('admin.password.request'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', self::SENT_MESSAGE);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('admin_password_reset_tokens', 0);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    /**
     * 1分以内の再送信でも同じ案内を表示し、2通目は送らない
     */
    #[Test]
    public function 一分以内の再送信でも同じ案内を表示して二通目は送らない(): void
    {
        Mail::fake();
        $this->createAdmin();

        $this->post(route('admin.password.email'), ['email' => 'admin-reset@example.com']);
        $response = $this->from(route('admin.password.request'))->post(route('admin.password.email'), ['email' => 'admin-reset@example.com']);

        $response->assertRedirect(route('admin.password.request'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', self::SENT_MESSAGE);
        Mail::assertSentCount(1);
    }

    /**
     * 60秒を過ぎると、もう一度送れる
     */
    #[Test]
    public function 一分を過ぎると再送信できる(): void
    {
        Mail::fake();
        $this->createAdmin();

        $this->post(route('admin.password.email'), ['email' => 'admin-reset@example.com']);
        $this->travel(61)->seconds();
        $this->post(route('admin.password.email'), ['email' => 'admin-reset@example.com']);

        Mail::assertSentCount(2);
    }

    /**
     * メールアドレスの大文字小文字は区別しない
     */
    #[Test]
    public function メールアドレスは大文字で入力しても再設定メールが届く(): void
    {
        Mail::fake();
        $this->createAdmin();

        $this->post(route('admin.password.email'), ['email' => 'ADMIN-RESET@Example.com']);

        Mail::assertSentCount(1);
    }

    /**
     * メールの送信に失敗したときは、保存したトークンを残さない（残すと60秒間は再送信できず、メールも届かない）
     */
    #[Test]
    public function メールの送信に失敗したらトークンを削除して再送信できる(): void
    {
        $this->createAdmin();
        // 最初の1回だけ、送信の直前に例外を投げる（送信の失敗を再現する）
        $failed = false;
        Event::listen(MessageSending::class, function () use (&$failed): void {
            if (! $failed) {
                $failed = true;
                throw new RuntimeException('送信に失敗');
            }
        });
        $this->withoutExceptionHandling();

        try {
            $this->post(route('admin.password.email'), ['email' => 'admin-reset@example.com']);
            $this->fail('送信の失敗が例外として投げ直されませんでした');
        } catch (RuntimeException $e) {
            $this->assertSame('送信に失敗', $e->getMessage());
        }

        $this->assertDatabaseCount('admin_password_reset_tokens', 0);

        // 待たずに、もう一度送信できる
        $this->post(route('admin.password.email'), ['email' => 'admin-reset@example.com']);
        $this->assertDatabaseCount('admin_password_reset_tokens', 1);
        $this->assertTrue($failed);
    }

    /**
     * メールアドレスが未入力・形式不正・文字列でないときはエラーにして、メールは送らない
     */
    #[Test]
    public function 送信のメールアドレスが不正ならエラーになりメールは送らない(): void
    {
        Mail::fake();
        $this->createAdmin();

        $this->from(route('admin.password.request'))->post(route('admin.password.email'), ['email' => ''])
            ->assertRedirect(route('admin.password.request'))
            ->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);

        $this->from(route('admin.password.request'))->post(route('admin.password.email'), ['email' => 'abc'])
            ->assertSessionHasErrors(['email' => 'メールアドレスは有効なメールアドレス形式で入力してください']);

        $this->from(route('admin.password.request'))->post(route('admin.password.email'), ['email' => ['admin-reset@example.com']])
            ->assertSessionHasErrors(['email' => 'メールアドレスは文字で入力してください']);

        Mail::assertNothingSent();
    }

    /**
     * 入力エラーは送信ページの入力欄の近くに表示され、入力したメールアドレスは残る
     */
    #[Test]
    public function 送信ページに戻ったときエラーメッセージが表示される(): void
    {
        $response = $this->followingRedirects()->from(route('admin.password.request'))->post(route('admin.password.email'), ['email' => 'abc']);

        $response->assertSee('メールアドレスは有効なメールアドレス形式で入力してください');
        $response->assertSee('value="abc"', false);
        $response->assertDontSee(self::SENT_MESSAGE);
    }

    // ---- メールの内容 ----

    /**
     * 再設定メールの差出人・件名・宛先
     */
    #[Test]
    public function 再設定メールの差出人と件名と宛先(): void
    {
        $mail = new AdminPasswordResetMail($this->createAdmin(), 'sample-token');

        $mail->assertTo('admin-reset@example.com');
        $mail->assertFrom('shioyafarm@example.com', 'しおや農園');
        $mail->assertHasSubject('【しおや農園】管理画面のパスワード再設定のご案内');
    }

    /**
     * 再設定メールの本文は日本語で、英語の定型文を含まない（HTML版とテキスト版）
     */
    #[Test]
    public function 再設定メールの本文は日本語で英語の定型文を含まない(): void
    {
        $mail = new AdminPasswordResetMail($this->createAdmin(), 'sample-token');
        $url = route('admin.password.reset', ['token' => 'sample-token', 'email' => 'admin-reset@example.com']);

        $html = $mail->render();

        $this->assertStringContainsString('再設定管理者 様', html_entity_decode(strip_tags($html)));
        $this->assertStringContainsString('管理画面のパスワードの再設定', $html);
        $this->assertStringContainsString('パスワードを再設定する', $html);
        $this->assertStringContainsString('href="'.str_replace('&', '&amp;', $url).'"', $html);
        $this->assertStringContainsString('/admin/reset-password/sample-token?email=', $html);
        $this->assertStringContainsString('有効期限は、お送りしてから60分', $html);
        $this->assertStringContainsString('破棄してください', $html);
        foreach (['Hello', 'Regards', 'Reset Password', 'having trouble', 'Laravel'] as $english) {
            $this->assertStringNotContainsString($english, $html);
        }

        $text = html_entity_decode($this->renderText($mail));
        $this->assertStringContainsString('再設定管理者 様', $text);
        $this->assertStringContainsString('パスワードを再設定する', $text);
        $this->assertStringContainsString($url, $text);
        $this->assertStringContainsString('有効期限は、お送りしてから60分', $text);
        $this->assertStringContainsString('破棄してください', $text);
        foreach (['Hello', 'Regards', 'Reset Password', 'having trouble', 'Laravel'] as $english) {
            $this->assertStringNotContainsString($english, $text);
        }
    }

    /**
     * メールの有効期限の表示は管理者用ブローカーの設定値（分）から出る
     */
    #[Test]
    public function メールの有効期限の表示は設定値から出る(): void
    {
        config(['auth.passwords.admins.expire' => 30]);

        $html = (new AdminPasswordResetMail($this->createAdmin(), 'sample-token'))->render();

        $this->assertStringContainsString('有効期限は、お送りしてから30分', $html);
    }

    /**
     * 管理者名に含まれる記号は、メールでHTMLとして解釈されない
     */
    #[Test]
    public function 管理者名の記号は無害化される(): void
    {
        $admin = $this->createAdmin();
        $admin->forceFill(['name' => '<b>太</b>'])->save();

        $html = (new AdminPasswordResetMail($admin, 'sample-token'))->render();

        $this->assertStringNotContainsString('<b>太</b>', $html);
    }

    /**
     * 送信されたメールのリンクを開くと、新しいパスワードの設定ページが表示される
     */
    #[Test]
    public function メールのリンクで再設定ページを開ける(): void
    {
        Mail::fake();
        $this->createAdmin();
        $this->post(route('admin.password.email'), ['email' => 'admin-reset@example.com']);

        $url = null;
        Mail::assertSent(AdminPasswordResetMail::class, function (AdminPasswordResetMail $mail) use (&$url): bool {
            preg_match('/href="([^"]*admin\/reset-password[^"]*)"/', html_entity_decode($mail->render()), $matches);
            $url = $matches[1] ?? null;

            return true;
        });

        $this->assertNotNull($url);
        $this->get($url)->assertOk()->assertViewIs('auth.admin.reset-password')->assertSee('name="email" value="admin-reset@example.com"', false);
    }

    // ---- 再設定ページ ----

    /**
     * 新しいパスワードの設定ページが表示される
     */
    #[Test]
    public function 再設定ページが表示される(): void
    {
        $token = $this->createToken($this->createAdmin());

        $response = $this->get(route('admin.password.reset', ['token' => $token, 'email' => 'admin-reset@example.com']));

        $response->assertOk();
        $response->assertViewIs('auth.admin.reset-password');
        $response->assertSee('<title>新しいパスワードの設定 | Shioya Farm</title>', false);
        $response->assertSee('<h1 class="text-[28px] font-medium text-ink lg:text-[32px]">新しいパスワードの設定</h1>', false);
        $response->assertSee('action="'.route('admin.password.update').'"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
        $response->assertSee('type="password"', false);
        // パスワード欄には「表示」の切り替えが2つ付く（Livewire同梱のAlpine）
        $this->assertSame(2, substr_count($response->getContent(), 'x-on:click="shown = ! shown"'));
        $response->assertSee('name="token" value="'.$token.'"', false);
        $response->assertSee('name="email" value="admin-reset@example.com"', false);
        $response->assertSee('パスワードを再設定する');
        $response->assertSee('storage/image/tool/admin-login-page-back-image.webp', false);
        $response->assertSee('bg-accent text-ink hover:bg-accent-hover hover:text-white', false);
        $response->assertSee('border border-line-strong', false);
        $response->assertDontSee('border-primary-dark', false);
        $response->assertDontSee('lg:flex-row-reverse', false);
        $response->assertDontSee('href="'.route('top.index').'"', false);
        $response->assertSee('href="'.route('admin.login').'"', false);
        $response->assertDontSee(self::INVALID_LINK_MESSAGE);
        $response->assertDontSee('お知らせ');
    }

    /**
     * リンクの ?email= が文字列でない（配列）ときも500にならない
     */
    #[Test]
    public function 再設定ページのメールアドレスが文字列でなくても表示される(): void
    {
        $this->get(route('admin.password.reset', ['token' => 'sample', 'email' => ['a']]))
            ->assertOk()
            ->assertSee('name="email" value=""', false);
    }

    // ---- 再設定 ----

    /**
     * 正しく入力すると、パスワードが変わり、管理者用ログインページへ移動して案内がグレーの枠で出る
     */
    #[Test]
    public function 新しいパスワードを設定してログインページへ移動する(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createToken($admin);

        $response = $this->postReset($token);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', self::DONE_MESSAGE);
        $this->assertTrue(Hash::check('newpass123', $admin->fresh()->password));
        $this->assertFalse(Hash::check('oldpass123', $admin->fresh()->password));
        $this->assertGuest('admin');
        $this->assertDatabaseCount('admin_password_reset_tokens', 0);

        $html = $this->followRedirects($response)->assertSee(self::DONE_MESSAGE)->getContent();
        $this->assertStringContainsString('class="rounded-[10px] border border-line-strong bg-surface px-4 py-3 text-base text-ink" role="status">'.self::DONE_MESSAGE, $html);
    }

    /**
     * 再設定後は新しいパスワードでログインでき、古いパスワードではログインできない
     */
    #[Test]
    public function 再設定後は新しいパスワードでだけログインできる(): void
    {
        $this->postReset($this->createToken($this->createAdmin()));

        $this->post(route('admin.login.store'), ['email' => 'admin-reset@example.com', 'password' => 'oldpass123'])->assertSessionHasErrors('email');
        $this->assertGuest('admin');

        $this->post(route('admin.login.store'), ['email' => 'admin-reset@example.com', 'password' => 'newpass123'])->assertRedirect(route('admin.home'));
        $this->assertAuthenticated('admin');
    }

    /**
     * パスワードのエラー（未入力・8文字未満・不一致）。パスワードは変わらず、トークンも使われない
     */
    #[Test]
    public function パスワードが不正ならエラーになりパスワードは変わらない(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createToken($admin);
        $page = route('admin.password.reset', ['token' => $token, 'email' => 'admin-reset@example.com']);

        $this->postReset($token, ['password' => '', 'password_confirmation' => ''])
            ->assertRedirect($page)
            ->assertSessionHasErrors(['password' => 'パスワードを入力してください']);

        $this->postReset($token, ['password' => 'short1', 'password_confirmation' => 'short1'])
            ->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);

        $this->postReset($token, ['password' => 'newpass123', 'password_confirmation' => 'newpass999'])
            ->assertSessionHasErrors(['password' => 'パスワードと確認用パスワードが一致しません']);

        $this->postReset($token, ['password' => 'newpass123', 'password_confirmation' => ['newpass123']])
            ->assertSessionHasErrors(['password' => 'パスワードと確認用パスワードが一致しません']);

        $this->postReset($token, ['password' => ['newpass123'], 'password_confirmation' => ['newpass123']])
            ->assertSessionHasErrors(['password' => 'パスワードは文字で入力してください']);

        $this->assertTrue(Hash::check('oldpass123', $admin->fresh()->password));
        $this->assertDatabaseCount('admin_password_reset_tokens', 1);
    }

    /**
     * 8文字ちょうどのパスワードは設定できる（境界）
     */
    #[Test]
    public function 八文字のパスワードは設定できる(): void
    {
        $admin = $this->createAdmin();

        $this->postReset($this->createToken($admin), ['password' => 'abcd1234', 'password_confirmation' => 'abcd1234'])
            ->assertRedirect(route('admin.login'));

        $this->assertTrue(Hash::check('abcd1234', $admin->fresh()->password));
    }

    /**
     * 再設定に成功したときだけ PasswordReset イベントが発火される（会員の Fortify に揃える）
     */
    #[Test]
    public function 再設定に成功したときだけパスワード再設定イベントが発火される(): void
    {
        Event::fake([PasswordReset::class]);
        $admin = $this->createAdmin();
        $token = $this->createToken($admin);

        $this->postReset($token, ['password' => 'short1', 'password_confirmation' => 'short1']);
        $this->postReset('invalidtoken');
        Event::assertNotDispatched(PasswordReset::class);

        $this->postReset($token);
        Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user->is($admin));
    }

    /**
     * エラーで戻ったとき、パスワード欄は空で、エラーが表示される
     */
    #[Test]
    public function エラーで戻ったときパスワード欄は空でエラーが表示される(): void
    {
        $token = $this->createToken($this->createAdmin());

        $response = $this->followingRedirects()->postReset($token, ['password' => 'short1', 'password_confirmation' => 'short1']);

        $response->assertSee('パスワードは8文字以上で入力してください');
        $response->assertDontSee('value="short1"', false);
        $response->assertDontSee(self::INVALID_LINK_MESSAGE);
        $response->assertSee('name="token" value="'.$token.'"', false);
    }

    /**
     * 一度使ったリンクは使えない
     */
    #[Test]
    public function 使用済みのリンクでは再設定できない(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createToken($admin);
        $this->postReset($token);

        $response = $this->postReset($token, ['password' => 'another123', 'password_confirmation' => 'another123']);

        $response->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);
        $this->assertTrue(Hash::check('newpass123', $admin->fresh()->password));
    }

    /**
     * 有効期限（60分）が切れたリンクは使えない
     */
    #[Test]
    public function 期限切れのリンクでは再設定できない(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createToken($admin);

        $this->travel(61)->minutes();

        $this->postReset($token)->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);
        $this->assertTrue(Hash::check('oldpass123', $admin->fresh()->password));
    }

    /**
     * 有効期限の境界（59分後はまだ有効）
     */
    #[Test]
    public function 五十九分後のリンクはまだ使える(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createToken($admin);

        $this->travel(59)->minutes();

        $this->postReset($token)->assertRedirect(route('admin.login'));
        $this->assertTrue(Hash::check('newpass123', $admin->fresh()->password));
    }

    /**
     * 改ざんされたトークン・別の管理者のメールアドレス・存在しないメールアドレスでは再設定できない
     */
    #[Test]
    public function 改ざんされたリンクでは再設定できない(): void
    {
        $admin = $this->createAdmin();
        $other = Admin::factory()->create(['email' => 'other@example.com', 'password' => Hash::make('otherpass1')]);
        $token = $this->createToken($admin);

        $this->postReset('invalidtoken')->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);
        $this->postReset($token.'x')->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);
        $this->postReset($token, ['email' => 'other@example.com'])->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);
        $this->postReset($token, ['email' => 'nobody@example.com'])->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);

        $this->assertTrue(Hash::check('oldpass123', $admin->fresh()->password));
        $this->assertTrue(Hash::check('otherpass1', $other->fresh()->password));
    }

    /**
     * メールアドレスやトークンが欠けた・文字列でない送信でも500にならない
     */
    #[Test]
    public function トークンやメールアドレスが不正な送信でも500にならない(): void
    {
        $token = $this->createToken($this->createAdmin());

        $this->postReset($token, ['email' => ''])->assertSessionHasErrors('email');
        $this->postReset($token, ['email' => ['admin-reset@example.com']])->assertSessionHasErrors('email');
        $this->postReset($token, ['token' => ''])->assertSessionHasErrors('token');
        $this->postReset($token, ['token' => ['x']])->assertSessionHasErrors('token');
    }

    /**
     * 無効なリンクで戻ったとき、案内と再設定メールの送信ページへのリンクが出る
     */
    #[Test]
    public function 無効なリンクで戻ったとき案内と送信ページへのリンクが表示される(): void
    {
        $response = $this->followingRedirects()->postReset('invalidtoken');

        $response->assertSee(self::INVALID_LINK_MESSAGE);
        $response->assertSee('href="'.route('admin.password.request').'"', false);
    }

    // ---- 会員の再設定との分離 ----

    /**
     * 会員の再設定のトークンでは、管理者の再設定ができない（会員のパスワードも変わらない）
     */
    #[Test]
    public function 会員の再設定のリンクでは管理者の再設定ができない(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com', 'password' => Hash::make('memberpass1')]);
        $userToken = Password::broker()->createToken($user);

        $this->postReset($userToken, ['email' => 'member@example.com'])->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);

        // 管理者と同じメールアドレスの会員がいても、会員のトークンでは管理者のパスワードは変わらない
        $admin = $this->createAdmin();
        $sameEmailUser = User::factory()->create(['email' => 'admin-reset@example.com']);
        $this->postReset(Password::broker()->createToken($sameEmailUser))->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);

        $this->assertTrue(Hash::check('memberpass1', $user->fresh()->password));
        $this->assertTrue(Hash::check('oldpass123', $admin->fresh()->password));
    }

    /**
     * 管理者の再設定のトークンでは、会員の再設定ができない
     */
    #[Test]
    public function 管理者の再設定のリンクでは会員の再設定ができない(): void
    {
        $user = User::factory()->create(['email' => 'admin-reset@example.com', 'password' => Hash::make('memberpass1')]);
        $admin = $this->createAdmin();
        $adminToken = $this->createToken($admin);

        $this->from(route('password.reset', ['token' => $adminToken]))
            ->post(route('password.update'), [
                'token' => $adminToken,
                'email' => 'admin-reset@example.com',
                'password' => 'newpass123',
                'password_confirmation' => 'newpass123',
            ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('memberpass1', $user->fresh()->password));
        $this->assertTrue(Hash::check('oldpass123', $admin->fresh()->password));
    }

    /**
     * 公開側の /forgot-password に管理者のメールアドレスを入れても、メールは送られない
     */
    #[Test]
    public function 会員用の送信ページに管理者のメールアドレスを入れてもメールは送られない(): void
    {
        Mail::fake();
        $this->createAdmin();

        $this->from(route('password.request'))->post(route('password.email'), ['email' => 'admin-reset@example.com'])
            ->assertSessionHas('status', self::SENT_MESSAGE);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertDatabaseCount('admin_password_reset_tokens', 0);
    }

    /**
     * 管理者の再設定メールのトークンは、管理者用のテーブルにだけ保存される
     */
    #[Test]
    public function 管理者の再設定メールは会員のトークンのテーブルに書き込まない(): void
    {
        Mail::fake();
        $this->createAdmin();

        $this->post(route('admin.password.email'), ['email' => 'admin-reset@example.com']);

        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertDatabaseHas('admin_password_reset_tokens', ['email' => 'admin-reset@example.com']);
    }

    /**
     * 公開側の再設定のメールとページは、今までどおり会員用のまま
     */
    #[Test]
    public function 公開側の再設定のメールとページは会員用のままである(): void
    {
        $user = User::factory()->create(['last_name' => '会員', 'first_name' => '太郎', 'email' => 'member@example.com']);
        $mail = new PasswordResetMail($user, 'sample-token');

        $mail->assertHasSubject('【しおや農園】パスワード再設定のご案内');
        $this->assertStringContainsString('/reset-password/sample-token', $mail->render());
        $this->assertStringNotContainsString('/admin/reset-password', $mail->render());
        $this->get(route('password.request'))->assertOk()->assertViewIs('auth.forgot-password')->assertSee('text-accent lg:text-[32px]', false);
    }

    // ---- ログイン中 ----

    /**
     * 管理者としてログイン中は、送信ページ・再設定ページ・各POSTで /admin へ移動する
     */
    #[Test]
    public function ログイン中は管理画面のトップへ移動する(): void
    {
        $admin = $this->createAdmin();
        Mail::fake();

        $this->actingAs($admin, 'admin')->get(route('admin.password.request'))->assertRedirect(route('admin.home'));
        $this->actingAs($admin, 'admin')->get(route('admin.password.reset', ['token' => 'invalidtoken']))->assertRedirect(route('admin.home'));
        $this->actingAs($admin, 'admin')->post(route('admin.password.email'), ['email' => 'admin-reset@example.com'])->assertRedirect(route('admin.home'));
        $this->actingAs($admin, 'admin')->post(route('admin.password.update'), [
            'token' => $this->createToken($admin),
            'email' => 'admin-reset@example.com',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertRedirect(route('admin.home'));

        Mail::assertNothingSent();
        $this->assertTrue(Hash::check('oldpass123', $admin->fresh()->password));
    }

    /**
     * 会員としてログイン中でも、管理者の再設定ページは開ける（会員の認証とは別）
     */
    #[Test]
    public function 会員としてログイン中でも管理者の送信ページは開ける(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.password.request'))->assertOk();
        Auth::shouldUse('web');
    }

    // ---- 回数制限 ----

    /**
     * 再設定メールの送信は、同じIPアドレスから1分に5回まで。6回目は日本語のメッセージで止め、メールは送らない
     */
    #[Test]
    public function 再設定メールの送信は一分に五回までで六回目は制限される(): void
    {
        Mail::fake();
        $this->createAdmin();

        for ($i = 1; $i <= 4; $i++) {
            $this->from(route('admin.password.request'))->post(route('admin.password.email'), ['email' => 'nobody'.$i.'@example.com'])
                ->assertSessionHasNoErrors();
        }
        $this->post(route('admin.password.email'), ['email' => 'admin-reset@example.com'])->assertRedirect()->assertSessionHasNoErrors();
        Mail::assertSentCount(1);

        $response = $this->from(route('admin.password.request'))->post(route('admin.password.email'), ['email' => 'nobody6@example.com']);

        $response->assertRedirect(route('admin.password.request'));
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('操作の回数が上限に達しました。', session('errors')->first('email'));
    }

    /**
     * 新しいパスワードの設定も1分に5回まで。6回目はパスワード欄の近くに日本語のメッセージを出し、パスワードは変えない
     */
    #[Test]
    public function 新しいパスワードの設定は一分に五回までで六回目は制限される(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createToken($admin);

        for ($i = 1; $i <= 5; $i++) {
            $this->postReset('invalidtoken');
        }
        $response = $this->postReset($token);

        $response->assertSessionHasErrors('password');
        $response->assertSessionDoesntHaveErrors('email');
        $this->assertStringContainsString('操作の回数が上限に達しました。', session('errors')->first('password'));
        $this->assertTrue(Hash::check('oldpass123', $admin->fresh()->password));

        $this->followRedirects($response)
            ->assertSee('操作の回数が上限に達しました。')
            ->assertDontSee(self::INVALID_LINK_MESSAGE);
    }

    /**
     * 回数制限は会員の再設定と別々に数える
     */
    #[Test]
    public function 回数制限は会員の再設定とは別に数える(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->post(route('password.email'), ['email' => 'nobody'.$i.'@example.com']);
        }

        $this->post(route('admin.password.email'), ['email' => 'nobody@example.com'])->assertSessionHasNoErrors();
    }

    /**
     * ページの表示は回数制限されない
     */
    #[Test]
    public function ページの表示は回数制限されない(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->get(route('admin.password.request'))->assertOk();
            $this->get(route('admin.password.reset', ['token' => 'invalidtoken']))->assertOk();
        }
    }

    // ---- 419 ----

    /**
     * 送信ページでトークンが古いとき、送信ページへ戻り、案内とメールアドレスが残る（メールは送らない）
     */
    #[Test]
    public function 送信でトークンが古いと案内つきで送信ページへ戻る(): void
    {
        Mail::fake();
        $this->createAdmin();
        $this->enableCsrfCheck();

        $response = $this->withSession(['_token' => 'valid-token'])
            ->post(route('admin.password.email'), ['_token' => 'stale-token', 'email' => 'admin-reset@example.com']);

        $response->assertRedirect(route('admin.password.request'));
        $response->assertSessionHas('status', self::EXPIRED_MESSAGE);
        $response->assertSessionHasInput('email', 'admin-reset@example.com');
        Mail::assertNothingSent();
    }

    /**
     * 再設定ページでトークンが古いとき、再設定ページ（リンク）へ戻り、案内が出て、パスワードは残らず変わらない
     */
    #[Test]
    public function 再設定でトークンが古いと案内つきで再設定ページへ戻る(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createToken($admin);
        $this->enableCsrfCheck();

        $response = $this->withSession(['_token' => 'valid-token'])
            ->post(route('admin.password.update'), [
                '_token' => 'stale-token',
                'token' => $token,
                'email' => 'admin-reset@example.com',
                'password' => 'newpass123',
                'password_confirmation' => 'newpass123',
            ]);

        $response->assertRedirect(route('admin.password.reset', ['token' => $token, 'email' => 'admin-reset@example.com']));
        $response->assertSessionHas('status', self::EXPIRED_MESSAGE);
        $response->assertSessionMissing('_old_input.password');
        $this->assertTrue(Hash::check('oldpass123', $admin->fresh()->password));
        $this->assertDatabaseCount('admin_password_reset_tokens', 1);

        $this->followRedirects($response)->assertSee(self::EXPIRED_MESSAGE);
    }

    /**
     * 再設定のPOSTにトークンがなくてCSRFトークンが古いときは、送信ページへ戻す
     */
    #[Test]
    public function 再設定でトークンがなく_csr_fトークンが古いと送信ページへ戻る(): void
    {
        $this->enableCsrfCheck();

        $this->withSession(['_token' => 'valid-token'])
            ->post(route('admin.password.update'), ['_token' => 'stale-token'])
            ->assertRedirect(route('admin.password.request'));
    }

    /**
     * JSONを期待するリクエストは、従来どおり419になる
     */
    #[Test]
    public function jso_nを期待するリクエストでトークンが古いと419のままになる(): void
    {
        $this->enableCsrfCheck();

        $this->withSession(['_token' => 'valid-token'])
            ->postJson(route('admin.password.email'), ['_token' => 'stale-token'])
            ->assertStatus(419);
    }

    // ---- ほか ----

    /**
     * 再設定に成功すると、ほかの端末でログインしていた同じ管理者のセッションはログアウト状態になる（AuthenticateSession）
     */
    #[Test]
    public function 再設定するとほかの端末のログインは切れる(): void
    {
        $admin = $this->createAdmin();
        $oldHash = $admin->getAuthPassword();

        // ほかの端末(別のセッション)でログイン中。再設定前は、そのセッションで管理画面を開ける
        $this->actingAs($admin, 'admin')->withSession(['password_hash_admin' => $oldHash])->get(route('admin.home'))->assertOk();

        // 手元の端末(ログインしていないセッション)で再設定する
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->postReset($this->createToken($admin))->assertRedirect(route('admin.login'));

        // 再設定後は、ほかの端末のセッション(古いパスワードのハッシュを持つ)でアクセスするとログアウトされる
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin->fresh(), 'admin')->withSession(['password_hash_admin' => $oldHash])->get(route('admin.home'))
            ->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    /**
     * トークンは保存時にハッシュ化されている（メールのトークンそのものは保存されない）
     */
    #[Test]
    public function トークンはハッシュ化して保存される(): void
    {
        $token = $this->createToken($this->createAdmin());

        $stored = (string) DB::table('admin_password_reset_tokens')->value('token');

        $this->assertNotSame($token, $stored);
        $this->assertTrue(Hash::check($token, $stored));
    }
}
