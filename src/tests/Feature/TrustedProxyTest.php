<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 信頼するプロキシ（TRUSTED_PROXIES → config('trustedproxy.proxies')）のテスト
 *
 * リバースプロキシ（Caddy）の裏でも、回数制限が数える $request->ip() が本当のクライアントのIPアドレスになることを確かめる。
 */
class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    /** プロキシ（Caddy のコンテナ）のIPアドレス */
    private const string PROXY_IP = '172.18.0.2';

    /** 会員登録の回数制限のメッセージの一部（lang/ja/auth.php の register_throttle） */
    private const string THROTTLE_MESSAGE = '登録の操作が続いたため';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/client', fn (Request $request): array => ['ip' => $request->ip(), 'secure' => $request->isSecure(), 'host' => $request->getHost()]);
    }

    /**
     * プロキシ経由のリクエストを送る
     *
     * @param  array<string, string>  $headers
     */
    private function viaProxy(array $headers = []): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::PROXY_IP])->withHeaders($headers);
    }

    /**
     * 既定（未設定）では、X-Forwarded-For は信頼せず、直接つながってきた相手のIPアドレスになる（今までと同じ動き）
     */
    #[Test]
    public function 未設定ではforwardedヘッダーを信頼しない(): void
    {
        $this->assertNull(config('trustedproxy.proxies'));

        $this->viaProxy(['X-Forwarded-For' => '203.0.113.5', 'X-Forwarded-Proto' => 'https'])
            ->getJson('/_test/client')
            ->assertOk()
            ->assertJson(['ip' => self::PROXY_IP, 'secure' => false]);
    }

    /**
     * プロキシを指定すると（CIDR）、X-Forwarded-For の本当のクライアントのIPアドレスとhttpsになる
     */
    #[Test]
    public function cidrで指定したプロキシ経由ではクライアントのipになる(): void
    {
        config(['trustedproxy.proxies' => '172.16.0.0/12']);

        $this->viaProxy(['X-Forwarded-For' => '203.0.113.5', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'demo.example.org'])
            ->getJson('/_test/client')
            ->assertOk()
            ->assertJson(['ip' => '203.0.113.5', 'secure' => true, 'host' => 'demo.example.org']);
    }

    /**
     * カンマ区切りで複数指定できる
     */
    #[Test]
    public function カンマ区切りで複数のプロキシを指定できる(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.1, '.self::PROXY_IP]);

        $this->viaProxy(['X-Forwarded-For' => '203.0.113.6'])->getJson('/_test/client')->assertJson(['ip' => '203.0.113.6']);
    }

    /**
     * 「*」は、直接つながってきた相手をプロキシとして信頼する
     */
    #[Test]
    public function アスタリスクは直接の接続元を信頼する(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->viaProxy(['X-Forwarded-For' => '203.0.113.7'])->getJson('/_test/client')->assertJson(['ip' => '203.0.113.7']);
    }

    /**
     * 信頼するプロキシ以外から来たリクエストの X-Forwarded-For は無視する（IPアドレスを偽って回数制限を逃れられない）
     */
    #[Test]
    public function 信頼しない接続元のforwardedヘッダーは無視する(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/8']);

        $this->viaProxy(['X-Forwarded-For' => '203.0.113.8'])->getJson('/_test/client')->assertJson(['ip' => self::PROXY_IP]);
    }

    /**
     * 登録の送信を、X-Forwarded-For のIPアドレスで1回送り、回数制限のメッセージが出たかを返す
     */
    private function registerThrottled(string $clientIp): bool
    {
        $this->viaProxy(['X-Forwarded-For' => $clientIp])->post(route('register.store'), []);
        $message = session('errors')?->first('email');

        return is_string($message) && str_contains($message, self::THROTTLE_MESSAGE);
    }

    /**
     * 回数制限はプロキシ経由でもクライアントごとに数える（会員登録は同じIPアドレスから1分5回まで）
     */
    #[Test]
    public function 回数制限はプロキシ経由でもクライアントごとに数える(): void
    {
        Mail::fake();
        config(['trustedproxy.proxies' => '172.16.0.0/12', 'fortify.register_per_minute' => 5]);

        // クライアントAは5回目まで通り、6回目で制限される
        for ($i = 1; $i <= 5; $i++) {
            $this->assertFalse($this->registerThrottled('203.0.113.10'), $i.'回目');
        }
        $this->assertTrue($this->registerThrottled('203.0.113.10'));

        // 同じプロキシ経由の別のクライアントBは、数え始めからで制限されない
        $this->assertFalse($this->registerThrottled('203.0.113.11'));
    }

    /**
     * プロキシを信頼しない設定のままだと、全員が同じ接続元（プロキシ）として数えられ、別のクライアントも巻き込まれる（設定の必要性を示す）
     */
    #[Test]
    public function 未設定だとプロキシ経由のクライアントをまとめて数える(): void
    {
        Mail::fake();
        config(['fortify.register_per_minute' => 5]);

        for ($i = 1; $i <= 5; $i++) {
            $this->assertFalse($this->registerThrottled('203.0.113.'.$i), $i.'回目');
        }
        $this->assertTrue($this->registerThrottled('203.0.113.99'));
    }
}
