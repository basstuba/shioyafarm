<?php

namespace Tests\Feature;

use App\Mail\RegistrationMail;
use App\Models\Area;
use App\Models\User;
use App\Services\RegistrationMailService;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * 会員登録完了メールのテスト（POST /register の後に1通送る）
 */
class RegistrationMailTest extends TestCase
{
    use RefreshDatabase;

    /** 回数制限のメッセージ（待ち時間の秒数が入る） */
    private const string THROTTLE_MESSAGE = '/^登録の操作が続いたため、\d+秒後に再度お試しください。$/u';

    protected function setUp(): void
    {
        parent::setUp();

        Area::factory()->create(['prefecture' => '東京都']);
    }

    /**
     * 会員登録を送る（上書きしたい項目だけ $overrides で渡す）
     *
     * @param  array<string, mixed>  $overrides
     */
    private function register(array $overrides = []): TestResponse
    {
        return $this->from(route('register'))->post(route('register.store'), array_merge([
            'last_name' => 'UAT',
            'first_name' => '登録',
            'prefecture' => '東京都',
            'city' => '千代田区千代田1-1',
            'tell' => '09011112222',
            'email' => 'reg-mail@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides));
    }

    /**
     * メールの本文のテキスト版
     */
    private function renderText(RegistrationMail $mail): string
    {
        $content = $mail->content();

        return app(Markdown::class)->renderText($content->markdown, $content->with)->toHtml();
    }

    /**
     * 登録に成功すると、登録したアドレス宛に差出人・件名どおりのメールが1通送られる
     */
    #[Test]
    public function 登録成功で登録完了メールが1通送られる(): void
    {
        Mail::fake();

        $this->register()->assertRedirect(route('shop.items.index'));

        $this->assertAuthenticated();
        Mail::assertSentCount(1);
        Mail::assertSent(RegistrationMail::class, function (RegistrationMail $mail): bool {
            $this->assertTrue($mail->hasTo('reg-mail@example.com'));
            $this->assertTrue($mail->hasFrom((string) config('mail.from.address'), (string) config('mail.from.name')));
            $this->assertTrue($mail->hasSubject('【しおや農園】会員登録ありがとうございます'));

            return true;
        });
    }

    /**
     * 本文は宛名・お礼・ご登録内容・変更や退会の案内・マイページのボタン・案内・署名の順で、HTML版・テキスト版とも同じ内容が出る
     */
    #[Test]
    public function 本文は決められた順に並ぶ(): void
    {
        $user = User::factory()->create(['last_name' => 'UAT', 'first_name' => '登録', 'email' => 'reg-mail@example.com']);
        $mail = new RegistrationMail($user);

        $html = $mail->render();
        $text = $this->renderText($mail);

        $parts = [
            'UAT 登録 様',
            '会員登録が完了しました',
            '■ ご登録内容',
            'お名前：UAT 登録',
            'メールアドレス：reg-mail@example.com',
            'マイページから行えます',
            'マイページを開く',
            'お心当たりがない場合',
            'このメールは送信専用です',
            route('top.inquiry'),
            (string) config('mail.from.address'),
        ];
        foreach ([$html, $text] as $body) {
            $position = -1;
            foreach ($parts as $part) {
                $found = mb_strpos($body, $part);
                $this->assertNotFalse($found, "「{$part}」が本文にない");
                $this->assertGreaterThan($position, $found, "「{$part}」の位置が順序どおりでない");
                $position = $found;
            }
        }
        // ボタンはマイページへ（登録直後はログイン済みなので、ログインページではない）
        $this->assertStringContainsString('href="'.route('shop.mypage.index').'"', $html);
        $this->assertStringNotContainsString(route('login'), $html);
        $this->assertStringNotContainsString(route('login'), $text);
        $this->assertStringContainsString(route('shop.mypage.index'), $text);
        $this->assertStringEndsWith('/mypage', route('shop.mypage.index'));
        $this->assertStringContainsString((string) config('mail.from.name'), $text);
    }

    /**
     * パスワード・住所・電話番号は本文（HTML版・テキスト版）に載らない
     */
    #[Test]
    public function パスワード住所電話番号は本文に載らない(): void
    {
        Mail::fake();
        $this->register()->assertRedirect(route('shop.items.index'));

        Mail::assertSent(RegistrationMail::class, function (RegistrationMail $mail): bool {
            foreach ([$mail->render(), $this->renderText($mail)] as $body) {
                foreach (['password123', '千代田区千代田1-1', '09011112222', '東京都'] as $secret) {
                    $this->assertStringNotContainsString($secret, $body);
                }
            }

            return true;
        });
    }

    /**
     * 姓・名・メールアドレスのHTMLタグはタグとして解釈されず、そのままの文字で出る
     */
    #[Test]
    public function 入力値のhtmlタグは無害化される(): void
    {
        $user = User::factory()->create(['last_name' => '<i>山田</i>', 'first_name' => '<b>太</b>', 'email' => 'tag@example.com']);
        $mail = new RegistrationMail($user);

        $mail->assertSeeInHtml('&lt;b&gt;太&lt;/b&gt;', false);
        $mail->assertSeeInHtml('&lt;i&gt;山田&lt;/i&gt;', false);
        $mail->assertDontSeeInHtml('<b>太', false);
        $mail->assertDontSeeInHtml('<i>山田', false);
        // テキスト版では、タグの文字のまま読める
        $mail->assertSeeInText('<i>山田</i> <b>太</b> 様');
    }

    /**
     * 入力エラーで登録されなかったときは、メールを送らない
     */
    #[Test]
    public function 入力エラーのときはメールを送らない(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        Mail::fake();

        $this->register(['password_confirmation' => 'different'])->assertSessionHasErrors('password');
        $this->register(['email' => 'taken@example.com'])->assertSessionHasErrors('email');
        $this->register(['last_name' => ''])->assertSessionHasErrors('last_name');

        $this->assertGuest();
        Mail::assertNothingSent();
    }

    /**
     * メールの送信に失敗しても、会員は登録されてログインし、トップへ進み、失敗がログに残る
     */
    #[Test]
    public function 送信に失敗しても登録は成立しログに残る(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP に接続できません'));
        Log::spy();

        $this->register()->assertRedirect(route('shop.items.index'))->assertSessionHasNoErrors();

        $user = User::where('email', 'reg-mail@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, '会員登録完了メールの送信に失敗')
                && $context['user_id'] === $user->id
                && $context['message'] === 'SMTP に接続できません')
            ->once();
    }

    /**
     * 送信先の設定が壊れていて実際の送信が例外になっても、登録は成立する
     */
    #[Test]
    public function 送信先の設定が壊れていても登録は成立する(): void
    {
        config(['mail.default' => 'broken', 'mail.mailers.broken' => ['transport' => 'unknown-transport']]);
        Log::spy();

        $this->register()->assertRedirect(route('shop.items.index'));

        $this->assertDatabaseHas('users', ['email' => 'reg-mail@example.com']);
        $this->assertAuthenticated();
        Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, '会員登録完了メールの送信に失敗'))->once();
    }

    /**
     * メールの送信はレスポンスを返した後（リクエストの終了時）に遅らせる。キューは使わない
     */
    #[Test]
    public function 送信はリクエストの終了時まで遅らせる(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        app(RegistrationMailService::class)->send($user);

        Mail::assertNothingSent();
        Mail::assertNothingQueued();

        app(DeferredCallbackCollection::class)->invoke();

        Mail::assertSentCount(1);
        Mail::assertSent(RegistrationMail::class, fn (RegistrationMail $mail): bool => $mail->hasTo($user->email));
        Mail::assertNothingQueued();
    }

    /**
     * 登録された会員が User でなかったときは、メールを送らず、警告をログに残す（登録とログインは続く）
     */
    #[Test]
    public function 登録された会員がユーザーモデルでないときはメールを送らず警告をログに残す(): void
    {
        $this->app->bind(CreatesNewUsers::class, fn () => new class implements CreatesNewUsers
        {
            /**
             * @param  array<string, mixed>  $input
             */
            public function create(array $input): Authenticatable
            {
                return new GenericUser(['id' => 987, 'email' => 'generic@example.com', 'password' => 'hashed']);
            }
        });
        Mail::fake();
        Log::spy();

        $this->register()->assertRedirect(route('shop.items.index'));
        app(DeferredCallbackCollection::class)->invoke();

        $this->assertAuthenticated();
        Mail::assertNothingSent();
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, '会員登録完了メールを送れませんでした')
                && $context['user_class'] === GenericUser::class
                && $context['user_id'] === 987)
            ->once();
    }

    /**
     * 会員登録の送信は同じIPアドレスから1分に5回まで。6回目は登録ページに戻して日本語のメッセージを出し、パスワード以外の入力を残す
     */
    #[Test]
    public function 会員登録は1分に5回までで6回目は制限される(): void
    {
        Mail::fake();

        // 入力エラーも1回に数える
        for ($i = 1; $i <= 5; $i++) {
            $this->register(['last_name' => ''])->assertSessionHasErrors('last_name');
        }

        $response = $this->register(['email' => 'limit@example.com']);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('email');
        $this->assertMatchesRegularExpression(self::THROTTLE_MESSAGE, session('errors')->first('email'));
        $response->assertSessionHasInput('email', 'limit@example.com');
        $response->assertSessionHasInput('city', '千代田区千代田1-1');
        $response->assertSessionMissing('_old_input.password');
        $response->assertSessionMissing('_old_input.password_confirmation');
        $this->assertDatabaseMissing('users', ['email' => 'limit@example.com']);
        $this->assertGuest();
        Mail::assertNothingSent();

        // 戻った登録ページには、メッセージが出る
        $this->followingRedirects()->get(route('register'))->assertSeeText('登録の操作が続いたため、', false)->assertSeeText('秒後に再度お試しください。', false);
    }

    /**
     * 上限を超えても、登録ページの表示（GET）は制限しない。1分たつと制限が解け、登録できる
     */
    #[Test]
    public function 登録ページの表示は制限されず一分たつと制限が解ける(): void
    {
        Mail::fake();
        for ($i = 1; $i <= 6; $i++) {
            $this->register(['last_name' => '']);
        }

        $this->get(route('register'))->assertOk();

        $this->travel(61)->seconds();

        $this->register()->assertRedirect(route('shop.items.index'))->assertSessionHasNoErrors();
        $this->assertAuthenticated();
        Mail::assertSentCount(1);
    }

    /**
     * JSONを期待するリクエストは上限で429になる
     */
    #[Test]
    public function jsonを期待する会員登録は上限で429になる(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson(route('register.store'), []);
        }

        $this->postJson(route('register.store'), [])
            ->assertStatus(429)
            ->assertJsonPath('message', fn (string $message): bool => preg_match(self::THROTTLE_MESSAGE, $message) === 1)
            ->assertHeader('Retry-After');
    }

    /**
     * 会員登録の上限は設定（fortify.register_per_minute）で変えられる。上限までは通り、その次から制限される
     */
    #[Test]
    public function 会員登録の上限は設定で変えられる(): void
    {
        config(['fortify.register_per_minute' => 2]);

        $this->register(['last_name' => ''])->assertSessionHasErrors('last_name');
        $this->register(['last_name' => ''])->assertSessionHasErrors('last_name');
        $this->register(['last_name' => ''])->assertSessionHasErrors('email');
        $this->assertMatchesRegularExpression(self::THROTTLE_MESSAGE, session('errors')->first('email'));
    }

    /**
     * 上限を大きくすると、6回を超えて続けても制限されない（E2E用アプリの設定）
     */
    #[Test]
    public function 上限を大きくすると続けて登録できる(): void
    {
        config(['fortify.register_per_minute' => 1000]);

        for ($i = 1; $i <= 8; $i++) {
            $this->register(['last_name' => ''])->assertSessionHasErrors('last_name')->assertSessionDoesntHaveErrors('email');
        }
    }

    /**
     * 待ち時間の秒数は、制限の残り時間（Retry-After）と同じ。1分以内の数字で出る
     */
    #[Test]
    public function 制限のメッセージの秒数は残り時間と同じ(): void
    {
        config(['fortify.register_per_minute' => 1]);
        $this->register(['last_name' => '']);

        $response = $this->postJson(route('register.store'), []);

        $seconds = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThanOrEqual(1, $seconds);
        $this->assertLessThanOrEqual(60, $seconds);
        $response->assertStatus(429)->assertJsonPath('message', "登録の操作が続いたため、{$seconds}秒後に再度お試しください。");
    }

    /**
     * 上限の環境変数が空・数字以外・0以下のときは既定の5回。1以上の整数のときだけその値になる
     */
    #[Test]
    public function 上限の環境変数が不正なときは既定の5回になる(): void
    {
        $name = 'REGISTER_THROTTLE_PER_MINUTE';
        $originalServer = $_SERVER[$name] ?? null;
        $originalEnv = $_ENV[$name] ?? null;
        $originalPutenv = getenv($name);

        try {
            foreach ([null, '', ' ', 'abc', '5/min', '5.5', '0', '-3', 'true', 'null', '(null)'] as $value) {
                $this->assertSame(5, $this->evaluateRegisterLimit($value), '既定の5になるはずの値: '.var_export($value, true));
            }
            foreach (['1' => 1, '7' => 7, ' 20 ' => 20, '1000' => 1000] as $value => $expected) {
                $this->assertSame($expected, $this->evaluateRegisterLimit((string) $value), $expected.' になるはずの値: '.$value);
            }
        } finally {
            $this->restoreEnvEntry($_SERVER, $name, $originalServer);
            $this->restoreEnvEntry($_ENV, $name, $originalEnv);
            $originalPutenv === false ? putenv($name) : putenv($name.'='.$originalPutenv);
        }
    }

    /**
     * $_SERVER・$_ENV の1項目を、保存しておいた元の状態に戻す（未設定だったものは未設定に戻す）
     *
     * @param  array<string, mixed>  $store
     */
    private function restoreEnvEntry(array &$store, string $name, mixed $original): void
    {
        if ($original === null) {
            unset($store[$name]);
        } else {
            $store[$name] = $original;
        }
    }

    /**
     * 環境変数を指定の値にして、config/fortify.php の register_per_minute を評価し直す（null なら未設定にする）。
     * env() は $_SERVER を先に読むため、$_ENV・putenv に加えて $_SERVER も書き換える。
     */
    private function evaluateRegisterLimit(?string $value): mixed
    {
        $name = 'REGISTER_THROTTLE_PER_MINUTE';

        if ($value === null) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        } else {
            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
            putenv($name.'='.$value);
        }

        $config = require config_path('fortify.php');

        return $config['register_per_minute'];
    }
}
