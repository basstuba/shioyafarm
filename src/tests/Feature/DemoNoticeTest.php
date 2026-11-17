<?php

namespace Tests\Feature;

use App\View\Components\DemoNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 公開デモの案内（DEMO_NOTICE。components/demo-notice）のテスト
 *
 * 公開側のレイアウト（app・auth・thanks）と管理画面のレイアウト（admin/top・admin/app・管理者用ログインの auth）で、オンのときだけ出る。
 */
class DemoNoticeTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /** 案内の文言（ユーザー承認済み） */
    private const string MESSAGE = 'ポートフォリオ用のデモサイトです。実在の氏名・住所・メールアドレスなどは入力しないでください。決済はテストモードで、メールは送信されません。データは毎日午前4時に初期化されます。';

    /**
     * 設定の既定はオフ（開発・E2E・テストでは出さない）
     */
    #[Test]
    public function 既定ではオフ(): void
    {
        $this->assertFalse(config('demo.notice'));
    }

    /**
     * 文言は承認された文のとおり
     */
    #[Test]
    public function 文言は決められたとおり(): void
    {
        $this->assertSame(self::MESSAGE, DemoNotice::MESSAGE);
    }

    /**
     * オフのときは、公開側のどのレイアウトにも出ない
     */
    #[Test]
    public function オフのときは公開側に出ない(): void
    {
        config(['demo.notice' => false]);

        foreach ([route('top.index'), route('login'), route('register'), route('info.shipping-fee'), '/not-exist-page'] as $url) {
            $this->get($url)->assertDontSee('デモサイトです')->assertDontSee('role="note"', false);
        }
        $this->assertStringNotContainsString('デモサイトです', Blade::render("@extends('layouts.thanks') @section('content')本文@endsection"));
    }

    /**
     * オフのときは、管理画面にも出ない
     */
    #[Test]
    public function オフのときは管理画面に出ない(): void
    {
        config(['demo.notice' => false]);

        $this->get(route('admin.login'))->assertDontSee('デモサイトです');
        $this->loginAsAdmin();
        $this->get(route('admin.home'))->assertOk()->assertDontSee('デモサイトです');
        $this->get(route('admin.purchase.index'))->assertOk()->assertDontSee('デモサイトです');
    }

    /**
     * オンのときは、公開側のトップ・ログイン・会員登録・静的ページ・404に文言が出る
     */
    #[Test]
    public function オンのときは公開側のすべてのページに出る(): void
    {
        config(['demo.notice' => true]);

        foreach ([route('top.index'), route('login'), route('register'), route('info.shipping-fee'), route('info.terms-of-service'), '/not-exist-page'] as $url) {
            $this->get($url)->assertSee(self::MESSAGE, false)->assertSee('role="note"', false);
        }
    }

    /**
     * オンのとき、サンクスページ（layouts/thanks）にも出る
     */
    #[Test]
    public function オンのときはサンクスページのレイアウトにも出る(): void
    {
        config(['demo.notice' => true]);

        $html = Blade::render("@extends('layouts.thanks') @section('content')本文@endsection");

        $this->assertStringContainsString(self::MESSAGE, $html);
        $this->assertStringContainsString('本文', $html);
    }

    /**
     * オンのとき、公開側のレイアウト（app）は、案内を固定ヘッダーの真下に置き、本文を案内の分だけ引き上げる（本文の pt-header の余白に収める）
     */
    #[Test]
    public function オンのとき公開側は固定ヘッダーの下に置く(): void
    {
        config(['demo.notice' => true]);

        $html = $this->get(route('top.index'))->getContent();

        $this->assertStringContainsString('class="border-b border-primary bg-peach font-maru text-ink relative mt-header"', (string) $html);
        $this->assertStringContainsString('<div class="-mt-header">', (string) $html);
        // 案内は本文より前、ヘッダーより後
        $this->assertLessThan(strpos((string) $html, '-mt-header'), strpos((string) $html, self::MESSAGE));
    }

    /**
     * オフのとき、公開側のレイアウトの本文は引き上げない
     */
    #[Test]
    public function オフのとき本文は引き上げない(): void
    {
        config(['demo.notice' => false]);

        $this->get(route('top.index'))->assertDontSee('<div class="-mt-header">', false);
    }

    /**
     * 公開側の案内は既存の配色（桃色の地・黒の文字）、折り返しても全文が出る（はみ出さない指定）
     */
    #[Test]
    public function 公開側の案内の配色と折り返し(): void
    {
        config(['demo.notice' => true]);

        $html = (string) $this->get(route('top.index'))->getContent();

        $this->assertStringContainsString('bg-peach', $html);
        $this->assertStringContainsString('text-ink', $html);
        $this->assertStringContainsString('wrap-break-word', $html);
        $this->assertStringContainsString('text-sm', $html);
        $this->assertStringNotContainsString('bg-admin-head font-sans text-white" role="note"', $html);
    }

    /**
     * オンのとき、管理画面（トップ・サイドメニュー付きのページ）の上部に、白・黒・グレーの色調で出る
     */
    #[Test]
    public function オンのときは管理画面のすべてのページに出る(): void
    {
        config(['demo.notice' => true]);
        $this->loginAsAdmin();

        foreach ([route('admin.home'), route('admin.purchase.index'), route('admin.materials.index')] as $url) {
            $html = (string) $this->get($url)->assertOk()->assertSee(self::MESSAGE, false)->getContent();

            $this->assertStringContainsString('bg-admin-head font-sans text-white', $html);
            // 公開側の橙系の配色は使わない
            $this->assertStringNotContainsString('bg-peach', $html);
            $this->assertStringNotContainsString('border-primary', $html);
            // ヘッダーより前（ページの最上部）
            $this->assertLessThan(strpos($html, 'しおや農園直販サイト管理画面'), strpos($html, self::MESSAGE));
        }
    }

    /**
     * オンのとき、管理者用ログイン（auth のレイアウト）は管理画面の色調、会員のログインは公開側の色調
     */
    #[Test]
    public function オンのときログイン画面は管理者と会員で色調が違う(): void
    {
        config(['demo.notice' => true]);

        $admin = (string) $this->get(route('admin.login'))->assertSee(self::MESSAGE, false)->getContent();
        $this->assertStringContainsString('bg-admin-head font-sans text-white', $admin);
        $this->assertStringNotContainsString('bg-peach', $admin);

        $member = (string) $this->get(route('login'))->assertSee(self::MESSAGE, false)->getContent();
        $this->assertStringContainsString('bg-peach', $member);
        $this->assertStringNotContainsString('bg-admin-head', $member);
    }

    /**
     * 環境変数 DEMO_NOTICE の読み取り（config/demo.php を実際に読み込む）：true・1・on・yes だけオン、false・未設定・空・不正な値はオフ
     */
    #[Test]
    public function 環境変数の値の解釈(): void
    {
        foreach (['true', '1', 'on', 'yes', 'TRUE'] as $on) {
            $this->assertTrue($this->noticeConfigWith($on), "DEMO_NOTICE={$on}");
        }
        foreach (['false', '0', 'off', 'no', '', 'abc', null] as $off) {
            $this->assertFalse($this->noticeConfigWith($off), 'DEMO_NOTICE='.var_export($off, true));
        }
    }

    /**
     * 環境変数 DEMO_NOTICE を設定（null なら未設定）して config/demo.php を読み込み、notice の値を返す。環境変数は元に戻す
     */
    private function noticeConfigWith(?string $value): bool
    {
        $name = 'DEMO_NOTICE';
        $beforeGetenv = getenv($name);
        $beforeEnv = $_ENV[$name] ?? null;
        $beforeServer = $_SERVER[$name] ?? null;

        try {
            if ($value === null) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);
            } else {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }

            /** @var array{notice: bool} $config */
            $config = require config_path('demo.php');

            return $config['notice'];
        } finally {
            if ($beforeGetenv === false) {
                putenv($name);
            } else {
                putenv("{$name}={$beforeGetenv}");
            }
            if ($beforeEnv === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $beforeEnv;
            }
            if ($beforeServer === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $beforeServer;
            }
        }
    }
}
