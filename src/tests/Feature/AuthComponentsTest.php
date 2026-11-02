<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 認証画面の部品（auth-guide・auth-submit）のテスト
 */
class AuthComponentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * auth-guide は案内文と枠線のリンクボタンを出す
     */
    #[Test]
    public function auth_guideは案内文とリンクボタンを出す(): void
    {
        $html = Blade::render('<x-auth-guide href="/login" label="ログイン">案内です。<br class="lg:hidden">こちら</x-auth-guide>');

        $this->assertStringContainsString('<p class="leading-[1.6]">案内です。<br class="lg:hidden">こちら</p>', $html);
        $this->assertStringContainsString('href="/login">ログイン</a>', $html);
        $this->assertStringContainsString('h-11', $html);
        $this->assertStringContainsString('lg:h-[34px] lg:w-[130px]', $html);
        $this->assertStringContainsString('border-primary-dark', $html);
    }

    /**
     * auth-submit は塗りの送信ボタンを出し、画面ごとのクラスを足せる
     */
    #[Test]
    public function auth_submitは送信ボタンを出しクラスを足せる(): void
    {
        $html = Blade::render('<x-auth-submit class="lg:mt-6">ログイン</x-auth-submit>');

        $this->assertStringContainsString('type="submit">ログイン</button>', $html);
        $this->assertStringContainsString('h-11 w-full', $html);
        $this->assertStringContainsString('bg-primary', $html);
        $this->assertStringContainsString('lg:h-[34px]', $html);
        $this->assertStringContainsString('lg:mt-6', $html);
        $this->assertStringContainsString('>ログイン</button>', $html);
    }

    /**
     * 認証の4画面は、部品で出したパネルの案内と送信ボタンを持つ
     */
    #[Test]
    public function 認証画面は部品の案内と送信ボタンを持つ(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('まだ会員登録がお済みでない方は、<br class="lg:hidden">こちらからご登録いただけます。', false)
            ->assertSee('href="'.route('register').'">会員登録</a>', false)
            ->assertSee('lg:h-[34px] lg:mt-6" type="submit">ログイン</button>', false);

        $this->get(route('register'))->assertOk()
            ->assertSee('すでに会員登録がお済みの方は、<br class="lg:hidden">こちらからログインしてください。', false)
            ->assertSee('href="'.route('login').'">ログイン</a>', false)
            ->assertSee('>登録する</button>', false);

        $this->get(route('password.request'))->assertOk()
            ->assertSee('パスワードを思い出した方は、<br class="lg:hidden">こちらからログインしてください。', false)
            ->assertSee('>再設定メールを送信</button>', false);

        $this->get(route('password.reset', ['token' => 'tok', 'email' => 'a@example.com']))->assertOk()
            ->assertSee('パスワードを思い出した方は、<br class="lg:hidden">こちらからログインしてください。', false)
            ->assertSee('>パスワードを再設定する</button>', false);
    }
}
