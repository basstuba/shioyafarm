<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminUserController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の会員一覧（admin.users.index）の表示・認証・ページ送りのテスト
 */
class AdminUserIndexTest extends TestCase
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
            'admin.users.index' => [AdminUserController::class.'@index', 'GET', '/admin/users'],
            'admin.users.search' => [AdminUserController::class.'@search', 'GET', '/admin/users/search'],
            'admin.users.delete' => [AdminUserController::class.'@delete', 'DELETE', '/admin/users/delete'],
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
    public function 未ログインでは一覧も検索も削除もログインページへ移動する(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.users.search'))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.users.delete'), ['id' => 1])->assertRedirect(route('admin.login'));
    }

    /**
     * 会員としてログインしていても、管理者としては未ログインのため入れない
     */
    #[Test]
    public function 会員としてログインしていても管理者のログインページへ移動する(): void
    {
        $this->loginAsMember();

        $this->get(route('admin.users.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.users.search'))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.users.delete'), ['id' => 1])->assertRedirect(route('admin.login'));
    }

    /**
     * 管理者には、ビュー・タイトル・見出し・検索欄・表が表示される
     */
    #[Test]
    public function 管理者には一覧の各部品が表示される(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create(['last_name' => '山田', 'first_name' => '太郎', 'tell' => '09011112222']);

        $response = $this->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertViewIs('admin.users');
        $response->assertSee('<title>会員一覧 | 管理画面 | Shioya Farm</title>', false);
        $response->assertSeeInOrder([
            'しおや農園直販サイト管理画面',
            '<h1', '会員一覧',
            '状態', '氏名', '検索', 'リセット',
            '会員', '山田 太郎', '09011112222', '詳細', 'メール送信',
            '&copy;Shioya Farm',
        ], false);
        $response->assertSee('placeholder="氏名を入力してください"', false);
        $response->assertSee('aria-label="会員の検索"', false);
        $response->assertSee('action="'.route('admin.users.search').'"', false);
        // 詳細モーダルの Livewire コンポーネントと、「詳細」ボタンが送るイベント
        $response->assertSee('wire:id', false);
        $response->assertSee("Livewire.dispatch('open-user-detail', { id: {$user->id} })", false);
    }

    /**
     * 日付とエクスポートは、会員一覧にはない
     */
    #[Test]
    public function 日付の欄とエクスポートはない(): void
    {
        $this->loginAsAdmin();
        User::factory()->create();

        $html = $this->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('type="date"', $html);
        $this->assertStringNotContainsString('エクスポート', $html);
        $this->assertStringNotContainsString(route('admin.export'), $html);
    }

    /**
     * ページタイトルが唯一の h1 で、ヘッダーのタイトルは見出しにしない
     */
    #[Test]
    public function h1は本文のページタイトルだけである(): void
    {
        $this->loginAsAdmin();

        $html = $this->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertMatchesRegularExpression('#<h1 class="[^"]*text-accent-hover[^"]*">会員一覧</h1>#u', $html);
    }

    /**
     * サイドメニューの「会員一覧」は会員一覧のリンクで、一覧と検索結果のページで現在のページになる
     */
    #[Test]
    public function サイドメニューの会員一覧は一覧と検索結果のページで現在のページになる(): void
    {
        $this->loginAsAdmin();

        foreach ([route('admin.users.index'), route('admin.users.search', ['name' => '山田'])] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertSame(1, preg_match_all('#aria-current="page"#', $html), $url);
            $this->assertMatchesRegularExpression('#class="[^"]*bg-admin-bar-end font-bold[^"]*" href="'.preg_quote(route('admin.users.index'), '#').'" aria-current="page">会員一覧</a>#u', $html, $url);
        }
    }

    /**
     * 管理画面トップの「会員一覧」のリンクは、会員一覧のルートを指す
     */
    #[Test]
    public function 管理画面トップの会員一覧のリンクは会員一覧を指す(): void
    {
        $this->loginAsAdmin();

        $this->get(route('admin.home'))->assertOk()->assertSee('href="'.route('admin.users.index').'">会員一覧</a>', false);
    }

    /**
     * 列は状態・氏名・電話番号・詳細・メール送信で、列の幅は注文一覧と同じく割合で指定する
     */
    #[Test]
    public function 表は_table要素で列の見出しが決められた順に並ぶ(): void
    {
        $this->loginAsAdmin();
        User::factory()->create();

        $html = $this->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<th scope="col"[^>]*>状態</th>\s*<th scope="col"[^>]*>氏名</th>\s*<th scope="col"[^>]*>電話番号</th>\s*<th scope="col"[^>]*><span class="sr-only">詳細</span></th>\s*<th scope="col"[^>]*><span class="sr-only">メール送信</span></th>#u', $html);
        $this->assertSame(1, preg_match('#<colgroup>(.*?)</colgroup>#s', $html, $colgroup));
        $this->assertSame(5, preg_match_all('#<col class="lg:w-\[(\d+)%\]">#', $colgroup[1], $widths));
        $this->assertSame(100, array_sum($widths[1]));
        // 状態12%・氏名32%・電話番号26%・詳細12%・メール送信18%
        $this->assertSame(['12', '32', '26', '12', '18'], $widths[1]);
        // 最後の列（メール送信）は、見出しも各行のセルもPCだけ右寄せにする（SPのカードは変えない）
        $this->assertStringContainsString('lg:text-right"><span class="sr-only">メール送信</span>', $html);
        $this->assertMatchesRegularExpression('#<td class="[^"]*\blg:text-right\b[^"]*">\s*<a\s[^>]*>メール送信</a>#u', $html);
        $this->assertSame(1, substr_count($html, 'lg:text-right"><span'));
        // 表の最大幅は注文一覧と同じ960px
        $this->assertStringContainsString('max-w-[960px]', $html);
    }

    /**
     * 状態は、退会していない会員が「会員」、退会済みの会員が「退会済」
     */
    #[Test]
    public function 状態に会員と退会済が表示される(): void
    {
        $this->loginAsAdmin();
        $this->makeUser('会員', '一郎');
        $this->makeWithdrawnUser('退会', '二郎');

        $html = $this->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#状態</span>退会済\s*</td>#u', $html);
        $this->assertMatchesRegularExpression('#状態</span>会員\s*</td>#u', $html);
    }

    /**
     * 新しく登録した会員から順に、退会済みも含めて表示される
     */
    #[Test]
    public function 新しく登録した順に退会済みも含めて表示される(): void
    {
        $this->loginAsAdmin();
        $old = User::factory()->create(['created_at' => '2026-01-01 10:00:00']);
        $withdrawn = User::factory()->create(['created_at' => '2026-03-01 10:00:00']);
        $withdrawn->delete();
        $new = User::factory()->create(['created_at' => '2026-05-01 10:00:00']);

        $users = $this->get(route('admin.users.index'))->assertOk()->viewData('users');

        $this->assertSame([$new->id, $withdrawn->id, $old->id], array_map(fn (User $user): int => $user->id, $users->items()));
    }

    /**
     * 登録日時が同じ会員は、IDの新しい順
     */
    #[Test]
    public function 登録日時が同じならidの降順になる(): void
    {
        $this->loginAsAdmin();
        $first = User::factory()->create(['created_at' => '2026-01-01 10:00:00']);
        $second = User::factory()->create(['created_at' => '2026-01-01 10:00:00']);

        $users = $this->get(route('admin.users.index'))->assertOk()->viewData('users');

        $this->assertSame([$second->id, $first->id], array_map(fn (User $user): int => $user->id, $users->items()));
    }

    /**
     * 11件あるときは1ページ10件で、ページ送りが出る。2ページ目に残りの1件
     */
    #[Test]
    public function 一覧は1ページ10件でページ送りが出る(): void
    {
        $this->loginAsAdmin();
        $users = $this->makeUsers(11);

        $first = $this->get(route('admin.users.index'));
        $first->assertOk()->assertSee('aria-label="ページ送り"', false);
        $this->assertCount(10, $first->viewData('users')->items());
        $this->assertSame($users[10]->id, $first->viewData('users')->items()[0]->id);

        $second = $this->get(route('admin.users.index', ['page' => 2]));
        $this->assertSame([$users[0]->id], array_map(fn (User $user): int => $user->id, $second->viewData('users')->items()));
    }

    /**
     * 10件以下のときは、ページ送りが出ない
     */
    #[Test]
    public function 十件以下のときはページ送りが出ない(): void
    {
        $this->loginAsAdmin();
        $this->makeUsers(10);

        $this->get(route('admin.users.index'))->assertOk()->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 存在しないページ番号は、最終ページを表示する
     */
    #[Test]
    public function 存在しないページ番号は最終ページになる(): void
    {
        $this->loginAsAdmin();
        $users = $this->makeUsers(11);

        $paginator = $this->get(route('admin.users.index', ['page' => 9999]))->assertOk()->viewData('users');

        $this->assertSame(2, $paginator->currentPage());
        $this->assertSame([$users[0]->id], array_map(fn (User $user): int => $user->id, $paginator->items()));
    }

    /**
     * 会員がいないときは、表の代わりに案内を出し、ページ送りも出さない
     */
    #[Test]
    public function 会員がいないときは案内を出す(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.users.index'));

        $response->assertOk()->assertSee('該当する会員はいません。');
        $response->assertDontSee('<table', false);
        $response->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 「メール送信」は退会済みを含むすべての行にあり、リンク先は /admin/mail/user/{会員ID}
     */
    #[Test]
    public function メール送信のリンクはすべての行にある(): void
    {
        $this->loginAsAdmin();
        $member = $this->makeUser('会員', '一郎');
        $withdrawn = $this->makeWithdrawnUser('退会', '二郎');

        $html = $this->get(route('admin.users.index'))->assertOk()->getContent();

        foreach ([$member, $withdrawn] as $user) {
            $this->assertMatchesRegularExpression('#<a\s[^>]*href="'.preg_quote(url('/admin/mail/user/'.$user->id), '#').'"[^>]*>メール送信</a>#u', $html);
        }
        $this->assertSame(2, substr_count($html, '>メール送信</a>'));
    }

    /**
     * 氏名に HTML の記号が含まれていても、文字のまま表示される（タグとして解釈されない）
     */
    #[Test]
    public function 氏名のhtmlの記号はエスケープされる(): void
    {
        $this->loginAsAdmin();
        $this->makeUser('<b>テスト</b>', '"><script>alert(1)</script>');

        $html = $this->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertStringContainsString('&lt;b&gt;テスト&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>テスト</b>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    /**
     * パスワードなどの認証情報は、HTML（data属性・JSONを含む）に出ない
     */
    #[Test]
    public function 認証情報は出力に含まれない(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_secret123',
            'two_factor_secret' => 'two-factor-secret-value',
            'two_factor_recovery_codes' => 'recovery-codes-value',
        ]);

        $html = $this->get(route('admin.users.index'))->assertOk()->getContent();

        foreach ([$user->password, (string) $user->remember_token, 'cus_secret123', 'two-factor-secret-value', 'recovery-codes-value'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }

    /**
     * ブラウザに保存させない（ログアウト後に戻るボタンで内容が出ないようにする）
     */
    #[Test]
    public function キャッシュさせない(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.users.index'))->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
