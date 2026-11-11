<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminNoticeController;
use App\Models\Notice;
use App\Support\AdminNav;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面のお知らせ一覧・検索（admin.notices.index・admin.notices.search）の表示・認証・ページ送り・検索のテスト
 */
class AdminNoticeIndexTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * お知らせを n 件作る（作った順＝ID の昇順。作成日時は1分ずつ新しくなる）
     *
     * @return list<Notice>
     */
    private function makeNotices(int $count): array
    {
        $notices = [];
        for ($i = 0; $i < $count; $i++) {
            $notices[] = Notice::factory()->create(['created_at' => now()->subHour()->addMinutes($i)]);
        }

        return $notices;
    }

    /**
     * 一覧のお知らせIDを（表示順に）返す
     *
     * @param  array<string, mixed>  $query
     * @return list<int>
     */
    private function searchIds(array $query): array
    {
        return array_map(
            fn (Notice $notice): int => $notice->id,
            $this->get(route('admin.notices.search', $query))->assertOk()->viewData('notices')->items(),
        );
    }

    /**
     * 7つのルートは決められたコントローラーが受け、管理者の認証と no-store が付く。修正のIDは数字だけ
     */
    #[Test]
    public function ルートは決められたコントローラーが受け管理者の認証とno_storeを要する(): void
    {
        $routes = [
            'admin.notices.index' => ['index', 'GET', '/admin/notices'],
            'admin.notices.search' => ['search', 'GET', '/admin/notices/search'],
            'admin.notices.create' => ['create', 'GET', '/admin/notices/create'],
            'admin.notices.store' => ['store', 'POST', '/admin/notices/store'],
            'admin.notices.edit' => ['edit', 'GET', '/admin/notices/edit/{notice_id}'],
            'admin.notices.update' => ['update', 'POST', '/admin/notices/update'],
            'admin.notices.delete' => ['delete', 'DELETE', '/admin/notices/delete'],
        ];

        foreach ($routes as $name => [$method, $httpMethod, $uri]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertSame(AdminNoticeController::class.'@'.$method, $route->getActionName(), $name);
            $this->assertContains($httpMethod, $route->methods(), $name);
            $this->assertSame($uri, '/'.$route->uri(), $name);
            $this->assertContains('auth:admin', $route->gatherMiddleware(), $name);
            $this->assertContains('no-store', $route->gatherMiddleware(), $name);
        }

        // 修正ページのIDは数字だけ（それ以外は404）
        $this->loginAsAdmin();
        $this->get('/admin/notices/edit/abc')->assertNotFound();
    }

    /**
     * 未ログインでは、一覧・検索・作成・修正ページも、作成・修正・削除の送信も、管理者用のログインページへ移動し、お知らせは変わらない
     */
    #[Test]
    public function 未ログインではどのルートもログインページへ移動する(): void
    {
        $notice = Notice::factory()->create(['title' => '元のタイトル']);

        $this->get(route('admin.notices.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.notices.search'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.notices.create'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.notices.edit', $notice->id))->assertRedirect(route('admin.login'));
        $this->post(route('admin.notices.store'), ['title' => '新規', 'text' => '内容'])->assertRedirect(route('admin.login'));
        $this->post(route('admin.notices.update'), ['id' => $notice->id, 'title' => '変更', 'text' => '内容'])->assertRedirect(route('admin.login'));
        $this->delete(route('admin.notices.delete'), ['id' => $notice->id])->assertRedirect(route('admin.login'));

        $this->assertDatabaseCount('notices', 1);
        $this->assertSame('元のタイトル', $notice->fresh()->title);
    }

    /**
     * 会員としてログインしていても、管理者としては未ログインのため入れない
     */
    #[Test]
    public function 会員としてログインしていても管理者のログインページへ移動する(): void
    {
        $this->loginAsMember();
        $notice = Notice::factory()->create(['title' => '元のタイトル']);

        $this->get(route('admin.notices.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.notices.search'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.notices.create'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.notices.edit', $notice->id))->assertRedirect(route('admin.login'));
        $this->post(route('admin.notices.store'), ['title' => '新規', 'text' => '内容'])->assertRedirect(route('admin.login'));
        $this->post(route('admin.notices.update'), ['id' => $notice->id, 'title' => '変更', 'text' => '内容'])->assertRedirect(route('admin.login'));
        $this->delete(route('admin.notices.delete'), ['id' => $notice->id])->assertRedirect(route('admin.login'));

        $this->assertDatabaseCount('notices', 1);
        $this->assertSame('元のタイトル', $notice->fresh()->title);
    }

    /**
     * 管理者には、ビュー・タイトル・見出し・検索欄・表が表示される。応答は no-store
     */
    #[Test]
    public function 管理者には一覧の各部品が表示される(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create(['title' => '収穫祭のお知らせ', 'created_at' => '2026-11-08 10:00:00']);

        $response = $this->get(route('admin.notices.index'));

        $response->assertOk();
        $response->assertViewIs('admin.notices');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertSee('<title>お知らせ一覧 | 管理画面 | Shioya Farm</title>', false);
        $response->assertSeeInOrder([
            'しおや農園直販サイト管理画面',
            '<h1', 'お知らせ一覧',
            '作成日', '検索', 'リセット',
            '2026/11/08', '収穫祭のお知らせ', '詳細', '修正', '削除',
            '&copy;Shioya Farm',
        ], false);
        $response->assertSee('aria-label="お知らせの検索"', false);
        $response->assertSee('action="'.route('admin.notices.search').'"', false);
        $response->assertSee('type="date"', false);
        // 検索欄は作成日だけ
        $response->assertDontSee('<select', false);
        $response->assertDontSee('name="name"', false);
        // 詳細モーダルの Livewire コンポーネントと、「詳細」ボタンが送るイベント
        $response->assertSee('wire:id', false);
        $response->assertSee("Livewire.dispatch('open-notice-detail', { id: {$notice->id} })", false);
        // 「修正」のリンク先
        $response->assertSee('href="'.route('admin.notices.edit', $notice->id).'"', false);
    }

    /**
     * ページタイトルが唯一の h1 で、ヘッダーのタイトルは見出しにしない
     */
    #[Test]
    public function h1は本文のページタイトルだけである(): void
    {
        $this->loginAsAdmin();

        $html = $this->get(route('admin.notices.index'))->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('#<h1[ >]#', $html));
    }

    /**
     * 列は作成日・タイトル・詳細・修正・削除の5つで、列の幅は割合で指定する
     */
    #[Test]
    public function 表は_table要素で列の見出しが決められた順に並ぶ(): void
    {
        $this->loginAsAdmin();
        Notice::factory()->create();

        $html = $this->get(route('admin.notices.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<table', $html);
        $this->assertSame(5, substr_count($html, '<col class="lg:w-['));
        $this->assertSame(1, preg_match('#<thead.*</thead>#su', $html, $head));
        $this->assertSame(5, substr_count($head[0], '<th scope="col"'));
        $this->assertMatchesRegularExpression('#作成日</th>.*タイトル</th>.*詳細</span></th>.*修正</span></th>.*削除</span></th>#su', $head[0]);
    }

    /**
     * 作成日の新しい順（同時刻は新しく作った順＝IDの降順）で並ぶ。更新日は順番に関係しない
     */
    #[Test]
    public function 作成日の新しいお知らせから順に並び同時刻はid降順になる(): void
    {
        $this->loginAsAdmin();
        $old = Notice::factory()->create(['created_at' => '2026-11-01 09:00:00', 'updated_at' => '2026-11-09 09:00:00']);
        $sameA = Notice::factory()->create(['created_at' => '2026-11-05 09:00:00']);
        $sameB = Notice::factory()->create(['created_at' => '2026-11-05 09:00:00']);
        // IDは小さいが、作成日時が新しい
        $newest = Notice::factory()->create(['created_at' => '2026-11-07 09:00:00', 'updated_at' => '2026-11-07 09:00:00']);

        $ids = array_map(fn (Notice $n): int => $n->id, $this->get(route('admin.notices.index'))->viewData('notices')->items());

        $this->assertSame([$newest->id, $sameB->id, $sameA->id, $old->id], $ids);
    }

    /**
     * 11件あるときは1ページ10件で、ページ送りが出る。2ページ目に残りの1件
     */
    #[Test]
    public function 一覧は1ページ10件でページ送りが出る(): void
    {
        $this->loginAsAdmin();
        $notices = $this->makeNotices(11);

        $first = $this->get(route('admin.notices.index'));
        $first->assertOk()->assertSee('aria-label="ページ送り"', false);
        $this->assertCount(10, $first->viewData('notices')->items());
        $this->assertSame($notices[10]->id, $first->viewData('notices')->items()[0]->id);

        $second = $this->get(route('admin.notices.index', ['page' => 2]));
        $this->assertSame([$notices[0]->id], array_map(fn (Notice $n): int => $n->id, $second->viewData('notices')->items()));
    }

    /**
     * 10件以下のときは、ページ送りが出ない
     */
    #[Test]
    public function 十件以下のときはページ送りが出ない(): void
    {
        $this->loginAsAdmin();
        $this->makeNotices(10);

        $this->get(route('admin.notices.index'))->assertOk()->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 存在しないページ番号は、最終ページを表示する
     */
    #[Test]
    public function 存在しないページ番号は最終ページになる(): void
    {
        $this->loginAsAdmin();
        $notices = $this->makeNotices(11);

        $paginator = $this->get(route('admin.notices.index', ['page' => 9999]))->assertOk()->viewData('notices');

        $this->assertSame(2, $paginator->currentPage());
        $this->assertSame([$notices[0]->id], array_map(fn (Notice $n): int => $n->id, $paginator->items()));
    }

    /**
     * お知らせがないときは、表の代わりに案内を出し、ページ送りも出さない
     */
    #[Test]
    public function お知らせがないときは案内を出す(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.notices.index'));

        $response->assertOk()->assertSee('該当するお知らせはありません。');
        $response->assertDontSee('<table', false);
        $response->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 作成日は日本時間のその日の日付（YYYY/MM/DD）で表示される（UTC では 2026-11-07 15:30 の時刻は、日本時間では 11/08 になる）
     */
    #[Test]
    public function 作成日は日本時間で表示される(): void
    {
        $this->loginAsAdmin();
        $utc = CarbonImmutable::parse('2026-11-07T15:30:00Z');
        $notice = Notice::factory()->create(['created_at' => $utc->setTimezone((string) config('app.timezone'))]);
        $this->assertDatabaseHas('notices', ['id' => $notice->id, 'created_at' => '2026-11-08 00:30:00']);

        $this->get(route('admin.notices.index'))->assertOk()->assertSee('2026/11/08')->assertDontSee('2026/11/07');
    }

    /**
     * 長いタイトルは折り返す（セルに break-words と min-w-0 を付ける）。SP のカードでも同じ
     */
    #[Test]
    public function 長いタイトルは折り返す(): void
    {
        $this->loginAsAdmin();
        Notice::factory()->create(['title' => str_repeat('あ', 255)]);

        $html = $this->get(route('admin.notices.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<td class="[^"]*min-w-0[^"]*break-words[^"]*">#', $html);
        $this->assertStringContainsString(str_repeat('あ', 255), $html);
    }

    /**
     * タイトルに HTML の記号があっても、文字のまま表示される（一覧・削除の確認に渡す値・aria-label）
     */
    #[Test]
    public function html記号はエスケープされる(): void
    {
        $this->loginAsAdmin();
        Notice::factory()->create(['title' => '<b>太字</b><script>alert(1)</script>']);

        $html = $this->get(route('admin.notices.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<b>太字</b>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;b&gt;太字&lt;/b&gt;&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    /**
     * 「修正」「削除」は一覧の各行にあり、「削除」は確認のモーダルを開く。確認の文言とボタン
     */
    #[Test]
    public function 修正と削除の列と削除の確認がある(): void
    {
        $this->loginAsAdmin();
        $first = Notice::factory()->create(['created_at' => '2026-11-01 09:00:00']);
        $second = Notice::factory()->create(['created_at' => '2026-11-02 09:00:00']);

        $html = $this->get(route('admin.notices.index'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, '>修正</a>'));
        $this->assertSame(2, substr_count($html, '>削除</button>'));
        foreach ([$first, $second] as $notice) {
            $this->assertStringContainsString('href="'.route('admin.notices.edit', $notice->id).'"', $html);
            $this->assertStringContainsString("deleteId = {$notice->id};", $html);
        }
        // 確認：DELETE で送り、タイトルは x-text で入れる
        $this->assertStringContainsString('action="'.route('admin.notices.delete').'"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('x-text="\'「\' + deleteLabel + \'」を削除します。よろしいですか？\'"', $html);
        $this->assertStringContainsString('公開サイトのトップからも消えます。', $html);
        $this->assertMatchesRegularExpression('#削除する</button>\s*<button[^>]*autofocus[^>]*>キャンセル</button>#u', $html);
        // 背景の押下・Esc で確認だけが閉じる。確認の中はフォーカスを閉じ込める
        $this->assertStringContainsString('x-on:click.self="confirming = false"', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window="confirming = false"', $html);
        $this->assertStringContainsString('x-trap="confirming"', $html);
        // タイトルは JSON の文字列としてだけ渡る（HTMLの属性を壊さない）
        $this->assertStringContainsString('deleteLabel = '.trim((string) Blade::render('@js($v)', ['v' => $first->title])), $html);
    }

    /**
     * 成功の案内は緑の文字、見つからない案内は赤字で、検索欄の下に出る
     */
    #[Test]
    public function 案内と見つからないエラーが検索欄の下に出る(): void
    {
        $this->loginAsAdmin();

        $this->withSession(['list_status' => 'お知らせを作成しました。'])
            ->get(route('admin.notices.index'))
            ->assertSee('<p class="mt-3 w-full text-center text-base text-accent-hover" role="status">お知らせを作成しました。</p>', false)
            ->assertDontSee('role="alert"', false);

        $this->followingRedirects()
            ->from(route('admin.notices.index'))
            ->delete(route('admin.notices.delete'), ['id' => 999999])
            ->assertSee('<ul class="mt-3 w-full text-center text-base text-danger" role="alert">', false)
            ->assertSee('お知らせが見つかりません。');
    }

    /**
     * 作成日は日本時間のその日だけが見つかる（前日 23:59 と当日 00:00 の境界）
     */
    #[Test]
    public function 作成日は日本時間の境界で絞り込まれる(): void
    {
        $this->loginAsAdmin();
        $before = Notice::factory()->create(['created_at' => '2026-11-07 23:59:59']);
        $start = Notice::factory()->create(['created_at' => '2026-11-08 00:00:00']);
        $end = Notice::factory()->create(['created_at' => '2026-11-08 23:59:59']);
        $after = Notice::factory()->create(['created_at' => '2026-11-09 00:00:00']);

        $this->assertSame([$end->id, $start->id], $this->searchIds(['date' => '2026-11-08']));
        $this->assertSame([$before->id], $this->searchIds(['date' => '2026-11-07']));
        $this->assertSame([$after->id], $this->searchIds(['date' => '2026-11-09']));
    }

    /**
     * 作成日が空・指定なしのときは全件が表示される
     */
    #[Test]
    public function 空で検索すると全件が表示される(): void
    {
        $this->loginAsAdmin();
        $old = Notice::factory()->create(['created_at' => '2026-11-01 09:00:00']);
        $new = Notice::factory()->create(['created_at' => '2026-11-02 09:00:00']);

        $this->assertSame([$new->id, $old->id], $this->searchIds(['date' => '']));
        $this->assertSame([$new->id, $old->id], $this->searchIds([]));
    }

    /**
     * 検索後も入力した日付が検索欄に残り、ページ送りのリンクにも検索条件が付く。リセットは全件の一覧
     */
    #[Test]
    public function 検索条件は検索欄とページ送りに残る(): void
    {
        $this->loginAsAdmin();
        for ($i = 0; $i < 11; $i++) {
            Notice::factory()->create(['created_at' => '2026-11-08 09:00:00']);
        }
        Notice::factory()->create(['created_at' => '2026-11-05 09:00:00']);

        $html = $this->get(route('admin.notices.search', ['date' => '2026-11-08']))->assertOk()->getContent();

        $this->assertStringContainsString('value="2026-11-08"', $html);
        $this->assertStringContainsString(e(route('admin.notices.search', ['date' => '2026-11-08', 'page' => 2])), $html);
        $this->assertStringContainsString('href="'.route('admin.notices.index').'"', $html);

        // 2ページ目でも条件が保たれ、その日の残り1件だけが出る
        $second = $this->get(route('admin.notices.search', ['date' => '2026-11-08', 'page' => 2]))->assertOk();
        $this->assertCount(1, $second->viewData('notices')->items());
    }

    /**
     * 該当がないときは案内を出し、表とページ送りは出さない
     */
    #[Test]
    public function 検索で該当がないときは案内を出す(): void
    {
        $this->loginAsAdmin();
        Notice::factory()->create(['created_at' => '2026-11-08 09:00:00']);

        $response = $this->get(route('admin.notices.search', ['date' => '2000-01-01']));

        $response->assertOk()->assertSee('該当するお知らせはありません。');
        $response->assertDontSee('<table', false);
        $response->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 作成日に日付でない値・実在しない日付を送ると、一覧に戻してエラーを出す。500にならない
     */
    #[Test]
    public function 不正な作成日は一覧に戻りエラーが表示される(): void
    {
        $this->loginAsAdmin();
        Notice::factory()->create();

        foreach (['abc', '2026-02-30', '2026/11/08', ['2026-11-08'], ['x']] as $date) {
            $this->get(route('admin.notices.search', ['date' => $date]))->assertRedirect(route('admin.notices.index'));
        }

        $this->get(route('admin.notices.search', ['date' => 'abc']));
        $this->get(route('admin.notices.index'))->assertOk()->assertSee('作成日は日付で入力してください。')->assertSee('role="alert"', false);
    }

    /**
     * エラーのあとの検索欄には、不正な日付を戻さない
     */
    #[Test]
    public function エラーのあとの検索欄の日付は空になる(): void
    {
        $this->loginAsAdmin();

        $html = $this->followingRedirects()->get(route('admin.notices.search', ['date' => 'abc']))->assertOk()->getContent();

        $this->assertStringContainsString('作成日は日付で入力してください。', $html);
        $this->assertStringNotContainsString('value="abc"', $html);
    }

    /**
     * サイドメニューの「お知らせ一覧」は、一覧・検索・修正ページで現在のページになる（作成ページではならない）
     */
    #[Test]
    public function サイドメニューのお知らせ一覧は一覧と検索と修正ページで現在のページになる(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create();

        foreach ([route('admin.notices.index'), route('admin.notices.search', ['date' => '2026-11-08']), route('admin.notices.edit', $notice->id)] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertSame(1, preg_match_all('#aria-current="page"#', $html), $url);
            $this->assertMatchesRegularExpression('#class="[^"]*bg-admin-bar-end font-bold[^"]*" href="'.preg_quote(route('admin.notices.index'), '#').'" aria-current="page">お知らせ一覧</a>#u', $html, $url);
        }
    }

    /**
     * サイドメニューの「お知らせ作成」は作成ページだけで現在のページになる
     */
    #[Test]
    public function サイドメニューのお知らせ作成は作成ページで現在のページになる(): void
    {
        $this->loginAsAdmin();

        $html = $this->get(route('admin.notices.create'))->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('#aria-current="page"#', $html));
        $this->assertMatchesRegularExpression('#class="[^"]*bg-admin-bar-end font-bold[^"]*" href="'.preg_quote(route('admin.notices.create'), '#').'" aria-current="page">お知らせ作成</a>#u', $html);
    }

    /**
     * AdminNav の2つのリンクは route() の URL で、active のパターンが決められたとおり
     */
    #[Test]
    public function admin_navのお知らせのリンクと選択中のパターン(): void
    {
        $links = [];
        foreach (AdminNav::groups() as $group) {
            foreach ($group['links'] as $link) {
                $links[$link['label']] = $link;
            }
        }

        $this->assertSame(route('admin.notices.index'), $links['お知らせ一覧']['url']);
        $this->assertSame(['admin/notices', 'admin/notices/search', 'admin/notices/edit/*'], $links['お知らせ一覧']['active']);
        $this->assertSame(route('admin.notices.create'), $links['お知らせ作成']['url']);
        $this->assertSame(['admin/notices/create'], $links['お知らせ作成']['active']);
    }

    /**
     * 管理画面トップの「お知らせ一覧」「お知らせ作成」のリンクは、それぞれのルートを指す
     */
    #[Test]
    public function 管理画面トップのお知らせのリンクは各ページを指す(): void
    {
        $this->loginAsAdmin();

        $this->get(route('admin.home'))->assertOk()
            ->assertSee('href="'.route('admin.notices.index').'">お知らせ一覧</a>', false)
            ->assertSee('href="'.route('admin.notices.create').'">お知らせ作成</a>', false);
    }

    /**
     * 処理の最初と最後にデバッグログを出す（一覧・検索）
     */
    #[Test]
    public function 一覧と検索の最初と最後にログを出す(): void
    {
        $this->loginAsAdmin();
        Log::spy();

        $this->get(route('admin.notices.index'));
        $this->get(route('admin.notices.search', ['date' => '2026-11-08']));

        foreach (['index', 'search'] as $method) {
            Log::shouldHaveReceived('debug')->with("AdminNoticeController@{$method} start")->once();
            Log::shouldHaveReceived('debug')->with("AdminNoticeController@{$method} end")->once();
        }
    }
}
