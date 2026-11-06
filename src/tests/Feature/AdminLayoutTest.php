<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\AdminNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の共通部品（サイドメニュー・ヘッダーのメニューボタン・AdminNav・no-store）のテスト
 */
class AdminLayoutTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 注文一覧のページの本文（HTML）
     */
    private function ordersHtml(): string
    {
        $this->loginAsAdmin();

        return $this->get(route('admin.purchase.index'))->assertOk()->getContent();
    }

    /**
     * AdminNav：トップページと8つのリンクが、決められた順とURLで並ぶ（未実装の画面は url() で書いてある）
     */
    #[Test]
    public function adminnavはトップと8つのリンクを決められた順とurlで返す(): void
    {
        $this->assertSame(['label' => 'トップページ', 'url' => url('/admin'), 'active' => ['admin'], 'linkClass' => 'decoration-admin-head'], AdminNav::home());

        $groups = AdminNav::groups();
        $this->assertSame(['各種一覧', '作成・登録'], array_column($groups, 'title'));
        $links = [];
        foreach ($groups as $group) {
            foreach ($group['links'] as $link) {
                $links[$link['label']] = $link['url'];
            }
        }
        $this->assertSame([
            '注文一覧' => url('/admin/purchase'),
            '予約一覧' => url('/admin/reservations'),
            '会員一覧' => url('/admin/users'),
            'お問い合わせ一覧' => url('/admin/inquiries'),
            'お知らせ一覧' => url('/admin/notices'),
            '掲載商品一覧' => url('/admin/items'),
            'お知らせ作成' => url('/admin/notices/create'),
            '掲載商品登録' => url('/admin/items/create'),
        ], $links);
        // 注文一覧は route() で書く
        $this->assertSame(route('admin.purchase.index'), $links['注文一覧']);
        $this->assertSame(route('admin.users.index'), $links['会員一覧']);
    }

    /**
     * AdminNav：表示中のページのリンクだけが現在のページと判定される
     */
    #[Test]
    public function adminnavは表示中のページのリンクだけを現在のページとする(): void
    {
        $this->loginAsAdmin();
        $current = function (string $path): array {
            $this->get($path);
            $labels = [];
            foreach ([['label' => 'トップページ'] + AdminNav::home(), ...array_merge(...array_column(AdminNav::groups(), 'links'))] as $link) {
                if (AdminNav::isCurrent($link)) {
                    $labels[] = $link['label'];
                }
            }

            return $labels;
        };

        $this->assertSame(['トップページ'], $current('/admin'));
        $this->assertSame(['注文一覧'], $current('/admin/purchase'));
        $this->assertSame(['注文一覧'], $current('/admin/purchase/search?name=a'));
        $this->assertSame(['会員一覧'], $current('/admin/users'));
        $this->assertSame(['会員一覧'], $current('/admin/users/search?name=a'));
    }

    /**
     * サイドメニュー：トップページと8つのリンクが決められた順に並び、注文一覧が表示中として示される
     */
    #[Test]
    public function サイドメニューにトップと8つのリンクが並び注文一覧が表示中になる(): void
    {
        $html = $this->ordersHtml();

        $this->assertStringContainsString('<aside', $html);
        $hrefs = [
            'トップページ' => '/admin',
            '注文一覧' => '/admin/purchase',
            '予約一覧' => '/admin/reservations',
            '会員一覧' => '/admin/users',
            'お問い合わせ一覧' => '/admin/inquiries',
            'お知らせ一覧' => '/admin/notices',
            '掲載商品一覧' => '/admin/items',
            'お知らせ作成' => '/admin/notices/create',
            '掲載商品登録' => '/admin/items/create',
        ];
        $asideStart = mb_strpos($html, '<aside');
        $aside = mb_substr($html, $asideStart, mb_strpos($html, '</aside>') - $asideStart);
        foreach ($hrefs as $label => $path) {
            $this->assertMatchesRegularExpression('#<a [^>]*href="'.preg_quote(url($path), '#').'"[^>]*>'.preg_quote($label, '#').'</a>#u', $aside, $label);
        }
        $this->assertSame(9, preg_match_all('#<li>\s*<a #', $aside));
        $this->assertSame(1, preg_match_all('#aria-current="page"#', $aside));
        $this->assertMatchesRegularExpression('#class="[^"]*bg-admin-bar-end font-bold[^"]*" href="'.preg_quote(route('admin.purchase.index'), '#').'" aria-current="page">注文一覧</a>#u', $aside);
        // 見出しの順（各種一覧 → 作成・登録）と色
        $this->assertMatchesRegularExpression('#text-accent-hover">各種一覧</p>.*text-primary-text">作成・登録</p>#su', $aside);
    }

    /**
     * サイドメニューのリンクの下線は、管理画面トップのカードと同じ太さ・距離（underline-thick）で、色は種類ごとに違う
     * （トップページ＝表の見出しと同じ灰色、各種一覧＝緑、作成・登録＝茶色）
     */
    #[Test]
    public function サイドメニューのリンクのホバー下線は種類ごとの色で太さはトップのカードと同じである(): void
    {
        $html = $this->ordersHtml();
        $asideStart = mb_strpos($html, '<aside');
        $aside = mb_substr($html, $asideStart, mb_strpos($html, '</aside>') - $asideStart);

        $expected = [
            'トップページ' => 'decoration-admin-head',
            '注文一覧' => 'decoration-accent-hover',
            '予約一覧' => 'decoration-accent-hover',
            '会員一覧' => 'decoration-accent-hover',
            'お問い合わせ一覧' => 'decoration-accent-hover',
            'お知らせ一覧' => 'decoration-accent-hover',
            '掲載商品一覧' => 'decoration-accent-hover',
            'お知らせ作成' => 'decoration-primary-text',
            '掲載商品登録' => 'decoration-primary-text',
        ];
        $others = ['decoration-admin-head', 'decoration-accent-hover', 'decoration-primary-text'];
        foreach ($expected as $label => $decoration) {
            $this->assertSame(1, preg_match('#<a class="([^"]*)" href="[^"]*"[^>]*>'.preg_quote($label, '#').'</a>#u', $aside, $m), $label);
            $classes = explode(' ', $m[1]);
            // 太さ・距離（underline-thick）と、ホバー・フォーカスの下線、色
            foreach (['underline-thick', 'hover:underline', 'focus-visible:underline', $decoration] as $class) {
                $this->assertContains($class, $classes, $label.'：'.$class);
            }
            // ほかの種類の色は付かない
            foreach (array_diff($others, [$decoration]) as $class) {
                $this->assertNotContains($class, $classes, $label.'：'.$class);
            }
        }
        // 「メニューを閉じる」はリンクではないので、太さ・色は変えない
        $this->assertDoesNotMatchRegularExpression('#<button [^>]*underline-thick#u', $aside);
    }

    /**
     * サイドメニューの幅と開閉：PC は 200px で右に線、畳むと 56px。ボタンの文言・aria が決められたとおり
     */
    #[Test]
    public function サイドメニューは_pcで200pxと56pxに開閉しボタンの名前が付く(): void
    {
        $html = $this->ordersHtml();

        $this->assertStringContainsString("x-data=\"{ open: \$persist(true).as('admin-sidebar-open'), spOpen: false, ready: false }\" x-init=\"\$nextTick(() => ready = true)\"", $html);
        $this->assertStringContainsString('lg:w-50 lg:shrink-0 lg:border-r lg:border-line lg:bg-admin-bar lg:relative lg:overflow-hidden', $html);
        $this->assertStringContainsString("'lg:w-50': open, 'lg:w-14': ! open", $html);
        $this->assertStringContainsString('x-cloak', $html);
        // 開いているときの「メニューを閉じる」と、畳んだ帯の「メニュー」（縦書き。aria-label「メニューを開く」）
        $this->assertMatchesRegularExpression('#<button [^>]*aria-expanded="true"[^>]*x-on:click="open = false; \$nextTick\(\(\) => requestAnimationFrame\(\(\) => \$refs\.sidebarOpen\.focus\(\)\)\)">\s*<svg[^>]*aria-hidden="true">.*?</svg>\s*メニューを閉じる\s*</button>#su', $html);
        $this->assertMatchesRegularExpression('#<button [^>]*w-11[^>]*aria-label="メニューを開く" aria-expanded="false"[^>]*x-bind:aria-expanded="open" x-ref="sidebarOpen" x-on:click="open = true; \$nextTick\(\(\) => requestAnimationFrame\(\(\) => \$refs\.sidebarClose\.focus\(\)\)\)">.*?\[writing-mode:vertical-rl\]">メニュー</span>#su', $html);
    }

    /**
     * 開閉のアニメーション：transition は Alpine の起動後（ready）に付け、動きを減らす設定のときは付かない（motion-safe:）。
     * 閉じている側は visibility で隠し、Tab でフォーカスが入らない
     */
    #[Test]
    public function サイドメニューの開閉はアニメーションし再読み込みでは動かず閉じた側にフォーカスが入らない(): void
    {
        $html = $this->ordersHtml();

        // 初期のHTMLには transition を付けない（ready が true になってから付ける）
        $asideStart = mb_strpos($html, '<aside');
        $aside = mb_substr($html, $asideStart, mb_strpos($html, '</aside>') - $asideStart);
        $this->assertDoesNotMatchRegularExpression('#class="[^"]*(?<!:)transition-#', $aside);
        $this->assertStringContainsString("'motion-safe:lg:transition-[width] motion-safe:lg:duration-200 motion-safe:lg:ease-out': ready", $aside);
        $this->assertStringContainsString("'motion-safe:transition-[grid-template-rows,opacity,visibility] motion-safe:duration-200 motion-safe:ease-out': ready", $aside);
        $this->assertStringContainsString("'motion-safe:lg:transition-[opacity,visibility] motion-safe:lg:duration-200 motion-safe:lg:ease-out': ready", $aside);
        // transition-none ではなく motion-safe: で付けるため、reduce のときは何も付かない
        $this->assertDoesNotMatchRegularExpression('#[\s\'"](?:lg:)?transition-(?:\[|all|colors|opacity)#', $aside);

        // 閉じている側は invisible（SPの初期はメニューが invisible、PCの初期は帯が invisible）
        $this->assertStringContainsString('class="invisible grid grid-rows-[0fr] lg:visible lg:block lg:w-50"', $aside);
        $this->assertStringContainsString('lg:w-14 lg:invisible lg:opacity-0"', $aside);
        // 余白は内側の要素に付ける（外側に付けると高さ0にならない）
        $this->assertStringContainsString('<div class="min-h-0 overflow-hidden">', $aside);
        // 開閉ボタンを押したら、開いた側のボタンへフォーカスを移す（visibility が切り替わる最初のフレームの後に移す）
        $this->assertStringContainsString('x-ref="sidebarClose"', $aside);
        $this->assertStringContainsString('x-ref="sidebarOpen"', $aside);
    }

    /**
     * SP：ヘッダーの2段目の左に「メニュー」ボタンが出て、メニューは初期状態で閉じている（PCの保存状態は使わない）
     */
    #[Test]
    public function spはヘッダーのメニューボタンで開閉し初期状態は閉じている(): void
    {
        $html = $this->ordersHtml();

        $this->assertMatchesRegularExpression('#<button class="[^"]*h-11[^"]*rounded-\[10px\][^"]*border-line-strong[^"]*lg:hidden" type="button" aria-controls="admin-menu" aria-expanded="false" x-bind:aria-expanded="spOpen" x-on:click="spOpen = ! spOpen">\s*<span x-text="spOpen \? \'閉じる\' : \'メニュー\'">メニュー</span>#u', $html);
        // メニューの初期の class は、SPで閉じる（invisible・高さ0fr）・PCで開く（lg:visible）
        $this->assertMatchesRegularExpression('#<div id="admin-menu" class="invisible grid grid-rows-\[0fr\] lg:visible [^"]*" x-bind:class="\{ \'visible grid-rows-\[1fr\]\': spOpen, \'invisible grid-rows-\[0fr\]\': ! spOpen, \'lg:visible lg:opacity-100\': open, \'lg:invisible lg:opacity-0\': ! open, #u', $html);
        // 幅が足りない（320px前後）ときは、2段目の操作を折り返す（横スクロールを出さない）
        $this->assertStringContainsString('flex items-center flex-wrap justify-between gap-y-1 lg:justify-end', $html);
        $this->assertStringContainsString('ml-auto gap-x-3 lg:gap-x-8', $html);
        // SPのリンクは18px・高さ48px、PCは16px・44px
        $this->assertStringContainsString('min-h-12 items-center rounded-[5px] pr-2 text-lg text-ink underline-thick hover:underline focus-visible:underline lg:min-h-11 lg:text-base', $html);
        // メニューを閉じる・線はPCだけ
        $this->assertStringContainsString('hidden min-h-11 w-full cursor-pointer items-center gap-2', $html);
    }

    /**
     * ヘッダー・フッターはトップページと共通で、トップページにはサイドメニューも「メニュー」ボタンもない
     */
    #[Test]
    public function トップページにはサイドメニューがなくヘッダーとフッターは注文一覧と共通である(): void
    {
        $this->loginAsAdmin();
        $home = $this->get(route('admin.home'))->assertOk()->getContent();
        $orders = $this->get(route('admin.purchase.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<aside', $home);
        $this->assertStringNotContainsString('aria-controls="admin-menu"', $home);
        $this->assertStringNotContainsString('admin-sidebar-open', $home);
        $this->assertStringContainsString('<h1 class="py-1 text-xl font-bold lg:py-0">しおや農園直販サイト管理画面</h1>', $home);
        $this->assertSame(1, substr_count($home, '<h1'));

        foreach (['bg-linear-to-r from-admin-bar from-60% to-admin-bar-end', 'lg:h-[90px]', 'ログアウト</span>', 'サイトを見る</a>', 'flex h-15 items-center justify-center bg-admin-bar font-maru', '&copy;Shioya Farm'] as $common) {
            $this->assertStringContainsString($common, $home);
            $this->assertStringContainsString($common, $orders);
        }
    }

    /**
     * トップのカードの「注文一覧」は、注文一覧へのリンクで、注文一覧の画面に移動できる
     */
    #[Test]
    public function トップの注文一覧のリンクから注文一覧を開ける(): void
    {
        $this->loginAsAdmin();

        $this->get(route('admin.home'))
            ->assertOk()
            ->assertSee('href="'.route('admin.purchase.index').'"', false);
        $this->get(route('admin.purchase.index'))->assertOk();
    }

    /**
     * 管理者用ログイン画面は、サイドメニューの開閉の保存を消す（ログイン直後は開く）
     */
    #[Test]
    public function ログイン画面でサイドメニューの保存を消す(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee("x-init=\"localStorage.removeItem('admin-sidebar-open')\"", false);
    }

    /**
     * 管理者のログインが必要なページは、ブラウザに保存させない（Cache-Control: no-store）
     */
    #[Test]
    public function 管理者のページはno_storeでブラウザに保存させない(): void
    {
        $this->loginAsAdmin();

        foreach ([route('admin.home'), route('admin.purchase.index'), route('admin.purchase.search')] as $url) {
            $this->assertStringContainsString('no-store', $this->get($url)->assertOk()->headers->get('Cache-Control'), $url);
        }
    }

    /**
     * 公開側のページとログイン画面には no-store を付けない
     */
    #[Test]
    public function 公開側とログイン画面にはno_storeを付けない(): void
    {
        foreach ([route('top.index'), route('admin.login'), route('login')] as $url) {
            $this->assertStringNotContainsString('no-store', (string) $this->get($url)->assertOk()->headers->get('Cache-Control'), $url);
        }
    }

    /**
     * ログアウトのあとに戻って同じページを開き直すと、内容ではなくログインページへ移動する
     */
    #[Test]
    public function ログアウト後に注文一覧を開き直すとログインページへ移動する(): void
    {
        $this->makeOrder();
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->get(route('admin.purchase.index'))->assertOk();

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->app['auth']->forgetGuards();

        $this->get(route('admin.purchase.index'))->assertRedirect(route('admin.login'));
    }
}
