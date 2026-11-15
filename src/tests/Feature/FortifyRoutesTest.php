<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fortifyのルートのうち、使わない機能（パスワードの確認）は取り除き（404）、使うものは残していることのテスト
 */
class FortifyRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function unusedRoutes(): array
    {
        return [
            'パスワード確認の画面（GET）' => ['GET', '/user/confirm-password'],
            'パスワード確認の送信（POST）' => ['POST', '/user/confirm-password'],
            'パスワード確認の状態（GET）' => ['GET', '/user/confirmed-password-status'],
        ];
    }

    /**
     * 未ログインでも、ログイン中でも、404になる（ログインページへ移動したり500になったりしない）
     */
    #[Test]
    #[DataProvider('unusedRoutes')]
    public function 使わないパスワード確認のルートは未ログインでもログイン中でも404になる(string $method, string $uri): void
    {
        $this->call($method, $uri)->assertNotFound();

        $this->actingAs(User::factory()->create())->call($method, $uri, ['password' => 'password123'])->assertNotFound();
    }

    /**
     * ルートそのものが取り除かれている（Fortifyのルートは標準どおり登録し、そのあとで外している）
     */
    #[Test]
    public function パスワード確認のルートは登録されていない(): void
    {
        foreach (['password.confirm', 'password.confirm.store', 'password.confirmation'] as $name) {
            $this->assertFalse(Route::has($name), $name);
        }

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $this->assertNotContains($route->uri(), ['user/confirm-password', 'user/confirmed-password-status']);
        }
    }

    /**
     * 404は、存在しないURLと同じアプリの404ページ（ログイン中はヘッダーがログイン中の表示）
     */
    #[Test]
    public function パスワード確認のページはアプリの404ページを表示する(): void
    {
        $this->get('/user/confirm-password')->assertNotFound()->assertSee('ページが見つかりませんでした')->assertDontSee('マイページ');

        $this->actingAs(User::factory()->create())->get('/user/confirm-password')
            ->assertNotFound()->assertSee('ページが見つかりませんでした')->assertSee('マイページ');
    }

    /**
     * 使っているFortifyのルート（ログイン・ログアウト・会員登録・パスワード再設定）は、これまでの名前・URLのまま残っている
     */
    #[Test]
    public function 使っているfortifyのルートは残っている(): void
    {
        $expected = [
            'login' => '/login',
            'login.store' => '/login',
            'logout' => '/logout',
            'register' => '/register',
            'register.store' => '/register',
            'password.request' => '/forgot-password',
            'password.email' => '/forgot-password',
            'password.reset' => '/reset-password/{token}',
            'password.update' => '/reset-password',
        ];

        foreach ($expected as $name => $uri) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertSame($uri, '/'.$route->uri(), $name);
        }
        $this->assertContains('guest:web', Route::getRoutes()->getByName('login')->gatherMiddleware());
        $this->assertContains('auth:web', Route::getRoutes()->getByName('logout')->gatherMiddleware());
        $this->assertContains('throttle:login', Route::getRoutes()->getByName('login.store')->gatherMiddleware());
    }
}
