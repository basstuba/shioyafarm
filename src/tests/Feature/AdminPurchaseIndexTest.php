<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminExportController;
use App\Http\Controllers\AdminPurchaseController;
use App\Models\Purchase_history;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の注文一覧（admin.purchase.index）の表示・認証・ページ送りのテスト
 */
class AdminPurchaseIndexTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 4つのルートは決められたコントローラーが受け、管理者の認証と no-store が付く
     */
    #[Test]
    public function ルートは決められたコントローラーが受け管理者の認証とno_storeを要する(): void
    {
        $routes = [
            'admin.purchase.index' => [AdminPurchaseController::class.'@index', 'GET', '/admin/purchase'],
            'admin.purchase.search' => [AdminPurchaseController::class.'@search', 'GET', '/admin/purchase/search'],
            'admin.purchase.update' => [AdminPurchaseController::class.'@update', 'POST', '/admin/purchase/update'],
            'admin.export' => [AdminExportController::class.'@export', 'POST', '/admin/export'],
        ];

        foreach ($routes as $name => [$action, $method, $uri]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertSame($action, $route->getActionName(), $name);
            $this->assertContains($method, $route->methods(), $name);
            $this->assertSame($uri, '/'.$route->uri(), $name);
            $this->assertContains('auth:admin', $route->gatherMiddleware(), $name);
            $this->assertContains('no-store', $route->gatherMiddleware(), $name);
        }
    }

    /**
     * 未ログインは管理者用のログインページへ移動する
     */
    #[Test]
    public function 未ログインでは一覧も検索もエクスポートも更新もログインページへ移動する(): void
    {
        $this->get(route('admin.purchase.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.purchase.search'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.export'), ['type' => 'purchase'])->assertRedirect(route('admin.login'));
        $this->post(route('admin.purchase.update'), ['id' => 1, 'shipped' => true])->assertRedirect(route('admin.login'));
    }

    /**
     * 会員としてログインしていても、管理者としては未ログインのため入れない
     */
    #[Test]
    public function 会員としてログインしていても管理者のログインページへ移動する(): void
    {
        $this->loginAsMember();

        $this->get(route('admin.purchase.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.purchase.search'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.export'), ['type' => 'purchase'])->assertRedirect(route('admin.login'));
    }

    /**
     * 管理者には、ビュー・タイトル・見出し・検索欄・エクスポート・表が表示される
     */
    #[Test]
    public function 管理者には一覧の各部品が表示される(): void
    {
        $this->loginAsAdmin();
        $user = $this->makeUser('山田', '太郎');
        $order = $this->makeOrder($user, '2026-11-04 10:00:00');

        $response = $this->get(route('admin.purchase.index'));

        $response->assertOk();
        $response->assertViewIs('admin.orders');
        $response->assertSee('<title>注文一覧 | 管理画面 | Shioya Farm</title>', false);
        $response->assertSeeInOrder([
            'しおや農園直販サイト管理画面',
            '<h1', '注文一覧',
            '発送状況', '注文日', '注文者名', '検索', 'リセット',
            'エクスポート',
            '発送済', '注文管理番号', '注文日', '注文者',
            (string) $order->id, '2026/11/04', '山田 太郎', '詳細',
            '&copy;Shioya Farm',
        ], false);
        $response->assertSee('placeholder="注文者名を入力してください"', false);
    }

    /**
     * ページタイトルが唯一の h1 で、ヘッダーのタイトルは見出しにしない（見出しの階層が二重にならない）
     */
    #[Test]
    public function h1は本文のページタイトルだけでヘッダーのタイトルは見出しにしない(): void
    {
        $this->loginAsAdmin();

        $html = $this->get(route('admin.purchase.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertMatchesRegularExpression('#<h1 class="[^"]*text-accent-hover[^"]*">注文一覧</h1>#u', $html);
        $this->assertMatchesRegularExpression('#<p class="[^"]*font-bold[^"]*">しおや農園直販サイト管理画面</p>#u', $html);
    }

    /**
     * 列は発送済・注文管理番号・注文日・注文者・詳細で、実際の table 要素を使う
     */
    #[Test]
    public function 表は_table要素で列の見出しが決められた順に並ぶ(): void
    {
        $this->loginAsAdmin();
        $this->makeOrder();

        $html = $this->get(route('admin.purchase.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<table', $html);
        $this->assertMatchesRegularExpression('#<th scope="col"[^>]*>発送済</th>\s*<th scope="col"[^>]*>注文管理番号</th>\s*<th scope="col"[^>]*>注文日</th>\s*<th scope="col"[^>]*>注文者</th>\s*<th scope="col"[^>]*><span class="sr-only">詳細</span></th>#u', $html);
        // PC の列の幅（発送済10%・注文管理番号24%・注文日20%・注文者34%・詳細12%）
        foreach (['lg:w-[10%]', 'lg:w-[24%]', 'lg:w-[20%]', 'lg:w-[34%]', 'lg:w-[12%]'] as $width) {
            $this->assertStringContainsString($width, $html);
        }
    }

    /**
     * 新しい注文（IDの大きい順）から1ページ10件
     */
    #[Test]
    public function 新しい順に1ページ10件が表示される(): void
    {
        $this->loginAsAdmin();
        $orders = $this->makeOrders(12);
        $ids = array_map(fn (Purchase_history $order): int => $order->id, $orders);

        $response = $this->get(route('admin.purchase.index'));

        /** @var Purchase_history[] $shown */
        $shown = $response->viewData('purchases')->items();
        $this->assertSame(array_slice(array_reverse($ids), 0, 10), array_map(fn (Purchase_history $order): int => $order->id, $shown));
        $this->assertSame(12, $response->viewData('purchases')->total());
    }

    /**
     * 11件目以降は2ページ目に表示され、ページ送りが出る
     */
    #[Test]
    public function 十一件以上あるとページ送りが出て二ページ目に残りが表示される(): void
    {
        $this->loginAsAdmin();
        $orders = $this->makeOrders(12);

        $first = $this->get(route('admin.purchase.index'))->assertOk();
        $first->assertSee('aria-label="ページ送り"', false);
        $first->assertSee('aria-label="2ページ目"', false);
        $first->assertSee(route('admin.purchase.index', ['page' => 2]), false);

        $second = $this->get(route('admin.purchase.index', ['page' => 2]))->assertOk();
        $ids = array_map(fn (Purchase_history $order): int => $order->id, $second->viewData('purchases')->items());
        $this->assertSame([$orders[1]->id, $orders[0]->id], $ids);
        // 現在のページは灰色の地に白文字で、リンクではない
        $second->assertSee('bg-admin-gray text-white" aria-current="page" aria-label="2ページ目"', false);
    }

    /**
     * 10件以下ではページ送りが出ない
     */
    #[Test]
    public function 十件ちょうどではページ送りが出ない(): void
    {
        $this->loginAsAdmin();
        $this->makeOrders(10);

        $this->get(route('admin.purchase.index'))->assertOk()->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 範囲外のページ番号は最終ページを表示する（空の表にしない）
     */
    #[Test]
    public function 範囲外のページ番号は最終ページを表示する(): void
    {
        $this->loginAsAdmin();
        $this->makeOrders(12);

        $response = $this->get(route('admin.purchase.index', ['page' => 99]))->assertOk();

        $this->assertSame(2, $response->viewData('purchases')->currentPage());
        $this->assertCount(2, $response->viewData('purchases')->items());
        $response->assertDontSee('該当する注文はありません。');
    }

    /**
     * 退会済みの会員の注文も、注文者名つきで表示される
     */
    #[Test]
    public function 退会済みの会員の注文も氏名つきで表示される(): void
    {
        $this->loginAsAdmin();
        $user = $this->makeUser('佐藤', '花子');
        $this->makeOrder($user);
        $user->delete();

        $this->get(route('admin.purchase.index'))->assertOk()->assertSee('佐藤 花子');
    }

    /**
     * 注文者は Eager Load され、件数が増えてもクエリ数が増えない
     */
    #[Test]
    public function 注文者はeager_loadされ件数が増えてもクエリ数が変わらない(): void
    {
        $this->loginAsAdmin();
        $this->makeOrders(2);
        DB::enableQueryLog();
        $this->get(route('admin.purchase.index'))->assertOk();
        $few = count(DB::getQueryLog());

        $this->makeOrders(8);
        DB::flushQueryLog();
        $this->get(route('admin.purchase.index'))->assertOk();
        $many = count(DB::getQueryLog());

        $this->assertSame($few, $many);
    }

    /**
     * 0件のときは表の代わりに文言を出し、ページ送りは出さず、エクスポートは押せない
     */
    #[Test]
    public function 注文がないときは該当なしの文言でエクスポートは押せない(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.purchase.index'))->assertOk();

        $response->assertSee('該当する注文はありません。');
        $response->assertDontSee('<table', false);
        $response->assertDontSee('aria-label="ページ送り"', false);
        $response->assertSee('type="submit" disabled>エクスポート</button>', false);
    }

    /**
     * 注文があるときは、エクスポートは押せる
     */
    #[Test]
    public function 注文があるときはエクスポートが押せる(): void
    {
        $this->loginAsAdmin();
        $this->makeOrder();

        $html = $this->get(route('admin.purchase.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#type="submit"\s*>エクスポート</button>#u', $html);
        $this->assertStringNotContainsString('type="submit" disabled>', $html);
        $this->assertStringNotContainsString('該当する注文はありません。', $html);
    }

    /**
     * 新しい注文は未発送で始まる（チェックなし・白い行）。発送済みの注文は、チェックありで地が灰色
     */
    #[Test]
    public function 未発送と発送済みでチェックと行の地が違う(): void
    {
        $this->loginAsAdmin();
        $unshipped = $this->makeOrder();
        $shipped = $this->makeOrder(overrides: ['shipped' => true]);
        $this->assertFalse($unshipped->fresh()->shipped);

        $html = $this->get(route('admin.purchase.index'))->assertOk()->getContent();

        // 新しい順なので、先に発送済みの行（shipped）、次に未発送の行
        preg_match_all('#<tr\s+class="([^"]*)"\s+x-data="\{ checked: (true|false), saving: false \}"#u', $html, $rows, PREG_SET_ORDER);
        $this->assertCount(2, $rows);
        $this->assertSame('true', $rows[0][2]);
        $this->assertStringContainsString('bg-admin-shipped', $rows[0][1]);
        $this->assertStringNotContainsString('lg:even:bg-admin-bar', $rows[0][1]);
        $this->assertSame('false', $rows[1][2]);
        $this->assertStringContainsString('bg-white lg:even:bg-admin-bar', $rows[1][1]);
        $this->assertMatchesRegularExpression('#aria-label="注文'.$shipped->id.'の発送済み"\s+checked#u', $html);
        $this->assertDoesNotMatchRegularExpression('#aria-label="注文'.$unshipped->id.'の発送済み"\s+checked#u', $html);
    }

    /**
     * チェックボックスは24px・押せる範囲は高さ44px以上で、注文管理番号を含む名前が付く。押すと Alpine が保存する
     */
    #[Test]
    public function チェックボックスは_24pxで押せる範囲が44pxありfetchで保存する(): void
    {
        $this->loginAsAdmin();
        $order = $this->makeOrder();

        $html = $this->get(route('admin.purchase.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<label class="flex h-11 cursor-pointer', $html);
        $this->assertMatchesRegularExpression('#<input\s+class="size-6 [^"]*accent-admin-gray[^"]*"\s+type="checkbox"\s+aria-label="注文'.$order->id.'の発送済み"#u', $html);
        // JS の文字列は @js で出力されるため、スラッシュと日本語はエスケープされる
        $this->assertStringContainsString('fetch('.trim((string) Blade::render('@js($v)', ['v' => route('admin.purchase.update')])), $html);
        $this->assertStringContainsString('X-CSRF-TOKEN', $html);
        $this->assertStringContainsString('save('.$order->id.', checked)', $html);
        // 保存中は disabled にしない（フォーカスが外れるため）。aria-busy を付け、保存中の操作は無視して二重送信しない
        $this->assertStringNotContainsString('x-bind:disabled="saving"', $html);
        $this->assertStringContainsString('x-bind:aria-busy="saving"', $html);
        $this->assertStringContainsString('if (saving) { checked = ! checked; return }', $html);
        // 失敗の案内（role="status"）
        $this->assertStringContainsString(trim((string) Blade::render('@js($v)', ['v' => '保存できませんでした。もう一度お試しください。'])), $html);
        // ログイン・ページの期限切れ（401・419）は、再試行しても直らないため別の案内
        $this->assertStringContainsString('response.status === 401 || response.status === 419', $html);
        $this->assertStringContainsString(trim((string) Blade::render('@js($v)', ['v' => 'ページの有効期限が切れました。ページを再読み込みしてください。'])), $html);
        $this->assertMatchesRegularExpression('#<p [^>]*role="status"[^>]*x-text="message"#u', $html);
    }

    /**
     * 「詳細」は注文ごとに、詳細モーダル（Livewire）へイベントを送る
     */
    #[Test]
    public function 詳細ボタンは注文ごとに詳細モーダルへイベントを送る(): void
    {
        $this->loginAsAdmin();
        $order = $this->makeOrder();

        $html = $this->get(route('admin.purchase.index'))->assertOk()->getContent();

        $this->assertStringContainsString("Livewire.dispatch('open-purchase-detail', { id: {$order->id} })", $html);
        $this->assertStringContainsString('aria-label="注文'.$order->id.'の詳細を見る"', $html);
        $this->assertStringContainsString('wire:id=', $html);
    }

    /**
     * SP はカード形式（table を grid にする）で、横スクロールを出さない。PC は最大幅960pxの表（列の幅は割合）
     */
    #[Test]
    public function 表はspでカード形式でpcで最大幅960pxになるクラスを持つ(): void
    {
        $this->loginAsAdmin();
        $this->makeOrder();

        $html = $this->get(route('admin.purchase.index'))->assertOk()->getContent();

        $this->assertStringContainsString('mt-6 w-full max-w-[960px]', $html);
        $this->assertStringNotContainsString('max-w-[700px]', $html);
        // 列の幅は割合（px の合計がコンテナより大きいとはみ出すため）。合計は100%
        $this->assertSame(1, preg_match('#<colgroup>(.*?)</colgroup>#s', $html, $colgroup));
        $this->assertSame(5, preg_match_all('#<col class="lg:w-\[(\d+)%\]">#', $colgroup[1], $widths));
        $this->assertSame([10, 24, 20, 34, 12], array_map('intval', $widths[1]));
        $this->assertSame(100, array_sum($widths[1]));
        $this->assertStringContainsString('block w-full lg:table lg:table-fixed', $html);
        $this->assertStringContainsString('grid grid-cols-[minmax(0,1fr)_auto]', $html);
        $this->assertStringContainsString('rounded-[10px] border border-line', $html);
        // ラベルはSPだけ表示する
        foreach (['注文管理番号', '注文日', '注文者'] as $label) {
            $this->assertStringContainsString('<span class="mr-2 text-cancel-text lg:hidden">'.$label.'</span>', $html);
        }
        $this->assertStringNotContainsString('overflow-x-scroll', $html);
    }

    /**
     * 色は @theme の名前を使い、色を直接書かない。公開側の橙系も使わない
     */
    #[Test]
    public function 色は_themeの名前を使い公開側の橙を使わない(): void
    {
        $this->loginAsAdmin();
        $this->makeOrder();

        $html = $this->get(route('admin.purchase.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('[#', $html);
        $this->assertStringNotContainsString('bg-primary', $html);
        $this->assertStringNotContainsString('cream', $html);
        $this->assertStringContainsString('bg-admin-head', $html);
    }
}
