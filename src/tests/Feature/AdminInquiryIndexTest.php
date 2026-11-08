<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminInquiryController;
use App\Models\Inquiry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面のお問い合わせ一覧（admin.inquiries.index）の表示・認証・ページ送りのテスト
 */
class AdminInquiryIndexTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * お問い合わせを n 件作る（作った順＝ID の昇順。問い合わせ日時は1分ずつ新しくなる）
     *
     * @return list<Inquiry>
     */
    private function makeInquiries(int $count): array
    {
        $inquiries = [];
        for ($i = 0; $i < $count; $i++) {
            $inquiries[] = Inquiry::factory()->create(['created_at' => now()->subHour()->addMinutes($i)]);
        }

        return $inquiries;
    }

    /**
     * 3つのルートは決められたコントローラーが受け、管理者の認証と no-store が付く
     */
    #[Test]
    public function ルートは決められたコントローラーが受け管理者の認証とno_storeを要する(): void
    {
        $routes = [
            'admin.inquiries.index' => [AdminInquiryController::class.'@index', 'GET', '/admin/inquiries'],
            'admin.inquiries.search' => [AdminInquiryController::class.'@search', 'GET', '/admin/inquiries/search'],
            'admin.inquiries.update' => [AdminInquiryController::class.'@update', 'POST', '/admin/inquiries/update'],
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
    public function 未ログインでは一覧も検索もログインページへ移動する(): void
    {
        $this->get(route('admin.inquiries.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.inquiries.search'))->assertRedirect(route('admin.login'));
    }

    /**
     * 会員としてログインしていても、管理者としては未ログインのため入れない
     */
    #[Test]
    public function 会員としてログインしていても管理者のログインページへ移動する(): void
    {
        $this->loginAsMember();

        $this->get(route('admin.inquiries.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.inquiries.search'))->assertRedirect(route('admin.login'));
    }

    /**
     * 管理者には、ビュー・タイトル・見出し・検索欄・表が表示される。応答は no-store
     */
    #[Test]
    public function 管理者には一覧の各部品が表示される(): void
    {
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->create(['last_name' => '山田', 'first_name' => '太郎', 'created_at' => '2026-11-08 10:00:00']);

        $response = $this->get(route('admin.inquiries.index'));

        $response->assertOk();
        $response->assertViewIs('admin.inquiries');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertSee('<title>お問い合わせ一覧 | 管理画面 | Shioya Farm</title>', false);
        $response->assertSeeInOrder([
            'しおや農園直販サイト管理画面',
            '<h1', 'お問い合わせ一覧',
            '対応状況', '問い合わせ日', '検索', 'リセット',
            '対応済', '2026/11/08', '山田 太郎', '詳細', '返信',
            '&copy;Shioya Farm',
        ], false);
        $response->assertSee('aria-label="お問い合わせの検索"', false);
        $response->assertSee('action="'.route('admin.inquiries.search').'"', false);
        $response->assertSee('type="date"', false);
        // 氏名の検索欄・エクスポートはない
        $response->assertDontSee('name="name"', false);
        $response->assertDontSee('エクスポート');
        // 詳細モーダルの Livewire コンポーネントと、「詳細」ボタンが送るイベント
        $response->assertSee('wire:id', false);
        $response->assertSee("Livewire.dispatch('open-inquiry-detail', { id: {$inquiry->id} })", false);
    }

    /**
     * ページタイトルが唯一の h1 で、ヘッダーのタイトルは見出しにしない
     */
    #[Test]
    public function h1は本文のページタイトルだけである(): void
    {
        $this->loginAsAdmin();

        $html = $this->get(route('admin.inquiries.index'))->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('#<h1[ >]#', $html));
    }

    /**
     * 選択欄は何も選ばない選択肢（PC・SPとも「対応済 / 非対応」）と「対応済」「非対応」を持つ
     */
    #[Test]
    public function 選択欄の選択肢は対応済と非対応である(): void
    {
        $this->loginAsAdmin();

        $html = $this->get(route('admin.inquiries.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<select[^>]*id="addressed"[^>]*name="addressed"#', $html);
        $this->assertStringContainsString('<option value="" selected>対応済 / 非対応</option>', $html);
        // SPだけ「すべて」に切り替える仕組み（matchMedia・x-text）と「すべて」の表示はない
        $this->assertStringNotContainsString('すべて', $html);
        $this->assertStringNotContainsString('matchMedia', $html);
        $this->assertStringNotContainsString('x-text="wide', $html);
        $this->assertMatchesRegularExpression('#<option value="1"[^>]*>対応済</option>\s*<option value="0"[^>]*>非対応</option>#u', $html);
    }

    /**
     * サイドメニューの「お問い合わせ一覧」はお問い合わせ一覧のリンクで、一覧と検索結果のページで現在のページになる
     */
    #[Test]
    public function サイドメニューのお問い合わせ一覧は一覧と検索結果のページで現在のページになる(): void
    {
        $this->loginAsAdmin();

        foreach ([route('admin.inquiries.index'), route('admin.inquiries.search', ['addressed' => '1'])] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertSame(1, preg_match_all('#aria-current="page"#', $html), $url);
            $this->assertMatchesRegularExpression('#class="[^"]*bg-admin-bar-end font-bold[^"]*" href="'.preg_quote(route('admin.inquiries.index'), '#').'" aria-current="page">お問い合わせ一覧</a>#u', $html, $url);
        }
    }

    /**
     * 管理画面トップの「お問い合わせ一覧」のリンクは、お問い合わせ一覧のルートを指す
     */
    #[Test]
    public function 管理画面トップのお問い合わせ一覧のリンクは一覧を指す(): void
    {
        $this->loginAsAdmin();

        $this->get(route('admin.home'))->assertOk()->assertSee('href="'.route('admin.inquiries.index').'">お問い合わせ一覧</a>', false);
    }

    /**
     * 列は対応済・問い合わせ日・氏名・詳細・返信で、列の幅は割合で指定する
     */
    #[Test]
    public function 表は_table要素で列の見出しが決められた順に並ぶ(): void
    {
        $this->loginAsAdmin();
        Inquiry::factory()->create();

        $html = $this->get(route('admin.inquiries.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<table', $html);
        $this->assertSame(5, substr_count($html, '<col class="lg:w-['));
        $this->assertSame(1, preg_match('#<thead.*</thead>#su', $html, $head));
        $this->assertSame(5, substr_count($head[0], '<th scope="col"'));
        $this->assertMatchesRegularExpression('#対応済</th>.*問い合わせ日</th>.*氏名</th>.*詳細</span></th>.*返信</span></th>#su', $head[0]);
    }

    /**
     * 新しい順（問い合わせ日時の降順、同時刻はIDの降順）で並ぶ
     */
    #[Test]
    public function 新しいお問い合わせから順に並び同時刻はid降順になる(): void
    {
        $this->loginAsAdmin();
        $old = Inquiry::factory()->create(['created_at' => '2026-11-01 09:00:00']);
        $sameA = Inquiry::factory()->create(['created_at' => '2026-11-05 09:00:00']);
        $sameB = Inquiry::factory()->create(['created_at' => '2026-11-05 09:00:00']);
        // IDは小さいが、問い合わせ日時が新しい
        $newest = Inquiry::factory()->create(['created_at' => '2026-11-07 09:00:00']);

        $ids = array_map(fn (Inquiry $i): int => $i->id, $this->get(route('admin.inquiries.index'))->viewData('inquiries')->items());

        $this->assertSame([$newest->id, $sameB->id, $sameA->id, $old->id], $ids);
    }

    /**
     * 11件あるときは1ページ10件で、ページ送りが出る。2ページ目に残りの1件
     */
    #[Test]
    public function 一覧は1ページ10件でページ送りが出る(): void
    {
        $this->loginAsAdmin();
        $inquiries = $this->makeInquiries(11);

        $first = $this->get(route('admin.inquiries.index'));
        $first->assertOk()->assertSee('aria-label="ページ送り"', false);
        $this->assertCount(10, $first->viewData('inquiries')->items());
        $this->assertSame($inquiries[10]->id, $first->viewData('inquiries')->items()[0]->id);

        $second = $this->get(route('admin.inquiries.index', ['page' => 2]));
        $this->assertSame([$inquiries[0]->id], array_map(fn (Inquiry $i): int => $i->id, $second->viewData('inquiries')->items()));
    }

    /**
     * 10件以下のときは、ページ送りが出ない
     */
    #[Test]
    public function 十件以下のときはページ送りが出ない(): void
    {
        $this->loginAsAdmin();
        $this->makeInquiries(10);

        $this->get(route('admin.inquiries.index'))->assertOk()->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 存在しないページ番号は、最終ページを表示する
     */
    #[Test]
    public function 存在しないページ番号は最終ページになる(): void
    {
        $this->loginAsAdmin();
        $inquiries = $this->makeInquiries(11);

        $paginator = $this->get(route('admin.inquiries.index', ['page' => 9999]))->assertOk()->viewData('inquiries');

        $this->assertSame(2, $paginator->currentPage());
        $this->assertSame([$inquiries[0]->id], array_map(fn (Inquiry $i): int => $i->id, $paginator->items()));
    }

    /**
     * お問い合わせがないときは、表の代わりに案内を出し、ページ送りも出さない
     */
    #[Test]
    public function お問い合わせがないときは案内を出す(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.inquiries.index'));

        $response->assertOk()->assertSee('該当するお問い合わせはありません。');
        $response->assertDontSee('<table', false);
        $response->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 「返信」はすべての行にあり、リンク先は /admin/mail/inquiry/{お問い合わせID}。誰への返信かが名前に入る
     */
    #[Test]
    public function 返信のリンクはすべての行にある(): void
    {
        $this->loginAsAdmin();
        $first = Inquiry::factory()->create(['last_name' => '山田', 'first_name' => '太郎']);
        $second = Inquiry::factory()->addressed()->create(['last_name' => '佐藤', 'first_name' => '花子']);

        $html = $this->get(route('admin.inquiries.index'))->assertOk()->getContent();

        foreach ([[$first, '山田 太郎'], [$second, '佐藤 花子']] as [$inquiry, $name]) {
            $this->assertMatchesRegularExpression(
                '#<a\s[^>]*href="'.preg_quote(route('admin.mail.inquiry', ['inquiry_id' => $inquiry->id]), '#').'"\s+aria-label="'.preg_quote($name, '#').'に返信"[^>]*>返信</a>#u',
                $html,
            );
        }
        $this->assertSame(2, substr_count($html, '>返信</a>'));
        // 青い下線の文字リンク。最後の列は右寄せ
        $this->assertStringContainsString('text-mail-link underline', $html);
        $this->assertStringContainsString('lg:text-right', $html);
    }

    /**
     * 対応済みの行だけ地が admin-shipped で、チェックが入る。未対応の行は白と灰色の交互のまま
     */
    #[Test]
    public function 対応済みの行だけ地が灰色でチェックが入る(): void
    {
        $this->loginAsAdmin();
        Inquiry::factory()->create();
        Inquiry::factory()->addressed()->create();

        $html = $this->get(route('admin.inquiries.index'))->assertOk()->getContent();

        preg_match_all('#<tr\s+class="grid[^"]*"#', $html, $rows);
        $this->assertCount(2, $rows[0]);
        $this->assertSame(1, substr_count(implode('', $rows[0]), ' bg-admin-shipped'));
        $this->assertSame(1, substr_count(implode('', $rows[0]), 'bg-white lg:even:bg-admin-bar'));
        $this->assertSame(1, preg_match_all('#<input\s+class="[^"]*"\s+type="checkbox"\s+aria-label="[^"]*"\s+checked\b#u', $html));
    }

    /**
     * チェックボックスは24px、押せる範囲は高さ44px、名前に氏名と問い合わせ日が入る。保存先は update のルート
     */
    #[Test]
    public function チェックボックスの大きさと名前と保存先(): void
    {
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->create(['last_name' => '山田', 'first_name' => '太郎', 'created_at' => '2026-11-08 10:00:00']);

        $html = $this->get(route('admin.inquiries.index'))->assertOk()->getContent();

        $this->assertStringContainsString('size-6', $html);
        $this->assertStringContainsString('<label class="flex h-11', $html);
        $this->assertStringContainsString('aria-label="山田 太郎（2026/11/08）のお問い合わせの対応済み"', $html);
        $this->assertStringContainsString('fetch('.trim((string) Blade::render('@js($v)', ['v' => route('admin.inquiries.update')])), $html);
        $this->assertStringContainsString('save('.$inquiry->id.', checked)', $html);
        // 保存できなかったときの案内
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString(trim((string) Blade::render('@js($v)', ['v' => '保存できませんでした。もう一度お試しください。'])), $html);
    }

    /**
     * 問い合わせ日は日本時間のその日の日付で表示される（UTC では 2026-11-07 15:30 の時刻は、日本時間では 11/08 になる）
     *
     * アプリは created_at をアプリのタイムゾーン（日本時間）の文字列で保存するため、UTC の時刻を日本時間にしてから入れる。
     * 表示や保存でタイムゾーンを取り違えると、2026/11/07 になって失敗する。
     */
    #[Test]
    public function 問い合わせ日は日本時間で表示される(): void
    {
        $this->loginAsAdmin();
        $utc = CarbonImmutable::parse('2026-11-07T15:30:00Z');
        $this->assertSame('2026/11/07', $utc->format('Y/m/d'));
        $inquiry = Inquiry::factory()->create(['created_at' => $utc->setTimezone((string) config('app.timezone'))]);
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'created_at' => '2026-11-08 00:30:00']);

        $this->get(route('admin.inquiries.index'))->assertOk()->assertSee('2026/11/08')->assertDontSee('2026/11/07');
    }

    /**
     * 氏名・メールアドレスに HTML の記号があっても、文字のまま表示される
     */
    #[Test]
    public function html記号はエスケープされる(): void
    {
        $this->loginAsAdmin();
        Inquiry::factory()->create(['last_name' => '<b>x</b>', 'first_name' => '<script>alert(1)</script>']);

        $html = $this->get(route('admin.inquiries.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt; &lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    /**
     * 検索欄の矢印：選択欄はブラウザ標準の矢印を消し、#acacac（line-strong）の下向きの三角形を重ねる。日付の入力欄は変えない
     */
    #[Test]
    public function 選択欄の矢印は三角形に置き換わる(): void
    {
        $this->loginAsAdmin();

        $html = $this->get(route('admin.inquiries.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<select[^>]*class="[^"]*appearance-none[^"]*pr-10[^"]*"#', $html);
        $this->assertStringContainsString('pointer-events-none absolute top-1/2 right-4', $html);
        $this->assertStringContainsString('bg-line-strong [clip-path:polygon(0_0,100%_0,50%_100%)]', $html);
        // 強制カラー（ハイコントラスト）では、三角形を隠して標準の矢印に戻す
        $this->assertMatchesRegularExpression('#<select[^>]*class="[^"]*forced-colors:appearance-auto[^"]*"#', $html);
        $this->assertMatchesRegularExpression('#<span[^>]*class="[^"]*forced-colors:hidden[^"]*"[^>]*aria-hidden="true"#', $html);
        // appearance-none は選択欄の1か所だけ（日付の入力欄には付けない）
        $this->assertSame(1, substr_count($html, 'appearance-none'));
    }
}
