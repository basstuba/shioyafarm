<?php

namespace Tests\Feature;

use App\Enums\AdminTwoFactorSendResult;
use App\Http\Controllers\AdminTwoFactorController;
use App\Mail\AdminTwoFactorCodeMail;
use App\Models\Admin;
use App\Models\Admin_two_factor_code;
use App\Models\User;
use App\Services\AdminTwoFactorMissStore;
use App\Services\AdminTwoFactorService;
use App\Services\AdminTwoFactorStore;
use App\Support\AdminTwoFactorHash;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Validated;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * 管理者の二段階認証（メールのワンタイムコード）のテスト
 *
 * オンの設定（既定）で、ログイン → 確認コードの入力 → ログイン完了までの流れと、コードの誤り・期限切れ・再送・オフのときを確かめる。
 */
class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const string WRONG_MESSAGE = '確認コードが正しくありません。（あと%d回入力できます）';

    private const string LOCKED_MESSAGE = '確認コードを5回間違えたため、無効にしました。もう一度ログインしてください。';

    private const string EXPIRED_MESSAGE = '確認コードの有効期限が切れました。「コードを再送する」から新しいコードを受け取ってください。';

    private const string RESENT_MESSAGE = '新しい確認コードをお送りしました。前のコードは使えなくなります。';

    private const string TOO_SOON_MESSAGE = '確認コードの再送は、前回の送信から1分たってからお試しください。';

    private const string MAIL_FAILED_MESSAGE = '確認コードのメールを送信できませんでした。時間をおいてもう一度お試しください。';

    private const string CSRF_MESSAGE = 'ページの有効期限が切れました。もう一度ログインしてください。';

    private const string CSRF_INPUT_MESSAGE = 'ページの有効期限が切れました。もう一度入力してください。';

    private const string CSRF_RESEND_MESSAGE = 'ページの有効期限が切れました。もう一度「コードを再送する」を押してください。';

    /** 既定のコード（Admin_two_factor_codeFactory と同じ） */
    private const string CODE = '123456';

    /**
     * 差出人は config('mail.from') から出るため、.env に左右されないよう、既定の値にそろえる。二段階認証はオン
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.from.address' => 'shioyafarm@example.com',
            'mail.from.name' => 'しおや農園',
            'auth.admin_two_factor.enabled' => true,
        ]);
    }

    /**
     * テスト用の管理者を作る
     */
    private function createAdmin(string $email = 'admin@admin.com'): Admin
    {
        return Admin::factory()->create([
            'name' => 'テスト管理者',
            'email' => $email,
            'password' => Hash::make('admin1234'),
        ]);
    }

    /**
     * パスワードでログインする（二段階認証がオンなら確認コードの入力待ちになる）
     */
    private function postLogin(string $email = 'admin@admin.com', string $password = 'admin1234'): TestResponse
    {
        return $this->post(route('admin.login.store'), ['email' => $email, 'password' => $password]);
    }

    /**
     * 確認コードの入力待ちのセッションの値（管理者のIDと、パスワードの派生値）
     *
     * @return array<string, array{id: int, fingerprint: string}>
     */
    private function pendingSession(Admin $admin): array
    {
        return [AdminTwoFactorStore::SESSION_KEY => ['id' => $admin->id, 'fingerprint' => AdminTwoFactorHash::passwordFingerprint($admin->fresh()->password)]];
    }

    /**
     * 確認コードの入力待ちの状態にする（既定はコード「123456」・失敗0回・10分後に期限切れ・送信は今）
     *
     * @param  array<string, mixed>  $overrides  コードの記録の項目の上書き
     */
    private function startPending(Admin $admin, array $overrides = []): Admin_two_factor_code
    {
        $this->withSession($this->pendingSession($admin));

        return Admin_two_factor_code::factory()->create(['admin_id' => $admin->id] + $overrides);
    }

    /**
     * 確認コードを送信する
     */
    private function postCode(mixed $code): TestResponse
    {
        return $this->post(route('admin.two-factor.store'), ['code' => $code]);
    }

    /**
     * 最後に送ったメールのコード（メール本文から取り出す）
     */
    private function lastMailedCode(): string
    {
        $mail = Mail::sent(AdminTwoFactorCodeMail::class)->last();
        $this->assertInstanceOf(AdminTwoFactorCodeMail::class, $mail);
        $this->assertSame(1, preg_match('/(?<!\d)(\d{6})(?!\d)/', strip_tags($mail->render()), $matches));

        return $matches[1];
    }

    /**
     * CSRFトークンの検証を有効にする（テストでは既定でスキップされるため、環境名を変えて回避する）
     */
    private function enableCsrfCheck(): void
    {
        $this->app['env'] = 'local';
    }

    // ---- ルート ----

    /**
     * 3つのルートは AdminTwoFactorController が受ける
     */
    #[Test]
    public function ルートは_admin_two_factor_controllerが受ける(): void
    {
        $routes = Route::getRoutes();

        $this->assertSame(AdminTwoFactorController::class.'@create', $routes->getByName('admin.two-factor')->getActionName());
        $this->assertSame(AdminTwoFactorController::class.'@store', $routes->getByName('admin.two-factor.store')->getActionName());
        $this->assertSame(AdminTwoFactorController::class.'@resend', $routes->getByName('admin.two-factor.resend')->getActionName());
        $this->assertSame('admin/two-factor', $routes->getByName('admin.two-factor')->uri());
        $this->assertSame('admin/two-factor/resend', $routes->getByName('admin.two-factor.resend')->uri());
    }

    // ---- AC-1：パスワードを通ったあと ----

    /**
     * パスワードが正しくても、まだログインせず、確認コードの入力ページへ進み、メールが1通届く
     */
    #[Test]
    public function パスワードが正しいとログインせず確認コードの入力ページへ進みメールが1通届く(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();

        $response = $this->postLogin();

        $response->assertRedirect(route('admin.two-factor'));
        $this->assertGuest('admin');
        $this->assertSame($this->pendingSession($admin)[AdminTwoFactorStore::SESSION_KEY], session(AdminTwoFactorStore::SESSION_KEY));
        Mail::assertSentCount(1);
        Mail::assertSent(AdminTwoFactorCodeMail::class, fn (AdminTwoFactorCodeMail $mail): bool => $mail->hasTo('admin@admin.com') && $mail->admin->is($admin));
    }

    /**
     * コードは数字6桁で、ハッシュ化して保存し、期限は10分後・失敗回数は0・送信時刻は今
     */
    #[Test]
    public function コードはハッシュ化して保存し有効期限は10分で失敗回数は0(): void
    {
        Mail::fake();
        $this->freezeTime();
        $admin = $this->createAdmin();

        $this->postLogin();

        $code = $this->lastMailedCode();
        $record = Admin_two_factor_code::query()->where('admin_id', $admin->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/\A\d{6}\z/', $code);
        $this->assertNotSame($code, $record->getRawOriginal('code'));
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $record->code);
        $this->assertSame(AdminTwoFactorHash::code($code), $record->code);
        $this->assertSame(0, $record->attempts);
        $this->assertSame(now()->addMinutes(10)->timestamp, $record->expires_at->timestamp);
        $this->assertSame(now()->timestamp, $record->sent_at->timestamp);
        $this->assertDatabaseCount('admin_two_factor_codes', 1);
    }

    /**
     * 作ったコードは何度でも6桁の数字になる
     */
    #[Test]
    public function コードは毎回6桁の数字になる(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();
        $service = app(AdminTwoFactorService::class);

        foreach (range(1, 30) as $ignored) {
            $this->assertSame(AdminTwoFactorSendResult::Sent, $service->issue($admin));
            $this->assertMatchesRegularExpression('/\A\d{6}\z/', $this->lastMailedCode());
        }
    }

    /**
     * パスワードを通ったときにセッションIDを作り直す
     */
    #[Test]
    public function パスワードを通ったときセッション_i_dを作り直す(): void
    {
        Mail::fake();
        $this->createAdmin();
        $this->get(route('admin.login'));
        $before = session()->getId();

        $this->postLogin();

        $this->assertNotSame($before, session()->getId());
    }

    /**
     * パスワードが違うときは今までどおりエラーになり、コードもメールも作らない
     */
    #[Test]
    public function パスワードが違うときはエラーでメールは送らない(): void
    {
        Mail::fake();
        $this->createAdmin();

        $response = $this->postLogin('admin@admin.com', 'wrongpass');

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        $response->assertSessionMissing(AdminTwoFactorStore::SESSION_KEY);
        $this->assertGuest('admin');
        Mail::assertNothingSent();
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
    }

    /**
     * 管理者でないメールアドレス（会員を含む）でも同じエラーで、メールは送らない
     */
    #[Test]
    public function 管理者でないメールアドレスではエラーでメールは送らない(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'member@example.com', 'password' => Hash::make('password123')]);

        $this->postLogin('member@example.com', 'password123')
            ->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        $this->postLogin('nobody@example.com', 'admin1234')
            ->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
    }

    // ---- AC-2・AC-15：確認コードの入力ページ ----

    /**
     * ページの要素がそろう
     */
    #[Test]
    public function 確認コードの入力ページが表示される(): void
    {
        $this->startPending($this->createAdmin());

        $response = $this->get(route('admin.two-factor'));

        $response->assertOk();
        $response->assertViewIs('auth.admin.two-factor');
        $response->assertSee('<title>確認コードの入力 | Shioya Farm</title>', false);
        $response->assertSee('<h1 class="text-[28px] font-medium text-ink lg:text-[32px]">確認コードの入力</h1>', false);
        $response->assertSee('ご登録のメールアドレス宛てに、', false);
        $response->assertSee('コードの有効期限は10分です。', false);
        $response->assertSee('<label class="text-base font-medium" for="code">確認コード</label>', false);
        $response->assertSee('action="'.route('admin.two-factor.store').'"', false);
        $response->assertSee('inputmode="numeric"', false);
        $response->assertSee('autocomplete="one-time-code"', false);
        $response->assertSee('maxlength="6"', false);
        $response->assertSee('>ログインする</button>', false);
        $response->assertSee('>コードを再送する</button>', false);
        $response->assertSee('form="two-factor-resend"', false);
        $response->assertSee('id="two-factor-resend" action="'.route('admin.two-factor.resend').'"', false);
        $response->assertSee('href="'.route('admin.login').'">ログインへ戻る</a>', false);
        $response->assertSee('&copy;Shioya farm', false);
    }

    /**
     * 案内文は、コードの有効期限（設定値）を出す
     */
    #[Test]
    public function 案内文の有効期限は設定値から出る(): void
    {
        config(['auth.admin_two_factor.expire' => 15]);
        $this->startPending($this->createAdmin());

        $this->get(route('admin.two-factor'))->assertSee('コードの有効期限は15分です。', false);
    }

    /**
     * 管理画面用の背景画像・配色で、ロゴはリンクにせず、PCは左にフォーム（ログインの反対向きにしない）
     */
    #[Test]
    public function ページは管理画面用の背景画像と配色でロゴはリンクにしない(): void
    {
        $this->startPending($this->createAdmin());

        $html = $this->get(route('admin.two-factor'))->getContent();

        $this->assertStringContainsString('admin-login-page-back-image.webp', $html);
        $this->assertStringContainsString('lg:flex-row', $html);
        $this->assertStringNotContainsString('lg:flex-row-reverse', $html);
        $this->assertStringNotContainsString('cream', $html);
        // ロゴ（img）を囲むリンクがない
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*>\s*<img/', $html);
        // 入力欄はグレー系、ボタンは緑の地に黒文字（パスワード再設定と同じ部品）
        $this->assertStringContainsString('border-line-strong', $html);
        $this->assertStringContainsString('bg-accent text-ink', $html);
    }

    /**
     * SPで入力欄・ボタン・再送・リンクの高さが44px以上になるクラスが付く
     */
    #[Test]
    public function s_pの入力欄とボタンとリンクの高さは44px以上(): void
    {
        $this->startPending($this->createAdmin());

        $html = $this->get(route('admin.two-factor'))->getContent();

        // 入力欄・「ログインする」・「コードを再送する」は h-11（44px）、「ログインへ戻る」は min-h-11
        $this->assertSame(1, preg_match('/<input[^>]*id="code"[^>]*>/s', $html, $input));
        $this->assertStringContainsString('h-11', $input[0]);
        $this->assertSame(1, preg_match('/<button[^>]*form="two-factor-resend"[^>]*>/s', $html, $resend));
        $this->assertStringContainsString('h-11', $resend[0]);
        $this->assertSame(1, preg_match('/<button[^>]*type="submit"[^>]*>ログインする<\/button>/s', $html, $submit));
        $this->assertStringContainsString('h-11', $submit[0]);
        $this->assertSame(1, preg_match('/<a[^>]*>ログインへ戻る<\/a>/s', $html, $back));
        $this->assertStringContainsString('min-h-11', $back[0]);
    }

    /**
     * 入力エラーのあと、入力欄の近くにエラーが出る
     */
    #[Test]
    public function 入力エラーは入力欄の近くに表示される(): void
    {
        $this->startPending($this->createAdmin());

        $this->postCode('abc');
        $html = $this->get(route('admin.two-factor'))->getContent();

        $this->assertStringContainsString('確認コードは6桁の数字で入力してください', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('id="code-error"', $html);
    }

    /**
     * 通常の入力欄の出力は変わらない（inputmode・maxlength は指定したときだけ出す）
     */
    #[Test]
    public function パスワード再設定のページには_inputmodeを出さない(): void
    {
        $html = $this->get(route('admin.password.request'))->getContent();

        $this->assertStringNotContainsString('inputmode', $html);
        $this->assertStringNotContainsString('maxlength', $html);
    }

    // ---- AC-3：メール ----

    /**
     * メールの差出人・件名・宛先
     */
    #[Test]
    public function 確認コードのメールの差出人と件名と宛先(): void
    {
        $mail = new AdminTwoFactorCodeMail($this->createAdmin(), '012345', 10);

        $mail->assertTo('admin@admin.com');
        $mail->assertFrom('shioyafarm@example.com', 'しおや農園');
        $mail->assertHasSubject('【しおや農園】管理画面のログイン確認コード');
    }

    /**
     * 本文は日本語（管理者名・コード・有効期限・心当たりがない場合の案内）で、英語の定型文を含まない（HTML版とテキスト版）
     */
    #[Test]
    public function 確認コードのメールの本文は日本語で英語の定型文を含まない(): void
    {
        $mail = new AdminTwoFactorCodeMail($this->createAdmin(), '012345', 10);
        $content = $mail->content();
        $text = html_entity_decode(app(Markdown::class)->renderText($content->markdown, $content->with)->toHtml());
        $html = $mail->render();

        $this->assertStringContainsString('テスト管理者 様', html_entity_decode(strip_tags($html)));
        $this->assertStringContainsString('テスト管理者 様', $text);
        foreach ([$html, $text] as $body) {
            $this->assertStringContainsString('012345', $body);
            $this->assertStringContainsString('有効期限は、お送りしてから10分', $body);
            $this->assertStringContainsString('お心当たりがない場合は、パスワードが第三者に知られているおそれがあります', $body);
            $this->assertStringContainsString('パスワードを再設定してください', $body);
            foreach (['Hello', 'Regards', 'having trouble', 'Laravel'] as $english) {
                $this->assertStringNotContainsString($english, $body);
            }
        }
    }

    /**
     * メールの有効期限の表示は設定値（分）から出る
     */
    #[Test]
    public function メールの有効期限の表示は設定値から出る(): void
    {
        // メールは、送る側から渡された値を出す（設定値を自分で読まない）
        config(['auth.admin_two_factor.expire' => 99]);

        $this->assertStringContainsString('有効期限は、お送りしてから15分', (new AdminTwoFactorCodeMail($this->createAdmin(), '012345', 15))->render());
    }

    /**
     * 管理者名に含まれる記号が、メール本文で Markdown として解釈されない
     */
    #[Test]
    public function 管理者名の記号はメール本文でそのまま表示される(): void
    {
        $admin = Admin::factory()->create(['name' => '**太郎**']);

        $this->assertStringContainsString('**太郎** 様', html_entity_decode(strip_tags((new AdminTwoFactorCodeMail($admin, '012345', 10))->render())));
    }

    // ---- AC-4：ログイン完了 ----

    /**
     * 正しいコードで、管理者としてログインして /admin へ進み、コードと入力待ちの状態は消える
     */
    #[Test]
    public function 正しいコードでログインして管理画面のトップへ進む(): void
    {
        $admin = $this->createAdmin();
        $this->startPending($admin);

        $response = $this->postCode(self::CODE);

        $response->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('web');
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
        $response->assertSessionMissing(AdminTwoFactorStore::SESSION_KEY);
        $this->get(route('admin.home'))->assertOk();
    }

    /**
     * ログインの完了でセッションIDとCSRFトークンを作り直す
     */
    #[Test]
    public function ログインの完了でセッション_i_dとトークンを作り直す(): void
    {
        $this->startPending($this->createAdmin());
        $this->get(route('admin.two-factor'));
        $idBefore = session()->getId();
        $tokenBefore = session()->token();

        $this->postCode(self::CODE);

        $this->assertNotSame($idBefore, session()->getId());
        $this->assertNotSame($tokenBefore, session()->token());
    }

    /**
     * ログイン後の戻り先（url.intended）は使わない（会員のログインの戻り先だから）
     */
    #[Test]
    public function ログインの完了後は会員のログインの戻り先を使わない(): void
    {
        $this->startPending($this->createAdmin());

        $response = $this->withSession(['url.intended' => route('shop.items.index')])->postCode(self::CODE);

        $response->assertRedirect(route('admin.home'));
        $this->assertSame(route('shop.items.index'), session('url.intended'));
    }

    /**
     * 一度使ったコードは、ログアウトしたあとにもう一度使えない
     */
    #[Test]
    public function 一度使ったコードはログアウト後に使えない(): void
    {
        Mail::fake();
        $this->createAdmin();

        $this->postLogin();
        $first = $this->lastMailedCode();
        $this->postCode($first)->assertRedirect(route('admin.home'));
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        // 入力待ちでない状態では、前のコードを送っても何も起きない
        $this->postCode($first)->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');

        // もう一度ログインして、前のコードを入力しても通らない（新しいコードと同じ数字になる100万分の1は除く）
        $this->postLogin();
        if ($this->lastMailedCode() !== $first) {
            $this->postCode($first)->assertSessionHasErrors(['code' => sprintf(self::WRONG_MESSAGE, 4)]);
            $this->assertGuest('admin');
        }
    }

    // ---- AC-5：入力の形式 ----

    /**
     * 未入力は「確認コードを入力してください」。失敗回数には数えない
     */
    #[Test]
    public function コードが未入力ならエラーで失敗回数に数えない(): void
    {
        $record = $this->startPending($this->createAdmin());

        $this->postCode('')
            ->assertRedirect(route('admin.two-factor'))
            ->assertSessionHasErrors(['code' => '確認コードを入力してください']);
        $this->post(route('admin.two-factor.store'))
            ->assertSessionHasErrors(['code' => '確認コードを入力してください']);

        $this->assertSame(0, $record->fresh()->attempts);
        $this->assertGuest('admin');
    }

    /**
     * 6桁の数字でない入力は「確認コードは6桁の数字で入力してください」。失敗回数には数えない
     */
    #[Test]
    public function 形式が違うコードはエラーで失敗回数に数えない(): void
    {
        $record = $this->startPending($this->createAdmin());

        foreach (['12345', '1234567', 'abcdef', '12345a', '12 456', '１２３４５', '12.456', '-12345', ['123456']] as $input) {
            $this->postCode($input)
                ->assertRedirect(route('admin.two-factor'))
                ->assertSessionHasErrors(['code' => '確認コードは6桁の数字で入力してください']);
        }

        $this->assertSame(0, $record->fresh()->attempts);
        $this->assertGuest('admin');
        $this->assertDatabaseCount('admin_two_factor_codes', 1);
    }

    /**
     * 全角数字は半角にして受け付ける
     */
    #[Test]
    public function 全角数字は半角として受け付ける(): void
    {
        $admin = $this->createAdmin();
        $this->startPending($admin);

        $this->postCode('１２３４５６')->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    /**
     * 前後の空白（全角を含む）は取り除いて受け付ける。全角数字の間違いは失敗回数に数える
     */
    #[Test]
    public function 前後の空白は取り除き全角数字の間違いは数える(): void
    {
        $record = $this->startPending($this->createAdmin());

        $this->postCode('　１２３４５７ ')
            ->assertSessionHasErrors(['code' => sprintf(self::WRONG_MESSAGE, 4)]);
        $this->assertSame(1, $record->fresh()->attempts);

        $this->postCode(' 123456　')->assertRedirect(route('admin.home'));
    }

    /**
     * 0から始まるコードも使える（先頭の0を落とさない）
     */
    #[Test]
    public function 先頭が0のコードも受け付ける(): void
    {
        $admin = $this->createAdmin();
        $this->startPending($admin, ['code' => AdminTwoFactorHash::code('000123')]);

        $this->postCode('000123')->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    // ---- AC-6：間違い ----

    /**
     * 正しくないコードは、残りの回数つきのエラーになり、ログインしない（失敗回数が増える）
     */
    #[Test]
    public function 正しくないコードは残りの回数を示してログインしない(): void
    {
        $record = $this->startPending($this->createAdmin());

        $response = $this->postCode('654321');

        $response->assertRedirect(route('admin.two-factor'));
        $response->assertSessionHasErrors(['code' => sprintf(self::WRONG_MESSAGE, 4)]);
        $this->assertSame(1, $record->fresh()->attempts);
        $this->assertGuest('admin');
        $this->assertDatabaseCount('admin_two_factor_codes', 1);

        $this->get(route('admin.two-factor'))->assertSee('確認コードが正しくありません。（あと4回入力できます）');
    }

    /**
     * 5回目の間違いでコードを削除し、入力待ちの状態も消して、ログインページへ戻す
     */
    #[Test]
    public function コードを5回間違えると無効になりログインからやり直しになる(): void
    {
        $record = $this->startPending($this->createAdmin());

        foreach ([4, 3, 2, 1] as $remaining) {
            $this->postCode('654321')->assertSessionHasErrors(['code' => sprintf(self::WRONG_MESSAGE, $remaining)]);
        }
        $this->assertSame(4, $record->fresh()->attempts);

        $response = $this->postCode('654321');

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('status', self::LOCKED_MESSAGE);
        $response->assertSessionMissing(AdminTwoFactorStore::SESSION_KEY);
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
        $this->assertGuest('admin');

        // 入力待ちではなくなるので、確認コードのページ・送信・再送はログインページへ戻る
        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
        $this->postCode(self::CODE)->assertRedirect(route('admin.login'));
        $this->post(route('admin.two-factor.resend'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    /**
     * ログインページに、5回間違えた案内が既存の案内の枠で表示される
     */
    #[Test]
    public function ログインページに5回間違えた案内が案内の枠で表示される(): void
    {
        $this->startPending($this->createAdmin(), ['attempts' => 4]);

        $html = $this->followingRedirects()->postCode('654321')->getContent();

        $this->assertStringContainsString('role="status">'.self::LOCKED_MESSAGE, $html);
        $this->assertStringContainsString('border border-line-strong bg-surface', $html);
        $this->assertStringContainsString('<title>管理者用ログイン | Shioya Farm</title>', $html);
    }

    /**
     * 間違えられる回数は設定値から決まる
     */
    #[Test]
    public function 間違えられる回数は設定値から決まる(): void
    {
        config(['auth.admin_two_factor.max_attempts' => 2]);
        $this->startPending($this->createAdmin());

        $this->postCode('654321')->assertSessionHasErrors(['code' => sprintf(self::WRONG_MESSAGE, 1)]);
        $this->postCode('654321')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', '確認コードを2回間違えたため、無効にしました。もう一度ログインしてください。');
    }

    /**
     * 4回間違えたあとでも、正しいコードならログインできる
     */
    #[Test]
    public function 四回間違えたあとでも正しいコードならログインできる(): void
    {
        $admin = $this->createAdmin();
        $this->startPending($admin, ['attempts' => 4]);

        $this->postCode(self::CODE)->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    // ---- AC-7：期限切れ ----

    /**
     * 有効期限が切れたコードは、正しくても受け付けず、案内を出す（失敗回数には数えない）
     */
    #[Test]
    public function 有効期限が切れたコードは受け付けない(): void
    {
        $this->freezeTime();
        $record = $this->startPending($this->createAdmin(), ['expires_at' => now()->subSecond()]);

        $this->postCode(self::CODE)
            ->assertRedirect(route('admin.two-factor'))
            ->assertSessionHasErrors(['code' => self::EXPIRED_MESSAGE]);
        $this->postCode('654321')->assertSessionHasErrors(['code' => self::EXPIRED_MESSAGE]);

        $this->assertGuest('admin');
        $this->assertSame(0, $record->fresh()->attempts);
        $this->get(route('admin.two-factor'))->assertSee('確認コードの有効期限が切れました。「コードを再送する」から新しいコードを受け取ってください。', false);
    }

    /**
     * 期限の1秒前までは使える（境界）
     */
    #[Test]
    public function 有効期限の直前までは使える(): void
    {
        $this->freezeTime();
        $admin = $this->createAdmin();
        $this->startPending($admin, ['expires_at' => now()->addSecond()]);

        $this->postCode(self::CODE)->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    /**
     * ログインで送ったコードは、10分たつと期限切れになる
     */
    #[Test]
    public function ログインで送ったコードは10分たつと期限切れになる(): void
    {
        Mail::fake();
        $this->freezeTime();
        $this->createAdmin();
        $this->postLogin();
        $code = $this->lastMailedCode();

        $this->travel(10)->minutes();
        $this->travel(1)->seconds();
        $this->postCode($code)->assertSessionHasErrors(['code' => self::EXPIRED_MESSAGE]);

        $this->assertGuest('admin');
    }

    /**
     * 10分たつ前（9分59秒後）は、ログインで送ったコードを使える
     */
    #[Test]
    public function ログインで送ったコードは10分たつ前なら使える(): void
    {
        Mail::fake();
        $this->freezeTime();
        $admin = $this->createAdmin();
        $this->postLogin();
        $code = $this->lastMailedCode();

        $this->travel(599)->seconds();
        $this->postCode($code)->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    /**
     * 期限切れのあと、再送すれば新しいコードでログインできる
     */
    #[Test]
    public function 期限切れのあと再送した新しいコードでログインできる(): void
    {
        Mail::fake();
        $this->freezeTime();
        $admin = $this->createAdmin();
        $this->postLogin();
        $this->travel(11)->minutes();

        $this->post(route('admin.two-factor.resend'))->assertSessionHas('status', self::RESENT_MESSAGE);
        $new = $this->lastMailedCode();

        $this->postCode($new)->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    // ---- AC-8・AC-9：再送 ----

    /**
     * 前回の送信から1分以上たっていれば、新しいコードに置き換えて送る（有効期限は数え直すが、失敗回数は引き継ぐ）。前のコードは使えない
     */
    #[Test]
    public function 再送すると新しいコードに置き換わり前のコードは使えない(): void
    {
        Mail::fake();
        $this->freezeTime();
        $admin = $this->createAdmin();
        $this->startPending($admin, [
            'attempts' => 3,
            'expires_at' => now()->addMinutes(2),
            'sent_at' => now()->subMinute(),
        ]);

        $response = $this->post(route('admin.two-factor.resend'));

        $response->assertRedirect(route('admin.two-factor'));
        $response->assertSessionHas('status', self::RESENT_MESSAGE);
        Mail::assertSentCount(1);
        Mail::assertSent(AdminTwoFactorCodeMail::class, fn (AdminTwoFactorCodeMail $mail): bool => $mail->hasTo('admin@admin.com'));
        $record = Admin_two_factor_code::query()->firstOrFail();
        $this->assertSame(3, $record->attempts);
        $this->assertSame(now()->addMinutes(10)->timestamp, $record->expires_at->timestamp);
        $this->assertSame(now()->timestamp, $record->sent_at->timestamp);
        $this->assertDatabaseCount('admin_two_factor_codes', 1);

        // 前のコード（123456）は使えない。失敗回数は引き継がれているので、間違えると「あと1回」（新しいコードが同じ数字になる100万分の1は除く）
        $new = $this->lastMailedCode();
        if ($new !== self::CODE) {
            $this->postCode(self::CODE)->assertSessionHasErrors(['code' => sprintf(self::WRONG_MESSAGE, 1)]);
            $this->assertGuest('admin');
        }
        $this->postCode($new)->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    /**
     * 再送の案内がコード入力ページに案内の枠で表示される
     */
    #[Test]
    public function 再送の案内は確認コードのページの案内の枠に出る(): void
    {
        Mail::fake();
        $this->freezeTime();
        $this->startPending($this->createAdmin(), ['sent_at' => now()->subMinutes(2)]);

        $html = $this->followingRedirects()->post(route('admin.two-factor.resend'))->getContent();

        $this->assertStringContainsString('role="status">'.self::RESENT_MESSAGE, $html);
        $this->assertStringContainsString('<title>確認コードの入力 | Shioya Farm</title>', $html);
    }

    /**
     * 前回の送信から1分たっていないときは、案内だけ出してメールは送らず、コードも変えない
     */
    #[Test]
    public function 前回の送信から1分たっていないと再送できない(): void
    {
        Mail::fake();
        $this->freezeTime();
        $record = $this->startPending($this->createAdmin(), ['sent_at' => now()->subSeconds(59)]);
        $hash = $record->code;

        $response = $this->post(route('admin.two-factor.resend'));

        $response->assertRedirect(route('admin.two-factor'));
        $response->assertSessionHas('status', self::TOO_SOON_MESSAGE);
        Mail::assertNothingSent();
        $this->assertSame($hash, $record->fresh()->code);
        $this->assertSame(now()->subSeconds(59)->timestamp, $record->fresh()->sent_at->timestamp);
        $this->get(route('admin.two-factor'))->assertSee(self::TOO_SOON_MESSAGE);
    }

    /**
     * ちょうど1分たっていれば再送できる（境界）
     */
    #[Test]
    public function 再送の1分の境界(): void
    {
        Mail::fake();
        $this->freezeTime();
        $this->startPending($this->createAdmin(), ['sent_at' => now()->subSeconds(60)]);

        $this->post(route('admin.two-factor.resend'))->assertSessionHas('status', self::RESENT_MESSAGE);

        Mail::assertSentCount(1);
    }

    /**
     * ログイン直後は前回の送信が今なので、すぐには再送できない。再送のあとも1分は再送できない
     */
    #[Test]
    public function ログイン直後と再送直後は再送できない(): void
    {
        Mail::fake();
        $this->freezeTime();
        $this->createAdmin();
        $this->postLogin();

        $this->post(route('admin.two-factor.resend'))->assertSessionHas('status', self::TOO_SOON_MESSAGE);
        Mail::assertSentCount(1);

        $this->travel(61)->seconds();
        $this->post(route('admin.two-factor.resend'))->assertSessionHas('status', self::RESENT_MESSAGE);
        Mail::assertSentCount(2);

        $this->travel(30)->seconds();
        $this->post(route('admin.two-factor.resend'))->assertSessionHas('status', self::TOO_SOON_MESSAGE);
        Mail::assertSentCount(2);
    }

    // ---- AC-10：入力待ちでないとき ----

    /**
     * パスワードを通っていないとき（直接開いた）は、確認コードのページ・送信・再送のどれもログインページへ戻る
     */
    #[Test]
    public function パスワードを通っていないと確認コードのページはログインへ戻る(): void
    {
        Mail::fake();
        $this->createAdmin();

        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
        $this->postCode(self::CODE)->assertRedirect(route('admin.login'));
        $this->post(route('admin.two-factor.resend'))->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
        Mail::assertNothingSent();
    }

    /**
     * 形式が違うコードでも、入力待ちでなければ（確認コードのページへ戻ったあとで）ログインページへ戻る
     */
    #[Test]
    public function 入力待ちでないとき形式が違うコードはログインページへ戻る(): void
    {
        $this->createAdmin();

        $this->followingRedirects()->postCode('abc')->assertSee('管理者用ログイン');
    }

    /**
     * コードの入力の途中で管理画面を開いても、ログインしていないのでログインページへ戻る
     */
    #[Test]
    public function コードの入力の途中は管理画面に入れない(): void
    {
        $this->startPending($this->createAdmin());

        $this->get(route('admin.home'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.users.index'))->assertRedirect(route('admin.login'));
    }

    /**
     * 管理者としてログイン中に確認コードのページを開くと /admin へ移動する
     */
    #[Test]
    public function ログイン中に確認コードのページを開くと管理画面のトップへ移動する(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')->get(route('admin.two-factor'))->assertRedirect(route('admin.home'));
        $this->actingAs($admin, 'admin')->post(route('admin.two-factor.store'), ['code' => self::CODE])->assertRedirect(route('admin.home'));
        $this->actingAs($admin, 'admin')->post(route('admin.two-factor.resend'))->assertRedirect(route('admin.home'));
    }

    /**
     * 入力待ちの管理者が削除されていたら、入力待ちを解いてログインページへ戻る
     */
    #[Test]
    public function 入力待ちの管理者がいなくなっていたらログインへ戻る(): void
    {
        $admin = $this->createAdmin();
        $this->withSession([AdminTwoFactorStore::SESSION_KEY => ['id' => $admin->id + 100, 'fingerprint' => AdminTwoFactorHash::passwordFingerprint($admin->password)]]);

        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));

        $this->assertNull(session(AdminTwoFactorStore::SESSION_KEY));
    }

    /**
     * セッションの値の形が正しくないとき（改ざん）は入力待ちとして扱わない
     */
    #[Test]
    public function セッションの値の形が正しくなければ入力待ちではない(): void
    {
        $admin = $this->createAdmin();

        foreach ([$admin->id, (string) $admin->id, ['id' => (string) $admin->id, 'fingerprint' => 'x'], ['id' => $admin->id], ['id' => $admin->id, 'fingerprint' => 'x']] as $value) {
            $this->flushSession();
            $this->withSession([AdminTwoFactorStore::SESSION_KEY => $value]);

            $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
        }
    }

    /**
     * 会員のログイン中でも、確認コードを通るまで管理者としては入れない
     */
    #[Test]
    public function 会員のログイン中でもコードを通るまで管理画面に入れない(): void
    {
        Mail::fake();
        $this->createAdmin();
        $this->actingAs(User::factory()->create());

        $this->postLogin()->assertRedirect(route('admin.two-factor'));

        $this->get(route('admin.home'))->assertRedirect(route('admin.login'));
    }

    // ---- テーブル ----

    /**
     * 管理者を削除すると、その管理者のコードも消える（外部キーの cascade）
     */
    #[Test]
    public function 管理者を削除するとコードも消える(): void
    {
        $admin = $this->createAdmin();
        Admin_two_factor_code::factory()->create(['admin_id' => $admin->id]);

        $admin->delete();

        $this->assertDatabaseCount('admin_two_factor_codes', 0);
    }

    /**
     * コードは管理者1人につき1件（unique）
     */
    #[Test]
    public function コードは管理者1人につき1件(): void
    {
        $admin = $this->createAdmin();
        Admin_two_factor_code::factory()->create(['admin_id' => $admin->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        Admin_two_factor_code::factory()->create(['admin_id' => $admin->id]);
    }

    /**
     * コードは配列・JSONに出さない（hidden）
     */
    #[Test]
    public function コードは配列に出さない(): void
    {
        $record = Admin_two_factor_code::factory()->create();

        $this->assertArrayNotHasKey('code', $record->toArray());
        $this->assertTrue($record->admin->is(Admin::query()->firstOrFail()));
    }

    // ---- AC-11：ログインへ戻る ----

    /**
     * ログインへ戻ってもう一度ログインすると、新しいコードになり前のコードは使えない
     */
    #[Test]
    public function ログインし直すと新しいコードに置き換わり前のコードは使えない(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();

        $this->postLogin();
        $first = $this->lastMailedCode();
        $this->get(route('admin.login'))->assertOk();
        $this->postLogin()->assertRedirect(route('admin.two-factor'));
        $second = $this->lastMailedCode();

        Mail::assertSentCount(2);
        $this->assertDatabaseCount('admin_two_factor_codes', 1);
        if ($second !== $first) {
            $this->postCode($first)->assertSessionHasErrors(['code' => sprintf(self::WRONG_MESSAGE, 4)]);
        }
        $this->postCode($second)->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    /**
     * ログインし直すと、前の失敗回数も数え直す
     */
    #[Test]
    public function ログインし直すと失敗回数も数え直す(): void
    {
        Mail::fake();
        $this->createAdmin();
        $this->postLogin();
        $this->postCode('654321');
        $this->postCode('654321');
        $this->assertSame(2, Admin_two_factor_code::query()->firstOrFail()->attempts);

        $this->postLogin();

        $this->assertSame(0, Admin_two_factor_code::query()->firstOrFail()->attempts);
    }

    // ---- AC-12：オフ ----

    /**
     * オフのときは、今までどおりパスワードだけでログインし、メールもコードも作らない
     */
    #[Test]
    public function 二段階認証がオフならパスワードだけでログインできる(): void
    {
        config(['auth.admin_two_factor.enabled' => false]);
        Mail::fake();
        $admin = $this->createAdmin();

        $response = $this->postLogin();

        $response->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $response->assertSessionMissing(AdminTwoFactorStore::SESSION_KEY);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
    }

    /**
     * オフのときも、パスワードが違えば今までどおりエラー
     */
    #[Test]
    public function 二段階認証がオフでもパスワードが違えばエラーになる(): void
    {
        config(['auth.admin_two_factor.enabled' => false]);
        Mail::fake();
        $this->createAdmin();

        $this->postLogin('admin@admin.com', 'wrongpass')
            ->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);

        $this->assertGuest('admin');
        Mail::assertNothingSent();
    }

    /**
     * オフのときは、確認コードのページ・送信・再送はログインページへ戻る（入力待ちの状態が残っていても）
     */
    #[Test]
    public function 二段階認証がオフなら確認コードのページはログインへ戻る(): void
    {
        config(['auth.admin_two_factor.enabled' => false]);
        Mail::fake();
        $this->startPending($this->createAdmin());

        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
        $this->postCode(self::CODE)->assertRedirect(route('admin.login'));
        $this->post(route('admin.two-factor.resend'))->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
        Mail::assertNothingSent();
    }

    /**
     * 設定の既定はオンで、.env.example に切り替え用の項目と公開デモでオフにする旨がある
     */
    #[Test]
    public function 設定の既定はオンで環境変数の例にオフにする旨がある(): void
    {
        $this->assertSame(10, config('auth.admin_two_factor.expire'));
        $this->assertSame(5, config('auth.admin_two_factor.max_attempts'));
        $this->assertSame(60, config('auth.admin_two_factor.resend_seconds'));

        $example = (string) file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString("ADMIN_TWO_FACTOR_ENABLED=true\n", $example);
        $this->assertStringContainsString('公開デモ', $example);
    }

    /**
     * 環境変数が空・不正な値のときはオンのまま。オフになるのは false・0・off・no のときだけ
     */
    #[Test]
    public function 環境変数が空や不正な値ならオンでありオフにできるのは決まった値だけ(): void
    {
        // 元の状態は $_SERVER・$_ENV・putenv で別々に保存する（CI では .env.example 由来の値が $_SERVER に入っている）
        $originalServer = $_SERVER['ADMIN_TWO_FACTOR_ENABLED'] ?? null;
        $originalEnv = $_ENV['ADMIN_TWO_FACTOR_ENABLED'] ?? null;
        $originalPutenv = getenv('ADMIN_TWO_FACTOR_ENABLED');

        try {
            foreach ([null, '', 'maybe', 'abc', 'true', '1', 'on', 'yes', 'null', '(null)', 'NULL', 'TRUE', 'empty', '(empty)', 'offf'] as $value) {
                $this->assertTrue($this->evaluateEnabledConfig($value), 'オンになるはずの値: '.var_export($value, true));
            }
            foreach (['false', '0', 'off', 'no', 'FALSE', 'False', 'Off', 'OFF', 'No', 'NO', '(false)', '"  off "'] as $value) {
                $this->assertFalse($this->evaluateEnabledConfig($value), 'オフになるはずの値: '.$value);
            }
        } finally {
            $this->restoreEnabledEnv($originalServer, $originalEnv, $originalPutenv);
        }
    }

    /**
     * $_SERVER・$_ENV・putenv の3つを、保存しておいた元の状態に戻す（未設定だったものは未設定に戻す）
     */
    private function restoreEnabledEnv(mixed $server, mixed $env, string|false $putenv): void
    {
        if ($server === null) {
            unset($_SERVER['ADMIN_TWO_FACTOR_ENABLED']);
        } else {
            $_SERVER['ADMIN_TWO_FACTOR_ENABLED'] = $server;
        }

        if ($env === null) {
            unset($_ENV['ADMIN_TWO_FACTOR_ENABLED']);
        } else {
            $_ENV['ADMIN_TWO_FACTOR_ENABLED'] = $env;
        }

        if ($putenv === false) {
            putenv('ADMIN_TWO_FACTOR_ENABLED');
        } else {
            putenv('ADMIN_TWO_FACTOR_ENABLED='.$putenv);
        }
    }

    /**
     * 環境変数を指定の値にして、config/auth.php の admin_two_factor.enabled を評価し直す（null なら環境変数を未設定にする）。
     * env() は $_SERVER を先に読むため、$_ENV・putenv に加えて $_SERVER も書き換える。
     */
    private function evaluateEnabledConfig(?string $value): bool
    {
        if ($value === null) {
            unset($_SERVER['ADMIN_TWO_FACTOR_ENABLED'], $_ENV['ADMIN_TWO_FACTOR_ENABLED']);
            putenv('ADMIN_TWO_FACTOR_ENABLED');
        } else {
            $_SERVER['ADMIN_TWO_FACTOR_ENABLED'] = $value;
            $_ENV['ADMIN_TWO_FACTOR_ENABLED'] = $value;
            putenv('ADMIN_TWO_FACTOR_ENABLED='.$value);
        }

        $config = require config_path('auth.php');

        return $config['admin_two_factor']['enabled'];
    }

    // ---- AC-13：メールの送信失敗 ----

    /**
     * ログインでメールを送れなかったときは、ログインページへ戻して案内を出し、ログインしない（コードも残さない）
     */
    #[Test]
    public function メールを送れなかったらログインページへ戻りログインしない(): void
    {
        Event::listen(MessageSending::class, function (): void {
            throw new RuntimeException('送信に失敗');
        });
        $this->createAdmin();

        $response = $this->postLogin();

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('status', self::MAIL_FAILED_MESSAGE);
        $response->assertSessionMissing(AdminTwoFactorStore::SESSION_KEY);
        $response->assertSessionHasInput('email', 'admin@admin.com');
        $response->assertSessionMissing('_old_input.password');
        $this->assertGuest('admin');
        $this->assertDatabaseCount('admin_two_factor_codes', 0);

        // 案内は既存の案内の枠で出て、メールアドレスは残る
        $html = $this->get(route('admin.login'))->getContent();
        $this->assertStringContainsString('role="status">'.self::MAIL_FAILED_MESSAGE, $html);
        $this->assertStringContainsString('border border-line-strong bg-surface', $html);
        $this->assertStringContainsString('value="admin@admin.com"', $html);
        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
    }

    /**
     * 管理者Aが入力待ちのまま、同じブラウザで別のログインがパスワードの誤りで終わったら、Aの入力待ちを解く
     */
    #[Test]
    public function 入力待ちのまま別のログインがパスワードの誤りで終わると入力待ちが解かれる(): void
    {
        Mail::fake();
        $this->startPending($this->createAdmin('a@example.com'));
        $this->createAdmin('b@example.com');

        $this->postLogin('b@example.com', 'wrong-password')->assertSessionHasErrors('email');

        $this->assertNull(session(AdminTwoFactorStore::SESSION_KEY));
        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
        Mail::assertNothingSent();
    }

    /**
     * 同じく、別のログインが停止中（確認コードの間違いが続いた管理者）で終わったときも、入力待ちを解く
     */
    #[Test]
    public function 入力待ちのまま別のログインが停止中で終わると入力待ちが解かれる(): void
    {
        Mail::fake();
        config(['auth.admin_two_factor.miss_limit' => 1]);
        $this->startPending($this->createAdmin('a@example.com'));
        $suspended = $this->createAdmin('b@example.com');
        app(AdminTwoFactorMissStore::class)->hit($suspended);

        $this->postLogin('b@example.com')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', fn (string $m): bool => str_contains($m, 'しばらくログインできません'));

        $this->assertNull(session(AdminTwoFactorStore::SESSION_KEY));
        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
        Mail::assertNothingSent();
    }

    /**
     * 同じく、別のログインでメールを送れなかったときも、入力待ちを解く
     */
    #[Test]
    public function 入力待ちのまま別のログインがメール送信失敗で終わると入力待ちが解かれる(): void
    {
        $this->startPending($this->createAdmin('a@example.com'));
        $this->createAdmin('b@example.com');
        Event::listen(MessageSending::class, function (): void {
            throw new RuntimeException('送信に失敗');
        });

        $this->postLogin('b@example.com')->assertSessionHas('status', self::MAIL_FAILED_MESSAGE);

        $this->assertNull(session(AdminTwoFactorStore::SESSION_KEY));
        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
    }

    /**
     * 再送でメールを送れなかったときも、ログインページへ戻して案内を出し、ログインしない
     */
    #[Test]
    public function 再送でメールを送れなかったらログインページへ戻る(): void
    {
        $this->freezeTime();
        $this->startPending($this->createAdmin(), ['sent_at' => now()->subMinutes(2)]);
        Event::listen(MessageSending::class, function (): void {
            throw new RuntimeException('送信に失敗');
        });

        $response = $this->post(route('admin.two-factor.resend'));

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('status', self::MAIL_FAILED_MESSAGE);
        $response->assertSessionMissing(AdminTwoFactorStore::SESSION_KEY);
        $this->assertGuest('admin');
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
    }

    /**
     * 送信に失敗したあとでも、もう一度ログインすれば送れる（失敗が残らない）
     */
    #[Test]
    public function メールの送信に失敗したあとでももう一度ログインできる(): void
    {
        $failed = false;
        Event::listen(MessageSending::class, function () use (&$failed): void {
            if (! $failed) {
                $failed = true;
                throw new RuntimeException('送信に失敗');
            }
        });
        $this->createAdmin();

        $this->postLogin()->assertSessionHas('status', self::MAIL_FAILED_MESSAGE);
        $this->postLogin()->assertRedirect(route('admin.two-factor'));

        $this->assertDatabaseCount('admin_two_factor_codes', 1);
        $this->assertTrue($failed);
    }

    // ---- AC-14：419 ----

    /**
     * CSRFトークンが古いとき、入力待ちが残っていれば、コードの送信は確認コードの入力ページへ戻る（コードは変えず、失敗にも数えない）
     */
    #[Test]
    public function コードの送信でトークンが古いと入力ページへ戻り届いたコードをそのまま使える(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();
        $record = $this->startPending($admin);
        $hash = $record->code;
        $this->enableCsrfCheck();

        $response = $this->withSession(['_token' => 'valid-token'] + $this->pendingSession($admin))
            ->post(route('admin.two-factor.store'), ['_token' => 'stale-token', 'code' => self::CODE]);

        $response->assertRedirect(route('admin.two-factor'));
        $response->assertSessionHas('status', self::CSRF_INPUT_MESSAGE);
        $this->assertGuest('admin');
        $this->assertSame($hash, $record->fresh()->code);
        $this->assertSame(0, $record->fresh()->attempts);
        Mail::assertNothingSent();

        // 案内は確認コードのページの案内の枠に出て、届いているコードで続けてログインできる
        $this->get(route('admin.two-factor'))->assertSee(self::CSRF_INPUT_MESSAGE);
        $this->post(route('admin.two-factor.store'), ['_token' => session()->token(), 'code' => self::CODE])->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    /**
     * CSRFトークンが古いとき、入力待ちが残っていれば、再送も確認コードの入力ページへ戻る（コードは置き換えず、メールも送らない）
     */
    #[Test]
    public function 再送でトークンが古いと入力ページへ戻りメールは送らない(): void
    {
        Mail::fake();
        $this->freezeTime();
        $admin = $this->createAdmin();
        $record = $this->startPending($admin, ['sent_at' => now()->subMinutes(2)]);
        $hash = $record->code;
        $this->enableCsrfCheck();

        $response = $this->withSession(['_token' => 'valid-token'] + $this->pendingSession($admin))
            ->post(route('admin.two-factor.resend'), ['_token' => 'stale-token']);

        $response->assertRedirect(route('admin.two-factor'));
        $response->assertSessionHas('status', self::CSRF_RESEND_MESSAGE);
        Mail::assertNothingSent();
        $this->assertSame($hash, $record->fresh()->code);
        $this->assertSame(now()->subMinutes(2)->timestamp, $record->fresh()->sent_at->timestamp);
        $this->assertGuest('admin');
    }

    /**
     * CSRFトークンが古く、入力待ちが残っていないとき（Cookie・セッションの削除など）は、コードの送信・再送とも、案内つきでログインページへ戻る
     */
    #[Test]
    public function トークンが古く入力待ちがなければログインページへ戻る(): void
    {
        Mail::fake();
        $this->createAdmin();
        $this->enableCsrfCheck();

        foreach (['admin.two-factor.store' => ['code' => self::CODE], 'admin.two-factor.resend' => []] as $name => $input) {
            $response = $this->withSession(['_token' => 'valid-token'])
                ->post(route($name), ['_token' => 'stale-token'] + $input);

            $response->assertRedirect(route('admin.login'));
            $response->assertSessionHas('status', self::CSRF_MESSAGE);
        }

        $this->assertGuest('admin');
        Mail::assertNothingSent();
        $this->get(route('admin.login'))->assertSee(self::CSRF_MESSAGE);
    }

    /**
     * 入力待ちが残っているかは、セッションだけで見る。オフのときは、いったん確認コードのページへ戻り、そのページがログインページへ戻す
     */
    #[Test]
    public function トークンが古くても二段階認証がオフなら確認コードのページからログインページへ戻る(): void
    {
        config(['auth.admin_two_factor.enabled' => false]);
        $admin = $this->createAdmin();
        $this->startPending($admin);
        $this->enableCsrfCheck();

        $this->withSession(['_token' => 'valid-token'] + $this->pendingSession($admin))
            ->post(route('admin.two-factor.store'), ['_token' => 'stale-token', 'code' => self::CODE])
            ->assertRedirect(route('admin.two-factor'));

        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
    }

    /**
     * 入力待ちが残っているかは、セッションだけで見る（DBの確認はしない）。パスワードが変わっていても、いったん確認コードのページへ戻り、そのページが入力待ちを解く
     */
    #[Test]
    public function トークンが古いときの戻り先の判定はセッションだけで行う(): void
    {
        $admin = $this->createAdmin();
        $this->startPending($admin);
        $pending = $this->pendingSession($admin);
        $admin->update(['password' => 'changed-password']);
        $this->enableCsrfCheck();

        $this->withSession(['_token' => 'valid-token'] + $pending)
            ->post(route('admin.two-factor.resend'), ['_token' => 'stale-token'])
            ->assertRedirect(route('admin.two-factor'))
            ->assertSessionHas(AdminTwoFactorStore::SESSION_KEY);

        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
        $this->assertNull(session(AdminTwoFactorStore::SESSION_KEY));
    }

    /**
     * 2つのフォーム（コードの送信・再送）に CSRF トークンが含まれる
     */
    #[Test]
    public function コードの送信と再送の2つのフォームにcsrfトークンが含まれる(): void
    {
        $this->startPending($this->createAdmin());

        $html = $this->get(route('admin.two-factor'))->getContent();

        $this->assertSame(2, substr_count($html, 'name="_token"'));
    }

    // ---- AC-16：パスワード再設定のあと・会員 ----

    /**
     * 管理者のパスワード再設定のあとのログインでも、確認コードを求められる
     */
    #[Test]
    public function パスワード再設定のあとのログインでも確認コードを求められる(): void
    {
        Mail::fake();
        $admin = $this->createAdmin('admin-reset@example.com');
        $token = Password::broker('admins')->createToken($admin);

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => 'admin-reset@example.com',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertRedirect(route('admin.login'));

        $this->postLogin('admin-reset@example.com', 'newpass123')->assertRedirect(route('admin.two-factor'));

        $this->assertGuest('admin');
        Mail::assertSent(AdminTwoFactorCodeMail::class, fn (AdminTwoFactorCodeMail $mail): bool => $mail->hasTo('admin-reset@example.com'));
    }

    /**
     * 会員のログインは今までどおりで、確認コードは求められない
     */
    #[Test]
    public function 会員のログインでは確認コードを求められない(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'member@example.com', 'password' => Hash::make('password123')]);

        $response = $this->post(route('login.store'), ['email' => 'member@example.com', 'password' => 'password123']);

        $response->assertRedirect();
        $this->assertStringNotContainsString('two-factor', (string) $response->headers->get('Location'));
        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
    }

    // ---- レビュー指摘への対応 ----

    /**
     * 再送しても失敗回数は引き継ぐ（4回間違えて、1分待って再送しても、あと1回しか試せない）。5回で無効になる扱いは同じ
     */
    #[Test]
    public function 再送しても失敗回数は引き継がれ5回で無効になる(): void
    {
        Mail::fake();
        $this->freezeTime();
        $admin = $this->createAdmin();
        $this->startPending($admin, ['attempts' => 4, 'sent_at' => now()->subMinutes(2)]);

        $this->post(route('admin.two-factor.resend'))->assertSessionHas('status', self::RESENT_MESSAGE);

        $this->assertSame(4, Admin_two_factor_code::query()->firstOrFail()->attempts);
        // 新しいコードが同じ数字になる100万分の1は除く
        if ($this->lastMailedCode() === '654321') {
            $this->markTestSkipped('新しいコードが偶然一致した');
        }
        $this->postCode('654321')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', self::LOCKED_MESSAGE);
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
        $this->assertGuest('admin');
    }

    /**
     * 再送を繰り返しても、間違えられるのは合計5回まで（1分ずつ待って、1回ずつ間違えて再送する）
     */
    #[Test]
    public function 再送を繰り返しても間違えられるのは合計5回まで(): void
    {
        Mail::fake();
        $this->freezeTime();
        $this->createAdmin();
        $this->postLogin();

        foreach ([4, 3, 2, 1] as $remaining) {
            if ($this->lastMailedCode() === '654321') {
                $this->markTestSkipped('新しいコードが偶然一致した');
            }
            $this->postCode('654321')->assertSessionHasErrors(['code' => sprintf(self::WRONG_MESSAGE, $remaining)]);
            $this->travel(61)->seconds();
            $this->post(route('admin.two-factor.resend'))->assertSessionHas('status', self::RESENT_MESSAGE);
        }

        if ($this->lastMailedCode() === '654321') {
            $this->markTestSkipped('新しいコードが偶然一致した');
        }
        $this->postCode('654321')->assertRedirect(route('admin.login'))->assertSessionHas('status', self::LOCKED_MESSAGE);
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
    }

    /**
     * 失敗回数が DB の上限に達していれば、コードが正しくても通らない（ロックは DB の値で判定する）
     */
    #[Test]
    public function 失敗回数が_d_bで上限に達していれば正しいコードでも通らない(): void
    {
        $this->startPending($this->createAdmin(), ['attempts' => 5]);

        $this->postCode(self::CODE)
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', self::LOCKED_MESSAGE);

        $this->assertGuest('admin');
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
    }

    /**
     * 失敗回数は、コードを比べる前に1つ使う。5回目でもコードが正しければログインできる（ちょうど上限の回数目まで試せる）
     */
    #[Test]
    public function 上限の回数目でも正しいコードならログインできる(): void
    {
        $admin = $this->createAdmin();
        $this->startPending($admin, ['attempts' => 4]);

        $this->postCode(self::CODE)->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    /**
     * 再送の判定は DB の sent_at で行う（読み込んだあとに他のリクエストが再送していれば、メールは送らない）
     */
    #[Test]
    public function 同時に再送されてもメールは1通だけ(): void
    {
        Mail::fake();
        $this->freezeTime();
        $admin = $this->createAdmin();
        $this->startPending($admin, ['sent_at' => now()->subMinutes(2)]);
        $service = app(AdminTwoFactorService::class);

        // 1回目の再送が置き換えた直後に、同じ「1分以上前に見えた」状態から2回目が来た状況は、置き換えの UPDATE が1件だけ更新することで防ぐ
        $this->assertSame(AdminTwoFactorSendResult::Sent, $service->resend($admin));
        $this->assertSame(AdminTwoFactorSendResult::TooSoon, $service->resend($admin));

        Mail::assertSentCount(1);
    }

    /**
     * パスワードの確認は admin ガードの validate() で行い、認証のイベントが標準どおり発行される
     */
    #[Test]
    public function パスワードの確認で認証のイベントが発行される(): void
    {
        Mail::fake();
        Event::fake([Attempting::class, Validated::class, Failed::class]);
        $this->createAdmin();

        $this->postLogin('admin@admin.com', 'wrongpass');
        Event::assertDispatched(Attempting::class);
        Event::assertDispatched(Failed::class);
        Event::assertNotDispatched(Validated::class);

        $this->postLogin();
        Event::assertDispatched(Validated::class, fn (Validated $event): bool => $event->guard === 'admin' && $event->user instanceof Admin);
    }

    /**
     * パスワードのハッシュが古い設定のときは、確認のときに標準どおり再計算して保存し直す
     */
    #[Test]
    public function パスワードの確認でハッシュが古ければ再計算される(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();
        $before = $admin->fresh()->getRawOriginal('password');
        Hash::driver('bcrypt')->setRounds(5);

        $this->postLogin()->assertRedirect(route('admin.two-factor'));

        $after = $admin->fresh()->getRawOriginal('password');
        $this->assertNotSame($before, $after);
        $this->assertTrue(Hash::check('admin1234', $after));
        $this->assertFalse(Hash::needsRehash($after));
    }

    /**
     * 確認コードの送信は、ルートごとに入力待ちの管理者あたり1分10回まで。上限を超えると入力欄の近くに日本語のメッセージが出る
     */
    #[Test]
    public function コードの送信は1分10回までで超えるとメッセージが出る(): void
    {
        $record = $this->startPending($this->createAdmin());

        foreach (range(1, 10) as $ignored) {
            $this->postCode('abc')->assertSessionHasErrors(['code' => '確認コードは6桁の数字で入力してください']);
        }

        $response = $this->postCode(self::CODE);

        $response->assertRedirect(route('admin.two-factor'));
        $this->assertMatchesRegularExpression('/\A確認コードの入力・再送の回数が上限に達しました。\d+秒後に再度お試しください。\z/u', session('errors')->first('code'));
        $this->assertGuest('admin');
        $this->assertSame(0, $record->fresh()->attempts);

        // 1分たてば、また送信できる
        $this->travel(61)->seconds();
        $this->postCode(self::CODE)->assertRedirect(route('admin.home'));
    }

    /**
     * 再送も同じく1分10回まで（再送の待ち時間の案内も回数に数える）。送信の回数とは別に数える
     */
    #[Test]
    public function 再送は1分10回までで超えるとメッセージが出る(): void
    {
        Mail::fake();
        $this->startPending($this->createAdmin());

        foreach (range(1, 10) as $ignored) {
            $this->post(route('admin.two-factor.resend'))->assertSessionHas('status', self::TOO_SOON_MESSAGE);
        }

        $this->post(route('admin.two-factor.resend'))
            ->assertRedirect(route('admin.two-factor'))
            ->assertSessionHasErrors('code');
        $this->assertMatchesRegularExpression('/上限に達しました。\d+秒後に再度お試しください。/u', session('errors')->first('code'));
        Mail::assertNothingSent();

        // 送信のほうはまだ使える
        $this->postCode('abc')->assertSessionHasErrors(['code' => '確認コードは6桁の数字で入力してください']);
    }

    /**
     * 入力待ちでない（セッションに管理者がいない）ときは、IPアドレスごとに数える。
     * 上限に達したときは、確認コードのページはすぐログインページへ移すので、最初からログインページへ戻して案内を出す
     */
    #[Test]
    public function 入力待ちでないときは_i_pアドレスごとに数える(): void
    {
        foreach (range(1, 10) as $ignored) {
            $this->postCode(self::CODE)->assertRedirect(route('admin.login'));
        }

        $response = $this->postCode(self::CODE);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', fn (string $m): bool => preg_match('/\A確認コードの入力・再送の回数が上限に達しました。\d+秒後に再度お試しください。\z/u', $m) === 1);
        // 案内はログインページに表示される
        $this->get(route('admin.login'))->assertSee('確認コードの入力・再送の回数が上限に達しました。', false);
        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
    }

    /**
     * 入力待ちでないときの再送も、上限に達したらログインページへ戻して案内を出す
     */
    #[Test]
    public function 入力待ちでないときの再送も上限に達したらログインページへ案内つきで戻す(): void
    {
        foreach (range(1, 10) as $ignored) {
            $this->post(route('admin.two-factor.resend'))->assertRedirect(route('admin.login'));
        }

        $this->post(route('admin.two-factor.resend'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', fn (string $m): bool => str_contains($m, '上限に達しました'));
    }

    /**
     * 入力待ちでなくても、JSONを期待するリクエストは上限に達したとき429で返す
     */
    #[Test]
    public function 入力待ちでないときも_jso_nのリクエストは上限で429になる(): void
    {
        foreach (range(1, 10) as $ignored) {
            $this->postJson(route('admin.two-factor.store'), ['code' => self::CODE]);
        }

        $this->postJson(route('admin.two-factor.store'), ['code' => self::CODE])->assertStatus(429)->assertJsonStructure(['message']);
    }

    /**
     * JSONを期待するリクエストは、上限に達したとき429で返す
     */
    #[Test]
    public function 上限に達したとき_jso_nのリクエストは429になる(): void
    {
        $this->startPending($this->createAdmin());

        foreach (range(1, 10) as $ignored) {
            $this->postJson(route('admin.two-factor.store'), ['code' => 'abc'])->assertUnprocessable();
        }

        $this->postJson(route('admin.two-factor.store'), ['code' => 'abc'])->assertStatus(429)->assertJsonStructure(['message']);
    }

    /**
     * 管理者のパスワード再設定が完了すると、その管理者に送ってあったコードを削除する
     */
    #[Test]
    public function 管理者のパスワード再設定が完了すると送ってあったコードは削除される(): void
    {
        $admin = $this->createAdmin();
        $this->startPending($admin);
        $token = Password::broker('admins')->createToken($admin);

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => 'admin@admin.com',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertRedirect(route('admin.login'));

        $this->assertDatabaseCount('admin_two_factor_codes', 0);
    }

    /**
     * 再設定がうまくいかなかったとき（パスワードの誤り）は、コードを削除しない
     */
    #[Test]
    public function パスワード再設定に失敗したときはコードを削除しない(): void
    {
        $admin = $this->createAdmin();
        $this->startPending($admin);
        $token = Password::broker('admins')->createToken($admin);

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => 'admin@admin.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $this->assertDatabaseCount('admin_two_factor_codes', 1);
    }

    /**
     * 入力待ちの途中で、パスワードの再設定が完了したら、その入力待ちは解かれる（届いているコードは使えず、ログインからやり直しになる）
     */
    #[Test]
    public function 入力待ちの途中でパスワードが再設定されると入力待ちが解かれる(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();
        $this->postLogin();
        $code = $this->lastMailedCode();
        $token = Password::broker('admins')->createToken($admin);

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => 'admin@admin.com',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertRedirect(route('admin.login'));

        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
        $this->postCode($code)->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
        $this->assertNull(session(AdminTwoFactorStore::SESSION_KEY));
    }

    /**
     * 再設定のしくみを通らずにパスワードが変わっても（コードの記録が残っていても）、入力待ちのセッションの派生値が合わなければ入力待ちは解かれる
     */
    #[Test]
    public function パスワードが変わっていたら入力待ちは解かれる(): void
    {
        $admin = $this->createAdmin();
        $this->startPending($admin);

        $admin->update(['password' => 'changed-password']);

        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
        $this->postCode(self::CODE)->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
        $this->assertNull(session(AdminTwoFactorStore::SESSION_KEY));
    }

    /**
     * セッションにはパスワードのハッシュそのものを入れない
     */
    #[Test]
    public function セッションにパスワードのハッシュを入れない(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();

        $this->postLogin();

        $stored = session(AdminTwoFactorStore::SESSION_KEY);
        $this->assertNotSame($admin->fresh()->password, $stored['fingerprint']);
        $this->assertStringNotContainsString($admin->fresh()->password, json_encode($stored));
    }

    // ---- 2回目のレビューへの対応 ----

    /**
     * 「ログイン」を続けて2回押しても（ダブルクリック）、500にならず、コードは1件のまま（あとのコードだけが使える）
     */
    #[Test]
    public function ログインのダブルクリックでもコードは1件のまま(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();

        $this->postLogin()->assertRedirect(route('admin.two-factor'));
        $this->postLogin()->assertRedirect(route('admin.two-factor'));

        $this->assertDatabaseCount('admin_two_factor_codes', 1);
        Mail::assertSentCount(2);
        $this->postCode($this->lastMailedCode())->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    /**
     * 再送のとき、コードがなければ（ロックされた・使われた）、新しく作らず、入力待ちを解いてログインページへ戻る（失敗回数も戻らない）
     */
    #[Test]
    public function 再送でコードがなければ入力待ちを解いてログインページへ戻る(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();
        $this->startPending($admin);
        // 別の端末の入力で、ロックされてコードが消えた
        Admin_two_factor_code::query()->delete();

        $response = $this->post(route('admin.two-factor.resend'));

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('status', '確認コードが無効になりました。もう一度ログインしてください。');
        $response->assertSessionMissing(AdminTwoFactorStore::SESSION_KEY);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
    }

    /**
     * 再送は、まず置き換えを試し、置き換えられなかったときだけコードの有無を調べる（コードがあって1分たっていなければ TooSoon のまま）
     */
    #[Test]
    public function 再送で置き換えられずコードがあれば再送待ちの案内になる(): void
    {
        Mail::fake();
        $this->freezeTime();
        $record = $this->startPending($this->createAdmin(), ['sent_at' => now()->subSeconds(10)]);

        $this->post(route('admin.two-factor.resend'))->assertSessionHas('status', self::TOO_SOON_MESSAGE);

        $this->assertNotNull($record->fresh());
        Mail::assertNothingSent();
    }

    /**
     * 同じ管理者の確認コードの間違いは、ログインし直しても合わせて数え、上限（20回）に達した時点でコードと入力待ちを消す
     */
    #[Test]
    public function 確認コードの間違いは1時間に合計20回までで超えるとコードを無効にする(): void
    {
        Mail::fake();
        $this->freezeTime();
        $this->createAdmin();
        $this->assertSame(20, config('auth.admin_two_factor.miss_limit'));
        $this->assertSame(3600, config('auth.admin_two_factor.miss_window_seconds'));

        // 4回間違えてはログインし直す、を繰り返す（1回のコードでは5回目の間違いで無効になるので、4回までにする）
        foreach (range(1, 4) as $cycle) {
            $this->postLogin()->assertRedirect(route('admin.two-factor'));
            foreach (range(1, 4) as $ignored) {
                $this->postCode('654321')->assertSessionHasErrors('code');
            }
            $this->travel(61)->seconds();
        }

        // ここまでで16回。5回目の途中の、20回目の間違いで、コードを無効にしてログインページへ戻る
        $this->postLogin()->assertRedirect(route('admin.two-factor'));
        foreach (range(1, 3) as $ignored) {
            $this->postCode('654321')->assertSessionHasErrors('code');
        }
        $response = $this->postCode('654321');

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('status', fn (string $message): bool => preg_match('/\A確認コードの間違いが続いたため、しばらくログインできません。\d+分後にもう一度お試しください。\z/u', $message) === 1);
        $response->assertSessionMissing(AdminTwoFactorStore::SESSION_KEY);
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
        $this->assertGuest('admin');
        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'));
    }

    /**
     * 上限に達したあとは、残り時間を分で切り上げて案内し、正しいパスワードでもコードを送らない。時間がたてば、また送れる
     */
    #[Test]
    public function 間違いが続いたあとは正しいパスワードでもコードを送らず時間がたてば送る(): void
    {
        config(['auth.admin_two_factor.miss_limit' => 3]);
        Mail::fake();
        $this->freezeTime();
        $this->createAdmin();
        $this->startPending(Admin::query()->firstOrFail());
        foreach (range(1, 2) as $ignored) {
            $this->postCode('654321')->assertSessionHasErrors('code');
        }
        $this->postCode('654321')->assertSessionHas('status', fn (string $m): bool => str_contains($m, '60分後'));

        // 正しいパスワードでも、コードを送らず、同じ案内（残り時間つき）を出す
        $this->travel(30)->minutes();
        $response = $this->postLogin();
        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('status', '確認コードの間違いが続いたため、しばらくログインできません。30分後にもう一度お試しください。');
        $response->assertSessionHasInput('email', 'admin@admin.com');
        $this->assertGuest('admin');
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
        Mail::assertNothingSent();

        // 残り1秒でも、「1分後」と切り上げる
        $this->travel(29)->minutes();
        $this->travel(59)->seconds();
        $this->postLogin()->assertSessionHas('status', '確認コードの間違いが続いたため、しばらくログインできません。1分後にもう一度お試しください。');

        // パスワードが間違いなら、従来どおりのエラー
        $this->postLogin('admin@admin.com', 'wrongpass')
            ->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);

        // 1時間たてば、また送れる
        $this->travel(2)->seconds();
        $this->postLogin()->assertRedirect(route('admin.two-factor'));
        Mail::assertSentCount(1);
    }

    /**
     * 管理者ごとに数える（ほかの管理者の間違いは合算しない）
     */
    #[Test]
    public function 確認コードの間違いはほかの管理者とは合算しない(): void
    {
        config(['auth.admin_two_factor.miss_limit' => 3]);
        Mail::fake();
        $a = $this->createAdmin('a@example.com');
        $this->createAdmin('b@example.com');

        $this->startPending($a);
        foreach (range(1, 3) as $ignored) {
            $this->postCode('654321');
        }
        $this->assertDatabaseCount('admin_two_factor_codes', 0);

        // b は、a がロックされていても、ログインできる
        $this->postLogin('b@example.com')->assertRedirect(route('admin.two-factor'));
        Mail::assertSent(AdminTwoFactorCodeMail::class, fn (AdminTwoFactorCodeMail $mail): bool => $mail->hasTo('b@example.com'));
    }

    /**
     * 期限切れ・形式エラーは、1時間の間違いの回数に数えない
     */
    #[Test]
    public function 期限切れと形式エラーは1時間の間違いに数えない(): void
    {
        config(['auth.admin_two_factor.miss_limit' => 2]);
        $this->freezeTime();
        $admin = $this->createAdmin();
        $this->startPending($admin, ['expires_at' => now()->subSecond()]);

        foreach (range(1, 3) as $ignored) {
            $this->postCode('654321')->assertSessionHasErrors(['code' => self::EXPIRED_MESSAGE]);
            $this->postCode('abc')->assertSessionHasErrors(['code' => '確認コードは6桁の数字で入力してください']);
        }

        // 期限内の間違い1回目はまだ上限に達しない
        Admin_two_factor_code::query()->update(['expires_at' => now()->addMinutes(5)]);
        $this->postCode('654321')->assertSessionHasErrors(['code' => sprintf(self::WRONG_MESSAGE, 4)]);
    }

    /**
     * 確認コードが正しく入力できても、1時間の間違いの回数は消さない（間違いを重ねたあとに成功しても、続けて間違えれば上限に達する）
     */
    #[Test]
    public function 成功しても1時間の間違いの回数は消さない(): void
    {
        config(['auth.admin_two_factor.miss_limit' => 3]);
        $this->freezeTime();
        $admin = $this->createAdmin();

        $this->startPending($admin);
        $this->postCode('654321')->assertSessionHasErrors('code');
        $this->postCode('654321')->assertSessionHasErrors('code');
        $this->postCode(self::CODE)->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($admin, 'admin');

        $this->post(route('admin.logout'));
        $this->startPending($admin);
        $this->postCode('654321')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', fn (string $m): bool => str_contains($m, 'しばらくログインできません'));
    }

    /**
     * 間違いの回数のキーは `admin-two-factor-misses|{管理者のID}` のまま（運用で RateLimiter::clear に使う）。上限・戻すときの動き
     */
    #[Test]
    public function 間違いの回数のストアはキーを変えず上限と消去が動く(): void
    {
        config(['auth.admin_two_factor.miss_limit' => 2]);
        $admin = $this->createAdmin();
        $store = app(AdminTwoFactorMissStore::class);
        $key = 'admin-two-factor-misses|'.$admin->id;

        $this->assertFalse($store->hit($admin));
        $this->assertSame(1, RateLimiter::attempts($key));
        $this->assertFalse($store->reachedLimit($admin));
        $this->assertTrue($store->hit($admin));
        $this->assertTrue($store->reachedLimit($admin));
        $this->assertGreaterThan(0, $store->availableIn($admin));

        RateLimiter::clear($key);
        $this->assertFalse($store->reachedLimit($admin));

        $store->hit($admin);
        $store->clear($admin);
        $this->assertSame(0, RateLimiter::attempts($key));
    }

    /**
     * 確認コードの間違いが上限に達して止められていても、パスワードを再設定すれば、新しいパスワードでログインし直せてコードも送られる
     */
    #[Test]
    public function パスワードを再設定すると確認コードの間違いの回数も戻りログインし直せる(): void
    {
        config(['auth.admin_two_factor.miss_limit' => 3]);
        Mail::fake();
        $admin = $this->createAdmin();
        $this->startPending($admin);
        foreach (range(1, 3) as $ignored) {
            $this->postCode('654321');
        }

        // 上限に達していて、正しいパスワードでもコードを送らない
        $this->postLogin()->assertSessionHas('status', fn (string $m): bool => str_contains($m, 'しばらくログインできません'));
        Mail::assertNothingSent();

        $token = Password::broker('admins')->createToken($admin);
        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => 'admin@admin.com',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertRedirect(route('admin.login'));

        $this->postLogin('admin@admin.com', 'newpass123')->assertRedirect(route('admin.two-factor'));
        Mail::assertSent(AdminTwoFactorCodeMail::class, 1);
        $this->postCode($this->lastMailedCode())->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    /**
     * 再設定に失敗したときは、確認コードの間違いの回数を戻さない
     */
    #[Test]
    public function パスワード再設定に失敗したときは確認コードの間違いの回数を戻さない(): void
    {
        config(['auth.admin_two_factor.miss_limit' => 3]);
        Mail::fake();
        $admin = $this->createAdmin();
        $this->startPending($admin);
        foreach (range(1, 3) as $ignored) {
            $this->postCode('654321');
        }
        $token = Password::broker('admins')->createToken($admin);

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => 'admin@admin.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $this->postLogin()->assertSessionHas('status', fn (string $m): bool => str_contains($m, 'しばらくログインできません'));
        Mail::assertNothingSent();
    }

    /**
     * 再送の案内の待ち時間は、設定の秒数から分に直して（切り上げ）出す
     */
    #[Test]
    public function 再送の案内の待ち時間は設定値から出る(): void
    {
        Mail::fake();
        $this->freezeTime();
        $admin = $this->createAdmin();
        $this->startPending($admin, ['sent_at' => now()]);

        foreach ([60 => 1, 90 => 2, 120 => 2, 300 => 5, 10 => 1] as $seconds => $minutes) {
            config(['auth.admin_two_factor.resend_seconds' => $seconds]);

            $this->assertSame($minutes, app(AdminTwoFactorService::class)->resendMinutes());
            $this->post(route('admin.two-factor.resend'))
                ->assertSessionHas('status', "確認コードの再送は、前回の送信から{$minutes}分たってからお試しください。");
        }
    }

    /**
     * 確認コードの入力ページ・送信・再送は、入力待ちでなくて早期に戻るときも、end のデバッグログを出す
     */
    #[Test]
    public function 入力待ちでなく早期に戻るときもendのログを出す(): void
    {
        Log::spy();

        $this->get(route('admin.two-factor'));
        $this->postCode(self::CODE);
        $this->post(route('admin.two-factor.resend'));

        Log::shouldHaveReceived('debug')->with('AdminTwoFactorController@create end (no pending)')->once();
        Log::shouldHaveReceived('debug')->with('AdminTwoFactorController@store end (no pending)')->once();
        Log::shouldHaveReceived('debug')->with('AdminTwoFactorController@resend end (no pending)')->once();
    }

    private const string PASSWORD_CHANGED_MESSAGE = 'パスワードが変更されたため、確認コードは使えなくなりました。新しいパスワードでもう一度ログインしてください。';

    /**
     * 入力待ちのあいだにパスワードが変わっていたため入力待ちを解くときは、確認コードのページ・送信・再送のどれでも、ログインページに案内を出す
     */
    #[Test]
    public function パスワードが変わっていたため入力待ちを解くときは案内を出す(): void
    {
        Mail::fake();
        $this->freezeTime();
        $admin = $this->createAdmin();

        foreach (['get', 'store', 'resend'] as $route) {
            $this->flushSession();
            $this->startPending($admin, ['sent_at' => now()->subMinutes(2)]);
            $pending = $this->pendingSession($admin);
            $admin->update(['password' => 'changed-'.$route]);
            $this->withSession($pending);

            $response = match ($route) {
                'get' => $this->get(route('admin.two-factor')),
                'store' => $this->postCode(self::CODE),
                'resend' => $this->post(route('admin.two-factor.resend')),
            };

            $response->assertRedirect(route('admin.login'));
            $response->assertSessionHas('status', self::PASSWORD_CHANGED_MESSAGE);
            $response->assertSessionMissing(AdminTwoFactorStore::SESSION_KEY);
            $this->assertGuest('admin');
            Admin_two_factor_code::query()->delete();
        }

        Mail::assertNothingSent();
    }

    /**
     * 案内は、リダイレクト先のログインページに既存の案内の枠で表示される
     */
    #[Test]
    public function パスワードが変わった案内はログインページに表示される(): void
    {
        $admin = $this->createAdmin();
        $this->startPending($admin);
        $pending = $this->pendingSession($admin);
        $admin->update(['password' => 'changed-password']);
        $this->withSession($pending);

        $html = $this->followingRedirects()->get(route('admin.two-factor'))->getContent();

        $this->assertStringContainsString('role="status">'.self::PASSWORD_CHANGED_MESSAGE, $html);
        $this->assertStringContainsString('<title>管理者用ログイン | Shioya Farm</title>', $html);
    }

    /**
     * 入力待ちの途中で、管理者のパスワードの再設定が完了したときも、案内を出す
     */
    #[Test]
    public function パスワード再設定のあと入力待ちを解くときも案内を出す(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();
        $this->postLogin();
        $token = Password::broker('admins')->createToken($admin);
        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => 'admin@admin.com',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertRedirect(route('admin.login'));

        $this->postCode($this->lastMailedCode())
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', self::PASSWORD_CHANGED_MESSAGE);
    }

    /**
     * 入力待ちがもともとないとき（直接開いた・オフ・管理者がいない・セッションの値が不正）は、案内を出さない
     */
    #[Test]
    public function 入力待ちがもともとないときは案内を出さない(): void
    {
        $admin = $this->createAdmin();

        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'))->assertSessionMissing('status');
        $this->postCode(self::CODE)->assertRedirect(route('admin.login'))->assertSessionMissing('status');
        $this->post(route('admin.two-factor.resend'))->assertRedirect(route('admin.login'))->assertSessionMissing('status');

        // 管理者がいなくなっていた
        $this->withSession([AdminTwoFactorStore::SESSION_KEY => ['id' => $admin->id + 100, 'fingerprint' => 'x']]);
        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'))->assertSessionMissing('status');

        // 二段階認証がオフ
        config(['auth.admin_two_factor.enabled' => false]);
        $this->withSession($this->pendingSession($admin));
        $this->get(route('admin.two-factor'))->assertRedirect(route('admin.login'))->assertSessionMissing('status');
    }
}
