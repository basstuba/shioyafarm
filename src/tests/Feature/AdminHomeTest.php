<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminHomeController;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 管理画面トップページ（admin.home）のテスト
 */
class AdminHomeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者としてトップページを開く
     */
    private function openHome(): TestResponse
    {
        $admin = Admin::factory()->create();

        return $this->actingAs($admin, 'admin')->get(route('admin.home'));
    }

    /**
     * ページの本文（HTML）を返す
     */
    private function homeHtml(): string
    {
        return $this->openHome()->assertOk()->getContent();
    }

    /**
     * ルートは AdminHomeController::index が受け、管理者の認証が必要
     */
    #[Test]
    public function トップページは_admin_home_controllerのindexが受け管理者の認証を要する(): void
    {
        $route = Route::getRoutes()->getByName('admin.home');

        $this->assertSame(AdminHomeController::class.'@index', $route->getActionName());
        $this->assertContains('auth:admin', $route->gatherMiddleware());
    }

    /**
     * ヘッダー・フッターの文言とビューが表示される
     */
    #[Test]
    public function 管理者にはヘッダーとフッターとタイトルが表示される(): void
    {
        $response = $this->openHome();

        $response->assertOk();
        $response->assertViewIs('admin.index');
        $response->assertSee('<title>管理画面 | Shioya Farm</title>', false);
        $response->assertSeeInOrder([
            '<h1', 'しおや農園直販サイト管理画面',
            'サイトを見る', 'ログアウト',
            '各種一覧', '作成・登録',
            '&copy;Shioya Farm',
        ], false);
        $response->assertSee('storage/image/icon/logout-icon.webp', false);
    }

    /**
     * 「各種一覧」のリンクは注文・予約・会員・お問い合わせ・お知らせ・掲載商品の順
     */
    #[Test]
    public function 各種一覧のカードに6つのリンクが順に表示される(): void
    {
        $this->openHome()->assertSeeInOrder([
            '各種一覧',
            '注文一覧', '予約一覧', '会員一覧', 'お問い合わせ一覧', 'お知らせ一覧', '掲載商品一覧',
            '作成・登録',
        ]);
    }

    /**
     * 「作成・登録」のリンクはお知らせ作成・掲載商品登録の順
     */
    #[Test]
    public function 作成登録のカードに2つのリンクが順に表示される(): void
    {
        $this->openHome()->assertSeeInOrder(['作成・登録', 'お知らせ作成', '掲載商品登録', '&copy;Shioya Farm'], false);
    }

    /**
     * 8つのリンクの行き先（画面が未実装のため url() で書いてある。実装時に route() へ置き換える）
     */
    #[Test]
    public function リンク8つの行き先は設計書の_ur_lになっている(): void
    {
        $html = $this->homeHtml();

        $hrefs = [
            '注文一覧' => '/admin/purchase',
            '予約一覧' => '/admin/reservations',
            '会員一覧' => '/admin/users',
            'お問い合わせ一覧' => '/admin/inquiries',
            'お知らせ一覧' => '/admin/notices',
            '掲載商品一覧' => '/admin/items',
            'お知らせ作成' => '/admin/notices/create',
            '掲載商品登録' => '/admin/items/create',
        ];
        foreach ($hrefs as $label => $path) {
            $this->assertMatchesRegularExpression(
                '#<a [^>]*href="'.preg_quote(url($path), '#').'"[^>]*>'.preg_quote($label, '#').'</a>#u',
                $html,
                "「{$label}」のリンクが {$path} を指していない"
            );
        }
        $this->assertSame(8, preg_match_all('#<li>\s*<a #', $html));
    }

    /**
     * 「サイトを見る」は別タブで公開サイトのトップを開き、ログアウトの左にあり、文言に「別タブ」を付けない
     */
    #[Test]
    public function サイトを見るは別タブで公開サイトのトップを開きログアウトの左にある(): void
    {
        $html = $this->homeHtml();

        $this->assertMatchesRegularExpression(
            '#<a [^>]*href="'.preg_quote(route('top.index'), '#').'"[^>]*target="_blank"[^>]*rel="noopener"[^>]*>サイトを見る</a>#u',
            $html
        );
        $this->assertLessThan(mb_strpos($html, 'ログアウト</span>'), mb_strpos($html, 'サイトを見る</a>'));
        $this->assertStringNotContainsString('別タブ', $html);
    }

    /**
     * ログアウトはPOSTのフォームで、アイコンと文字が同じボタンの中にある
     */
    #[Test]
    public function ログアウトは_pos_tのフォームでアイコンと文字をまとめて押せる(): void
    {
        $html = $this->homeHtml();

        $this->assertMatchesRegularExpression(
            '#<form action="'.preg_quote(route('admin.logout'), '#').'" method="POST">.*?<button [^>]*type="submit".*?<img [^>]*alt="".*?ログアウト</span>\s*</button>#su',
            $html
        );
        $this->assertStringContainsString('name="_token"', $html);
    }

    /**
     * ログアウト・「サイトを見る」はホバー・フォーカスで緑の下線（ふだんはなし）
     */
    #[Test]
    public function ログアウトとサイトを見るはホバーとフォーカスで緑の下線が出る(): void
    {
        $html = $this->homeHtml();

        $this->assertStringContainsString('underline-accent hover:underline focus-visible:underline', $html);
        $this->assertStringContainsString('underline-accent group-hover:underline group-focus-visible:underline', $html);
    }

    /**
     * カード見出しの色は @theme の既存の色で、見出しごとに違う
     */
    #[Test]
    public function カード見出しの色が各種一覧と作成登録で違う(): void
    {
        $html = $this->homeHtml();

        $this->assertMatchesRegularExpression('#<h2 class="[^"]*text-accent-hover[^"]*">各種一覧</h2>#u', $html);
        $this->assertMatchesRegularExpression('#<h2 class="[^"]*text-primary-text[^"]*">作成・登録</h2>#u', $html);
        $this->assertStringNotContainsString('[#', $html);
    }

    /**
     * リンクの色はink、下線はホバー・フォーカスのときだけ見出しの色で出る
     */
    #[Test]
    public function リンクの下線はホバーとフォーカスのときだけ見出しの色で出る(): void
    {
        $html = $this->homeHtml();

        preg_match_all('#<li>\s*<a class="([^"]*)" href="[^"]*">([^<]*)</a>#u', $html, $matches, PREG_SET_ORDER);
        $this->assertCount(8, $matches);
        foreach ($matches as $i => [, $class, $label]) {
            $decoration = $i < 6 ? 'decoration-accent-hover' : 'decoration-primary-text';
            $this->assertStringContainsString('text-ink', $class, $label);
            $this->assertStringContainsString('underline-thick hover:underline focus-visible:underline', $class, $label);
            $this->assertStringContainsString($decoration, $class, $label);
            // ふだんは下線なし（underline は hover:・focus-visible: の接頭辞つきだけ）
            $this->assertDoesNotMatchRegularExpression('/(?<![:\w-])underline(?![-\w])/', $class, $label);
        }
    }

    /**
     * SP：ヘッダーは縦に2段、カードは縦積み（lg:以上で横並び）、リンクは18px・44px以上。PC：カードは500×400
     */
    #[Test]
    public function レイアウトは_s_pで縦積みでlg以上で横並びになるクラスを持つ(): void
    {
        $html = $this->homeHtml();

        // ヘッダー：SPは flex-col の2段、PCは lg:flex-row の1行で高さ90px
        $this->assertStringContainsString('flex flex-col px-4 py-2 lg:h-[90px] lg:flex-row', $html);
        // 画面が低くてもフッターと重ならない縦積み
        $this->assertStringContainsString('flex min-h-dvh flex-col', $html);
        $this->assertStringContainsString('<main class="flex flex-1 flex-col items-center justify-center', $html);
        // カード：SPは縦積みで幅いっぱい、PCは横並びで500×400、間隔は xl で144px
        $this->assertStringContainsString('flex w-full flex-col items-center gap-8 lg:flex-row', $html);
        $this->assertStringContainsString('xl:gap-36', $html);
        $this->assertSame(2, substr_count($html, 'w-full rounded-[20px] bg-white'));
        $this->assertSame(2, substr_count($html, 'lg:h-100 lg:w-125'));
        // リンク：SPは18px・高さ44px以上
        $this->assertSame(8, substr_count($html, 'min-h-11 items-center justify-center text-lg'));
        // ヘッダーの2つの操作は高さ44px以上で、文字はSPが18px・lg以上が16px
        $this->assertStringContainsString('inline-flex min-h-11 items-center text-lg lg:text-base', $html);
        $this->assertStringContainsString('group flex min-h-11 cursor-pointer items-center gap-2.5 text-lg lg:text-base', $html);
    }

    /**
     * 管理画面のトップは公開側のヘッダー・ナビや橙の部品を使わない
     */
    #[Test]
    public function 公開側のヘッダーや橙の部品を使わない(): void
    {
        $html = $this->homeHtml();

        $this->assertStringNotContainsString('送料案内', $html);
        $this->assertStringNotContainsString('bg-primary', $html);
        $this->assertStringNotContainsString('cream', $html);
    }

    /**
     * ログアウトの419で戻ってきたときの案内（status）が、ヘッダーの下・main の外に role="status" で出る。ないときは出ない
     */
    #[Test]
    public function statusの案内はヘッダーの下でmainの外にrole_statusで表示される(): void
    {
        $admin = Admin::factory()->create();

        $html = $this->actingAs($admin, 'admin')
            ->withSession(['status' => 'ページの有効期限が切れました。もう一度ログアウトしてください。'])
            ->get(route('admin.home'))
            ->assertOk()
            ->assertSeeInOrder(['role="status"', 'ページの有効期限が切れました。もう一度ログアウトしてください。', '各種一覧'], false)
            ->getContent();

        // 案内はヘッダーの下・main の外に出る（出ている間、カードはその分下がる）
        $this->assertLessThan(mb_strpos($html, '<main'), mb_strpos($html, 'role="status"'));
        $this->assertGreaterThan(mb_strpos($html, '</header>'), mb_strpos($html, 'role="status"'));

        $this->assertStringContainsString('border border-line-strong bg-surface', $html);
    }

    /**
     * statusがないときは案内の枠を出さない
     */
    #[Test]
    public function statusがないときは案内を表示しない(): void
    {
        $this->openHome()->assertOk()->assertDontSee('role="status"', false);
    }
}
