<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Markdown;
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
 * パスワード再設定（再設定メールの送信・メールの内容・新しいパスワードの設定）のテスト（Fortifyのルートを使う）
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const string SENT_MESSAGE = 'ご登録のメールアドレス宛てに、パスワード再設定用のメールをお送りしました。';

    private const string INVALID_LINK_MESSAGE = 'このリンクは無効か、有効期限が切れています。もう一度再設定メールを送信してください。';

    private const string DONE_MESSAGE = 'パスワードを再設定しました。新しいパスワードでログインしてください。';

    /**
     * 差出人は config('mail.from') から出るため、.env に左右されないよう、既定の値にそろえる
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.from.address' => 'shioyafarm@example.com', 'mail.from.name' => 'しおや農園']);
    }

    /**
     * 再設定のテスト用の会員を作る
     */
    private function createUser(): User
    {
        return User::factory()->create([
            'last_name' => '再設定',
            'first_name' => '太郎',
            'email' => 'reset@example.com',
            'password' => Hash::make('oldpass123'),
        ]);
    }

    /**
     * 有効な再設定トークンを作る（メールに載るものと同じ）
     */
    private function createToken(User $user): string
    {
        return Password::broker()->createToken($user);
    }

    /**
     * 新しいパスワードの設定をPOSTする（再設定ページから送ったことにする）
     *
     * @param  array<string, mixed>  $overrides
     */
    private function postReset(string $token, array $overrides = []): TestResponse
    {
        return $this->from(route('password.reset', ['token' => $token, 'email' => 'reset@example.com']))
            ->post(route('password.update'), $overrides + [
                'token' => $token,
                'email' => 'reset@example.com',
                'password' => 'newpass123',
                'password_confirmation' => 'newpass123',
            ]);
    }

    /**
     * メールの本文のテキスト版
     */
    private function renderText(PasswordResetMail $mail): string
    {
        $content = $mail->content();

        return app(Markdown::class)->renderText($content->markdown, $content->with)->toHtml();
    }

    /**
     * 有効期限と再送信の待ち時間の設定
     */
    #[Test]
    public function 有効期限は60分で再送信は60秒待つ設定になっている(): void
    {
        $this->assertSame(60, config('auth.passwords.users.expire'));
        $this->assertSame(60, config('auth.passwords.users.throttle'));
    }

    /**
     * ログインページに再設定メールの送信ページへのリンクがある
     */
    #[Test]
    public function ログインページにパスワードをお忘れの方のリンクがある(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('パスワードをお忘れの方');
        $response->assertSee('href="'.route('password.request').'"', false);
    }

    /**
     * 送信ページは認証ページ用のレイアウトで表示される
     */
    #[Test]
    public function 送信ページが表示される(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
        $response->assertViewIs('auth.forgot-password');
        $response->assertSee('<title>パスワード再設定 | Shioya Farm</title>', false);
        $response->assertSee('パスワード再設定');
        $response->assertSee('name="email"', false);
        $response->assertSee('再設定メールを送信');
        $response->assertSee('href="'.route('login').'"', false);
        $response->assertSee('&copy;Shioya farm', false);
        $response->assertDontSee('お知らせ');
        $response->assertDontSee(self::SENT_MESSAGE);
    }

    /**
     * 登録済みの会員には再設定メールが1通届き、送信ページに案内が出る
     */
    #[Test]
    public function 登録済みのメールアドレスには再設定メールを送って案内を表示する(): void
    {
        Mail::fake();
        $user = $this->createUser();

        $response = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'reset@example.com']);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasNoErrors();
        Mail::assertSentCount(1);
        Mail::assertSent(PasswordResetMail::class, fn (PasswordResetMail $mail): bool => $mail->hasTo('reset@example.com') && $mail->user->is($user));
        $this->followRedirects($response)->assertSee(self::SENT_MESSAGE);
    }

    /**
     * メールの送信に失敗したときは、保存したトークンを残さない（残すと60秒間は再送信できず、メールも届かない）
     */
    #[Test]
    public function メールの送信に失敗したらトークンを削除して再送信できる(): void
    {
        $this->createUser();
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
            $this->post(route('password.email'), ['email' => 'reset@example.com']);
            $this->fail('送信の失敗が例外として投げ直されませんでした');
        } catch (RuntimeException $e) {
            $this->assertSame('送信に失敗', $e->getMessage());
        }

        $this->assertDatabaseCount('password_reset_tokens', 0);

        // 待たずに、もう一度送信できる
        $this->post(route('password.email'), ['email' => 'reset@example.com']);
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->assertTrue($failed);
    }

    /**
     * メールアドレスの大文字小文字は区別しない
     */
    #[Test]
    public function メールアドレスは大文字で入力しても再設定メールが届く(): void
    {
        Mail::fake();
        $this->createUser();

        $this->post(route('password.email'), ['email' => 'RESET@Example.com']);

        Mail::assertSentCount(1);
    }

    /**
     * 未登録のメールアドレスでも同じ案内を表示し、メールは送らない
     */
    #[Test]
    public function 未登録のメールアドレスでも同じ案内を表示してメールは送らない(): void
    {
        Mail::fake();

        $response = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'not-registered@example.com']);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', self::SENT_MESSAGE);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    /**
     * 退会済み（論理削除）の会員でも同じ案内を表示し、メールは送らない
     */
    #[Test]
    public function 退会済みの会員でも同じ案内を表示してメールは送らない(): void
    {
        Mail::fake();
        $user = $this->createUser();
        $user->delete();

        $response = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'reset@example.com']);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', self::SENT_MESSAGE);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    /**
     * 1分以内の再送信でも同じ案内を表示し、2通目は送らない
     */
    #[Test]
    public function 一分以内の再送信でも同じ案内を表示して二通目は送らない(): void
    {
        Mail::fake();
        $this->createUser();

        $this->post(route('password.email'), ['email' => 'reset@example.com']);
        $response = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'reset@example.com']);

        $response->assertRedirect(route('password.request'));
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
        $this->createUser();

        $this->post(route('password.email'), ['email' => 'reset@example.com']);
        $this->travel(61)->seconds();
        $this->post(route('password.email'), ['email' => 'reset@example.com']);

        Mail::assertSentCount(2);
    }

    /**
     * メールアドレスが未入力・形式不正・文字列でないときはエラーにして、メールは送らない
     */
    #[Test]
    public function 送信のメールアドレスが不正ならエラーになりメールは送らない(): void
    {
        Mail::fake();
        $this->createUser();

        $this->from(route('password.request'))->post(route('password.email'), ['email' => ''])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);

        $this->from(route('password.request'))->post(route('password.email'), ['email' => 'abc'])
            ->assertSessionHasErrors(['email' => 'メールアドレスは有効なメールアドレス形式で入力してください']);

        $this->from(route('password.request'))->post(route('password.email'), ['email' => ['reset@example.com']])
            ->assertSessionHasErrors(['email' => 'メールアドレスは文字で入力してください']);

        Mail::assertNothingSent();
    }

    /**
     * 入力エラーは送信ページの入力欄の近くに表示される
     */
    #[Test]
    public function 送信ページに戻ったときエラーメッセージが表示される(): void
    {
        $response = $this->followingRedirects()->from(route('password.request'))->post(route('password.email'), ['email' => 'abc']);

        $response->assertSee('メールアドレスは有効なメールアドレス形式で入力してください');
        $response->assertDontSee(self::SENT_MESSAGE);
    }

    /**
     * 再設定メールの差出人・件名・宛先
     */
    #[Test]
    public function 再設定メールの差出人と件名と宛先(): void
    {
        $mail = new PasswordResetMail($this->createUser(), 'sample-token');

        $mail->assertTo('reset@example.com');
        $mail->assertFrom('shioyafarm@example.com', 'しおや農園');
        $mail->assertHasSubject('【しおや農園】パスワード再設定のご案内');
    }

    /**
     * 再設定メールの本文は日本語で、英語の定型文を含まない（HTML版とテキスト版）
     */
    #[Test]
    public function 再設定メールの本文は日本語で英語の定型文を含まない(): void
    {
        $mail = new PasswordResetMail($this->createUser(), 'sample-token');
        $url = route('password.reset', ['token' => 'sample-token', 'email' => 'reset@example.com']);

        $html = $mail->render();

        $this->assertStringContainsString('再設定 太郎 様', html_entity_decode(strip_tags($html)));
        $this->assertStringContainsString('パスワードを再設定する', $html);
        $this->assertStringContainsString('href="'.str_replace('&', '&amp;', $url).'"', $html);
        $this->assertStringContainsString('有効期限は、お送りしてから60分', $html);
        $this->assertStringContainsString('破棄してください', $html);
        foreach (['Hello', 'Regards', 'Reset Password', 'having trouble', 'Laravel'] as $english) {
            $this->assertStringNotContainsString($english, $html);
        }

        $text = html_entity_decode($this->renderText($mail));
        $this->assertStringContainsString('再設定 太郎 様', $text);
        $this->assertStringContainsString('パスワードを再設定する', $text);
        $this->assertStringContainsString($url, $text);
        $this->assertStringContainsString('有効期限は、お送りしてから60分', $text);
        foreach (['Hello', 'Regards', 'Reset Password', 'having trouble', 'Laravel'] as $english) {
            $this->assertStringNotContainsString($english, $text);
        }
    }

    /**
     * メールの有効期限の表示は設定値（分）から出る
     */
    #[Test]
    public function メールの有効期限の表示は設定値から出る(): void
    {
        config(['auth.passwords.users.expire' => 30]);

        $html = (new PasswordResetMail($this->createUser(), 'sample-token'))->render();

        $this->assertStringContainsString('有効期限は、お送りしてから30分', $html);
    }

    /**
     * 会員の氏名に含まれる記号は、メールでHTMLとして解釈されない
     */
    #[Test]
    public function 会員の氏名の記号は無害化される(): void
    {
        $user = $this->createUser();
        $user->forceFill(['last_name' => '<b>太</b>'])->save();

        $html = (new PasswordResetMail($user, 'sample-token'))->render();

        $this->assertStringNotContainsString('<b>太</b>', $html);
    }

    /**
     * 送信されたメールのリンクを開くと、新しいパスワードの設定ページが表示される
     */
    #[Test]
    public function メールのリンクで再設定ページを開ける(): void
    {
        Mail::fake();
        $this->createUser();
        $this->post(route('password.email'), ['email' => 'reset@example.com']);

        $url = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$url): bool {
            preg_match('/href="([^"]*reset-password[^"]*)"/', html_entity_decode($mail->render()), $matches);
            $url = $matches[1] ?? null;

            return true;
        });

        $this->assertNotNull($url);
        $this->get($url)->assertOk()->assertViewIs('auth.reset-password')->assertSee('name="email" value="reset@example.com"', false);
    }

    /**
     * 新しいパスワードの設定ページが表示される
     */
    #[Test]
    public function 再設定ページが表示される(): void
    {
        $token = $this->createToken($this->createUser());

        $response = $this->get(route('password.reset', ['token' => $token, 'email' => 'reset@example.com']));

        $response->assertOk();
        $response->assertViewIs('auth.reset-password');
        $response->assertSee('<title>新しいパスワードの設定 | Shioya Farm</title>', false);
        $response->assertSee('新しいパスワードの設定');
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
        $response->assertSee('type="password"', false);
        $response->assertSee('name="token" value="'.$token.'"', false);
        $response->assertSee('name="email" value="reset@example.com"', false);
        $response->assertSee('パスワードを再設定する');
        $response->assertDontSee(self::INVALID_LINK_MESSAGE);
        $response->assertDontSee('お知らせ');
    }

    /**
     * 正しく入力すると、パスワードが変わり、ログインページへ移動して案内が出る
     */
    #[Test]
    public function 新しいパスワードを設定してログインページへ移動する(): void
    {
        $user = $this->createUser();
        $token = $this->createToken($user);

        $response = $this->postReset($token);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', self::DONE_MESSAGE);
        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
        $this->assertFalse(Hash::check('oldpass123', $user->fresh()->password));
        $this->assertGuest();
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->followRedirects($response)->assertSee(self::DONE_MESSAGE);
    }

    /**
     * 再設定後は新しいパスワードでログインでき、古いパスワードではログインできない
     */
    #[Test]
    public function 再設定後は新しいパスワードでだけログインできる(): void
    {
        $this->postReset($this->createToken($this->createUser()));

        $this->post(route('login.store'), ['email' => 'reset@example.com', 'password' => 'oldpass123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => 'reset@example.com', 'password' => 'newpass123']);
        $this->assertAuthenticated();
    }

    /**
     * パスワードのエラー（未入力・8文字未満・不一致）。パスワードは変わらず、トークンも使われない
     */
    #[Test]
    public function パスワードが不正ならエラーになりパスワードは変わらない(): void
    {
        $user = $this->createUser();
        $token = $this->createToken($user);
        $page = route('password.reset', ['token' => $token, 'email' => 'reset@example.com']);

        $this->postReset($token, ['password' => '', 'password_confirmation' => ''])
            ->assertRedirect($page)
            ->assertSessionHasErrors(['password' => 'パスワードを入力してください']);

        $this->postReset($token, ['password' => 'short1', 'password_confirmation' => 'short1'])
            ->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);

        $this->postReset($token, ['password' => 'newpass123', 'password_confirmation' => 'newpass999'])
            ->assertSessionHasErrors(['password' => 'パスワードと確認用パスワードが一致しません']);

        $this->postReset($token, ['password' => ['newpass123'], 'password_confirmation' => ['newpass123']])
            ->assertSessionHasErrors(['password' => 'パスワードは文字で入力してください']);

        $this->assertTrue(Hash::check('oldpass123', $user->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    /**
     * エラーで戻ったとき、パスワード欄は空で、エラーが表示される
     */
    #[Test]
    public function エラーで戻ったときパスワード欄は空でエラーが表示される(): void
    {
        $token = $this->createToken($this->createUser());

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
        $user = $this->createUser();
        $token = $this->createToken($user);
        $this->postReset($token);

        $response = $this->postReset($token, ['password' => 'another123', 'password_confirmation' => 'another123']);

        $response->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);
        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    /**
     * 有効期限（60分）が切れたリンクは使えない
     */
    #[Test]
    public function 期限切れのリンクでは再設定できない(): void
    {
        $user = $this->createUser();
        $token = $this->createToken($user);

        $this->travel(61)->minutes();

        $this->postReset($token)->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);
        $this->assertTrue(Hash::check('oldpass123', $user->fresh()->password));
    }

    /**
     * 有効期限の境界（59分後はまだ有効）
     */
    #[Test]
    public function 五十九分後のリンクはまだ使える(): void
    {
        $user = $this->createUser();
        $token = $this->createToken($user);

        $this->travel(59)->minutes();

        $this->postReset($token)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    /**
     * 改ざんされたトークン・別の会員のメールアドレス・存在しない会員のメールアドレスでは再設定できない
     */
    #[Test]
    public function 改ざんされたリンクでは再設定できない(): void
    {
        $user = $this->createUser();
        $other = User::factory()->create(['email' => 'other@example.com', 'password' => Hash::make('otherpass1')]);
        $token = $this->createToken($user);

        $this->postReset('invalidtoken')->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);
        $this->postReset($token.'x')->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);
        $this->postReset($token, ['email' => 'other@example.com'])->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);
        $this->postReset($token, ['email' => 'nobody@example.com'])->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);

        $this->assertTrue(Hash::check('oldpass123', $user->fresh()->password));
        $this->assertTrue(Hash::check('otherpass1', $other->fresh()->password));
    }

    /**
     * メールアドレスやトークンが欠けた・文字列でない送信でも500にならない
     */
    #[Test]
    public function トークンやメールアドレスが不正な送信でも500にならない(): void
    {
        $token = $this->createToken($this->createUser());

        $this->postReset($token, ['email' => ''])->assertSessionHasErrors('email');
        $this->postReset($token, ['email' => ['reset@example.com']])->assertSessionHasErrors('email');
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
        $response->assertSee('href="'.route('password.request').'"', false);
    }

    /**
     * 退会済みの会員のトークンでは再設定できない
     */
    #[Test]
    public function 退会済みの会員は再設定できない(): void
    {
        $user = $this->createUser();
        $token = $this->createToken($user);
        $user->delete();

        $this->postReset($token)->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);
        $this->assertTrue(Hash::check('oldpass123', User::withTrashed()->findOrFail($user->id)->password));
    }

    /**
     * ログイン中は送信ページ・再設定ページ・各POSTでトップページへ移動する
     */
    #[Test]
    public function ログイン中はトップページへ移動する(): void
    {
        $user = $this->createUser();
        Mail::fake();

        $this->actingAs($user)->get(route('password.request'))->assertRedirect(route('top.index'));
        $this->actingAs($user)->get(route('password.reset', ['token' => 'invalidtoken']))->assertRedirect(route('top.index'));
        $this->actingAs($user)->post(route('password.email'), ['email' => 'reset@example.com'])->assertRedirect(route('top.index'));
        $this->actingAs($user)->post(route('password.update'), [
            'token' => $this->createToken($user),
            'email' => 'reset@example.com',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertRedirect(route('top.index'));

        Mail::assertNothingSent();
        $this->assertTrue(Hash::check('oldpass123', $user->fresh()->password));
    }

    /**
     * 再設定メールの送信は、同じIPアドレスから1分に5回まで。6回目は日本語のメッセージで止め、メールは送らない
     */
    #[Test]
    public function 再設定メールの送信は一分に五回までで六回目は制限される(): void
    {
        Mail::fake();
        $this->createUser();

        for ($i = 1; $i <= 4; $i++) {
            $this->from(route('password.request'))->post(route('password.email'), ['email' => 'nobody'.$i.'@example.com'])
                ->assertSessionHasNoErrors();
        }
        // 5回目まではそのまま処理される
        $this->post(route('password.email'), ['email' => 'reset@example.com'])->assertRedirect()->assertSessionHasNoErrors();
        Mail::assertSentCount(1);

        $response = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'nobody6@example.com']);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('操作の回数が上限に達しました。', session('errors')->first('email'));
        $this->followRedirects($response)->assertSee('操作の回数が上限に達しました。');
    }

    /**
     * 回数制限で止めるときも、再設定メールは送らない(上限を超えた分は処理されない)
     */
    #[Test]
    public function 制限されたリクエストではメールを送らない(): void
    {
        Mail::fake();
        $user = $this->createUser();

        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('password.email'), ['email' => 'nobody'.$i.'@example.com']);
        }
        $this->post(route('password.email'), ['email' => 'reset@example.com'])->assertSessionHasErrors('email');

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    /**
     * 回数制限はIPアドレスごと。別のIPアドレスからは制限されない
     */
    #[Test]
    public function 回数制限は別の_i_pアドレスには影響しない(): void
    {
        Mail::fake();
        $this->createUser();

        for ($i = 1; $i <= 6; $i++) {
            $this->post(route('password.email'), ['email' => 'nobody'.$i.'@example.com']);
        }
        $this->post(route('password.email'), ['email' => 'reset@example.com'])->assertSessionHasErrors('email');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post(route('password.email'), ['email' => 'reset@example.com'])
            ->assertSessionHasNoErrors();
        Mail::assertSentCount(1);
    }

    /**
     * 1分たつと制限が解ける
     */
    #[Test]
    public function 一分たつと回数制限が解ける(): void
    {
        Mail::fake();
        $this->createUser();

        for ($i = 1; $i <= 6; $i++) {
            $this->post(route('password.email'), ['email' => 'nobody'.$i.'@example.com']);
        }
        $this->travel(61)->seconds();

        $this->post(route('password.email'), ['email' => 'reset@example.com'])->assertSessionHasNoErrors();
        Mail::assertSentCount(1);
    }

    /**
     * JSONを期待するリクエストは429で返す
     */
    #[Test]
    public function jso_nを期待するリクエストは上限で429になる(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson(route('password.email'), ['email' => 'nobody'.$i.'@example.com']);
        }

        $this->postJson(route('password.email'), ['email' => 'nobody6@example.com'])
            ->assertStatus(429)
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, '操作の回数が上限に達しました。'))
            ->assertHeader('Retry-After');
    }

    /**
     * 新しいパスワードの設定も、1分に5回まで。6回目はパスワード欄の近くに日本語のメッセージを出し、パスワードは変えない
     */
    #[Test]
    public function 新しいパスワードの設定は一分に五回までで六回目は制限される(): void
    {
        $user = $this->createUser();
        $token = $this->createToken($user);

        for ($i = 1; $i <= 5; $i++) {
            $this->postReset('invalidtoken');
        }
        $response = $this->postReset($token);

        $response->assertSessionHasErrors('password');
        $response->assertSessionDoesntHaveErrors('email');
        $this->assertStringContainsString('操作の回数が上限に達しました。', session('errors')->first('password'));
        $this->assertTrue(Hash::check('oldpass123', $user->fresh()->password));

        // 再設定ページには、無効なリンクの案内ではなく制限のメッセージを出す
        $this->followRedirects($response)
            ->assertSee('操作の回数が上限に達しました。')
            ->assertDontSee(self::INVALID_LINK_MESSAGE);
    }

    /**
     * 2つのルートの回数は別々に数える
     */
    #[Test]
    public function 送信と設定の回数制限は別々に数える(): void
    {
        $this->createUser();

        for ($i = 1; $i <= 6; $i++) {
            $this->post(route('password.email'), ['email' => 'nobody'.$i.'@example.com']);
        }

        $this->postReset('invalidtoken')->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);
    }

    /**
     * 回数制限の対象は2つのPOSTだけ。ページの表示は何度開いても制限されない
     */
    #[Test]
    public function ページの表示は回数制限されない(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->get(route('password.request'))->assertOk();
            $this->get(route('password.reset', ['token' => 'invalidtoken']))->assertOk();
        }
    }

    /**
     * 再設定に成功すると、ほかの端末でログインしていたセッションはログアウト状態になる（AuthenticateSession）
     */
    #[Test]
    public function 再設定するとほかの端末のログインは切れる(): void
    {
        $user = $this->createUser();
        $oldHash = $user->getAuthPassword();

        // ほかの端末(別のセッション)でログイン中。再設定前は、そのセッションで会員専用ページを開ける
        $this->actingAs($user)->withSession(['password_hash_web' => $oldHash])->get(route('shop.cart'))->assertOk();

        // 手元の端末(ログインしていないセッション)で再設定する
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->postReset($this->createToken($user))->assertRedirect(route('login'));

        // 再設定後は、ほかの端末のセッション(古いパスワードのハッシュを持つ)でアクセスするとログアウトされる
        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash])->get(route('shop.cart'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * トークンは保存時にハッシュ化されている（メールのトークンそのものは保存されない）
     */
    #[Test]
    public function トークンはハッシュ化して保存される(): void
    {
        $token = $this->createToken($this->createUser());

        $stored = (string) DB::table('password_reset_tokens')->value('token');

        $this->assertNotSame($token, $stored);
        $this->assertTrue(Hash::check($token, $stored));
    }
}
