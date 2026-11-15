<?php

namespace Tests\Unit;

use App\View\Components\AuthInput;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AuthInput（認証ページ・管理画面の入力欄）のクラス・値の組み立てのテスト
 *
 * 描画は tests/Feature/AuthComponentsTest（Blade::render）で確認している。ここでは分岐だけを確認する。
 */
class AuthInputTest extends TestCase
{
    /**
     * 直前の入力（フラッシュされた old）を設定する
     *
     * @param  array<string, mixed>  $input
     */
    private function flashOld(array $input): void
    {
        request()->setLaravelSession(app('session.store'));
        session()->put('_old_input', $input);
    }

    /**
     * variant が neutral のときだけグレー系のクラスになる（それ以外は橙系）
     */
    #[Test]
    public function variantで配色が変わる(): void
    {
        $default = new AuthInput('email', 'メールアドレス');
        $unknown = new AuthInput('email', 'メールアドレス', variant: 'other');
        $neutral = new AuthInput('email', 'メールアドレス', variant: 'neutral');

        $this->assertSame('border-primary-dark', $default->borderClass());
        $this->assertSame('focus-visible:outline-primary-dark', $default->outlineClass());
        $this->assertSame('text-primary-text', $default->toggleClass());
        $this->assertSame($default->borderClass(), $unknown->borderClass());
        $this->assertSame('border-line-strong', $neutral->borderClass());
        $this->assertSame('focus-visible:outline-ink', $neutral->outlineClass());
        $this->assertSame('text-muted', $neutral->toggleClass());
    }

    /**
     * type が password のときだけパスワードの欄になる
     */
    #[Test]
    public function パスワードの欄かどうか(): void
    {
        $this->assertTrue((new AuthInput('password', 'パスワード', type: 'password'))->isPassword);
        $this->assertFalse((new AuthInput('email', 'メールアドレス', type: 'email'))->isPassword);
    }

    /**
     * aria-describedby の id は、補足の id とエラー文の id をスペースでつなぐ
     */
    #[Test]
    public function describedbyの組み立て(): void
    {
        $with = new AuthInput('password', 'パスワード', describedby: 'password-hint');
        $without = new AuthInput('email', 'メールアドレス');

        $this->assertSame('password-hint password-error', $with->describedIds(true));
        $this->assertSame('password-hint', $with->describedIds(false));
        $this->assertSame('email-error', $without->describedIds(true));
        $this->assertSame('', $without->describedIds(false));
    }

    /**
     * 入力欄の値は old() を優先し、なければ渡された値を使う
     */
    #[Test]
    public function 入力欄の値はoldを優先する(): void
    {
        $input = new AuthInput('city', 'ご住所', value: '札幌市');
        $this->assertSame('札幌市', $input->oldValue());

        $this->flashOld(['city' => '旭川市']);
        $this->assertSame('旭川市', $input->oldValue());

        $this->flashOld(['city' => ['改変']]);
        $this->assertSame('', $input->oldValue());
    }
}
