<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleFortifyRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fortifyのルート（パスワード再設定の送信・会員登録の送信）の回数制限のテスト
 *
 * 会員登録のメッセージ・入力の残り方は RegistrationMailTest、管理者の再設定の制限は AdminPasswordResetTest でも確かめる。
 */
class ThrottleFortifyRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 回数制限の対象は、パスワード再設定の送信の2つと会員登録の送信だけ
     */
    #[Test]
    public function 対象のルートとlimiterの対応(): void
    {
        $this->assertSame([
            'password.email' => 'password-reset',
            'password.update' => 'password-reset',
            'register.store' => 'register',
        ], ThrottleFortifyRoutes::LIMITERS);
    }

    /**
     * 会員のパスワード再設定の送信は、同じIPアドレスから1分に5回まで。6回目は、メールアドレス欄の近くに日本語のメッセージを出す
     */
    #[Test]
    public function 会員の再設定メールの送信は6回目で制限される(): void
    {
        Mail::fake();

        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('password.email'), ['email' => 'nobody'.$i.'@example.com'])->assertSessionHasNoErrors();
        }

        $this->post(route('password.email'), ['email' => 'nobody6@example.com'])->assertSessionHasErrors('email');
    }

    /**
     * 会員登録の送信と、パスワード再設定の送信は、別々に数える
     */
    #[Test]
    public function 登録と再設定は別々に数える(): void
    {
        Mail::fake();

        for ($i = 1; $i <= 6; $i++) {
            $this->post(route('register.store'), []);
        }

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertSessionHasNoErrors();
    }

    /**
     * 対象ではないルートは、何回送っても制限されない
     */
    #[Test]
    public function 対象ではないルートは制限されない(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->get(route('register'))->assertOk();
            $this->get(route('login'))->assertOk();
            $this->get(route('password.request'))->assertOk();
        }
    }
}
