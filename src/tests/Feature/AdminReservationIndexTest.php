<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminReservationController;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の予約一覧（admin.reservations.index）の表示・認証・ページ送りのテスト
 */
class AdminReservationIndexTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 3つのルートは決められたコントローラーが受け、管理者の認証と no-store が付く
     */
    #[Test]
    public function ルートは決められたコントローラーが受け管理者の認証とno_storeを要する(): void
    {
        $routes = [
            'admin.reservations.index' => [AdminReservationController::class.'@index', 'GET', '/admin/reservations'],
            'admin.reservations.search' => [AdminReservationController::class.'@search', 'GET', '/admin/reservations/search'],
            'admin.reservations.update' => [AdminReservationController::class.'@update', 'POST', '/admin/reservations/update'],
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
        $this->get(route('admin.reservations.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.reservations.search'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.export'), ['type' => 'reservation'])->assertRedirect(route('admin.login'));
        $this->post(route('admin.reservations.update'), ['id' => 1, 'shipped' => true])->assertRedirect(route('admin.login'));
    }

    /**
     * 会員としてログインしていても、管理者としては未ログインのため入れない
     */
    #[Test]
    public function 会員としてログインしていても管理者のログインページへ移動する(): void
    {
        $this->loginAsMember();

        $this->get(route('admin.reservations.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.reservations.search'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.export'), ['type' => 'reservation'])->assertRedirect(route('admin.login'));
    }

    /**
     * 管理者には、ビュー・タイトル・見出し・検索欄・エクスポート・表が表示される
     */
    #[Test]
    public function 管理者には一覧の各部品が表示される(): void
    {
        $this->loginAsAdmin();
        $reservation = $this->makeAdminReservation($this->makeUser('山田', '太郎'), '2026-11-04 10:00:00');

        $response = $this->get(route('admin.reservations.index'));

        $response->assertOk();
        $response->assertViewIs('admin.reservations');
        $response->assertSee('<title>予約一覧 | 管理画面 | Shioya Farm</title>', false);
        $response->assertSeeInOrder([
            'しおや農園直販サイト管理画面',
            '<h1', '予約一覧',
            '発送状況', '予約日', '予約者名', '検索', 'リセット',
            'エクスポート',
            '発送済', '予約管理番号', '予約日', '予約者',
            (string) $reservation->id, '2026/11/04', '山田 太郎', '詳細',
            '&copy;Shioya Farm',
        ], false);
        $response->assertSee('placeholder="予約者名を入力してください"', false);
        $response->assertSee('aria-label="予約の検索"', false);
        $response->assertSee('<input type="hidden" name="type" value="reservation">', false);
    }

    /**
     * ページタイトルが唯一の h1 で、ヘッダーのタイトルは見出しにしない
     */
    #[Test]
    public function h1は本文のページタイトルだけである(): void
    {
        $this->loginAsAdmin();

        $html = $this->get(route('admin.reservations.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertMatchesRegularExpression('#<h1 class="[^"]*text-accent-hover[^"]*">予約一覧</h1>#u', $html);
    }

    /**
     * サイドメニューの「予約一覧」は予約一覧のリンクで、一覧と検索結果のページで太字とグレーの地（現在のページ）になる
     */
    #[Test]
    public function サイドメニューの予約一覧は一覧と検索結果のページで現在のページになる(): void
    {
        $this->loginAsAdmin();

        foreach ([route('admin.reservations.index'), route('admin.reservations.search', ['name' => '山田'])] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertSame(1, preg_match_all('#aria-current="page"#', $html), $url);
            $this->assertMatchesRegularExpression('#class="[^"]*bg-admin-bar-end font-bold[^"]*" href="'.preg_quote(route('admin.reservations.index'), '#').'" aria-current="page">予約一覧</a>#u', $html, $url);
        }
    }

    /**
     * 列は発送済・予約管理番号・予約日・予約者・詳細で、列の幅は注文一覧と同じ割合
     */
    #[Test]
    public function 表は_table要素で列の見出しが決められた順に並ぶ(): void
    {
        $this->loginAsAdmin();
        $this->makeAdminReservation();

        $html = $this->get(route('admin.reservations.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<th scope="col"[^>]*>発送済</th>\s*<th scope="col"[^>]*>予約管理番号</th>\s*<th scope="col"[^>]*>予約日</th>\s*<th scope="col"[^>]*>予約者</th>\s*<th scope="col"[^>]*><span class="sr-only">詳細</span></th>#u', $html);
        $this->assertSame(1, preg_match('#<colgroup>(.*?)</colgroup>#s', $html, $colgroup));
        $this->assertSame(5, preg_match_all('#<col class="lg:w-\[(\d+)%\]">#', $colgroup[1], $widths));
        $this->assertSame([10, 24, 20, 34, 12], array_map('intval', $widths[1]));
        $this->assertStringContainsString('<caption class="sr-only">予約一覧</caption>', $html);
    }

    /**
     * 新しい予約（IDの大きい順）から1ページ10件
     */
    #[Test]
    public function 新しい順に1ページ10件が表示される(): void
    {
        $this->loginAsAdmin();
        $reservations = $this->makeAdminReservations(12);
        $ids = array_map(fn (Reservation $reservation): int => $reservation->id, $reservations);

        $response = $this->get(route('admin.reservations.index'));

        /** @var Reservation[] $shown */
        $shown = $response->viewData('reservations')->items();
        $this->assertSame(array_slice(array_reverse($ids), 0, 10), array_map(fn (Reservation $reservation): int => $reservation->id, $shown));
        $this->assertSame(12, $response->viewData('reservations')->total());
    }

    /**
     * 11件目以降は2ページ目に表示され、ページ送りが出る
     */
    #[Test]
    public function 十一件以上あるとページ送りが出て二ページ目に残りが表示される(): void
    {
        $this->loginAsAdmin();
        $reservations = $this->makeAdminReservations(12);

        $first = $this->get(route('admin.reservations.index'))->assertOk();
        $first->assertSee('aria-label="ページ送り"', false);
        $first->assertSee(route('admin.reservations.index', ['page' => 2]), false);

        $second = $this->get(route('admin.reservations.index', ['page' => 2]))->assertOk();
        $ids = array_map(fn (Reservation $reservation): int => $reservation->id, $second->viewData('reservations')->items());
        $this->assertSame([$reservations[1]->id, $reservations[0]->id], $ids);
        $second->assertSee('bg-admin-gray text-white" aria-current="page" aria-label="2ページ目"', false);
    }

    /**
     * 10件ちょうどではページ送りが出ない
     */
    #[Test]
    public function 十件ちょうどではページ送りが出ない(): void
    {
        $this->loginAsAdmin();
        $this->makeAdminReservations(10);

        $this->get(route('admin.reservations.index'))->assertOk()->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 範囲外のページ番号は最終ページを表示する
     */
    #[Test]
    public function 範囲外のページ番号は最終ページを表示する(): void
    {
        $this->loginAsAdmin();
        $this->makeAdminReservations(12);

        $response = $this->get(route('admin.reservations.index', ['page' => 99]))->assertOk();

        $this->assertSame(2, $response->viewData('reservations')->currentPage());
        $this->assertCount(2, $response->viewData('reservations')->items());
        $response->assertDontSee('該当する予約はありません。');
    }

    /**
     * 退会済みの会員の予約も、予約者名つきで表示される
     */
    #[Test]
    public function 退会済みの会員の予約も氏名つきで表示される(): void
    {
        $this->loginAsAdmin();
        $user = $this->makeUser('佐藤', '花子');
        $this->makeAdminReservation($user);
        $user->delete();

        $this->get(route('admin.reservations.index'))->assertOk()->assertSee('佐藤 花子');
    }

    /**
     * 予約者は Eager Load され、件数が増えてもクエリ数が増えない
     */
    #[Test]
    public function 予約者はeager_loadされ件数が増えてもクエリ数が変わらない(): void
    {
        $this->loginAsAdmin();
        $this->makeAdminReservations(2);
        DB::enableQueryLog();
        $this->get(route('admin.reservations.index'))->assertOk();
        $few = count(DB::getQueryLog());

        $this->makeAdminReservations(8);
        DB::flushQueryLog();
        $this->get(route('admin.reservations.index'))->assertOk();
        $many = count(DB::getQueryLog());

        $this->assertSame($few, $many);
    }

    /**
     * 0件のときは表の代わりに文言を出し、ページ送りは出さず、エクスポートは押せない
     */
    #[Test]
    public function 予約がないときは該当なしの文言でエクスポートは押せない(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.reservations.index'))->assertOk();

        $response->assertSee('該当する予約はありません。');
        $response->assertDontSee('<table', false);
        $response->assertDontSee('aria-label="ページ送り"', false);
        $response->assertSee('type="submit" disabled>エクスポート</button>', false);
    }

    /**
     * 予約があるときは、エクスポートは押せる
     */
    #[Test]
    public function 予約があるときはエクスポートが押せる(): void
    {
        $this->loginAsAdmin();
        $this->makeAdminReservation();

        $html = $this->get(route('admin.reservations.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#type="submit"\s*>エクスポート</button>#u', $html);
        $this->assertStringNotContainsString('type="submit" disabled>', $html);
    }

    /**
     * 新しい予約は未発送で始まる（チェックなし・白い行）。発送済みの予約は、チェックありで地が灰色
     */
    #[Test]
    public function 未発送と発送済みでチェックと行の地が違う(): void
    {
        $this->loginAsAdmin();
        $unshipped = $this->makeAdminReservation();
        $shipped = $this->makeAdminReservation(overrides: ['shipped' => true]);
        $this->assertFalse($unshipped->fresh()->shipped);

        $html = $this->get(route('admin.reservations.index'))->assertOk()->getContent();

        preg_match_all('#<tr\s+class="([^"]*)"\s+x-data="\{ checked: (true|false), saving: false \}"#u', $html, $rows, PREG_SET_ORDER);
        $this->assertCount(2, $rows);
        $this->assertSame('true', $rows[0][2]);
        $this->assertStringContainsString('bg-admin-shipped', $rows[0][1]);
        $this->assertStringNotContainsString('lg:even:bg-admin-bar', $rows[0][1]);
        $this->assertSame('false', $rows[1][2]);
        $this->assertStringContainsString('bg-white lg:even:bg-admin-bar', $rows[1][1]);
        $this->assertMatchesRegularExpression('#aria-label="予約'.$shipped->id.'の発送済み"\s+checked#u', $html);
        $this->assertDoesNotMatchRegularExpression('#aria-label="予約'.$unshipped->id.'の発送済み"\s+checked#u', $html);
    }

    /**
     * チェックボックスは24px・押せる範囲は高さ44px以上で、予約管理番号を含む名前が付く。押すと予約の更新ルートへ保存する
     */
    #[Test]
    public function チェックボックスは_24pxで押せる範囲が44pxありfetchで保存する(): void
    {
        $this->loginAsAdmin();
        $reservation = $this->makeAdminReservation();

        $html = $this->get(route('admin.reservations.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<label class="flex h-11 cursor-pointer', $html);
        $this->assertMatchesRegularExpression('#<input\s+class="size-6 [^"]*accent-admin-gray[^"]*"\s+type="checkbox"\s+aria-label="予約'.$reservation->id.'の発送済み"#u', $html);
        // JS の文字列は @js で出力されるため、スラッシュと日本語はエスケープされる
        $this->assertStringContainsString('fetch('.trim((string) Blade::render('@js($v)', ['v' => route('admin.reservations.update')])), $html);
        $this->assertStringNotContainsString(route('admin.purchase.update'), $html);
        $this->assertStringContainsString('save('.$reservation->id.', checked)', $html);
        $this->assertStringContainsString(trim((string) Blade::render('@js($v)', ['v' => '保存できませんでした。もう一度お試しください。'])), $html);
        $this->assertMatchesRegularExpression('#<p [^>]*role="status"[^>]*x-text="message"#u', $html);
    }

    /**
     * 「詳細」は予約ごとに、予約の詳細モーダル（Livewire）へイベントを送る
     */
    #[Test]
    public function 詳細ボタンは予約ごとに詳細モーダルへイベントを送る(): void
    {
        $this->loginAsAdmin();
        $reservation = $this->makeAdminReservation();

        $html = $this->get(route('admin.reservations.index'))->assertOk()->getContent();

        $this->assertStringContainsString("Livewire.dispatch('open-reservation-detail', { id: {$reservation->id} })", $html);
        $this->assertStringContainsString('aria-label="予約'.$reservation->id.'の詳細を見る"', $html);
        $this->assertStringContainsString('wire:id=', $html);
    }

    /**
     * 色は @theme の名前を使い、公開側の橙系を使わない
     */
    #[Test]
    public function 色は_themeの名前を使い公開側の橙を使わない(): void
    {
        $this->loginAsAdmin();
        $this->makeAdminReservation();

        $html = $this->get(route('admin.reservations.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('[#', $html);
        $this->assertStringNotContainsString('bg-primary', $html);
        $this->assertStringNotContainsString('cream', $html);
    }
}
