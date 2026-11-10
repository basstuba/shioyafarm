<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminItemController;
use App\Models\Item;
use App\Support\AdminNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Js;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の掲載商品一覧（admin.items.index）の表示・認証・並び順のテスト
 */
class AdminItemIndexTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 商品を作る（予約限定でない・おすすめでない）
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeListed(string $name, int $weight, int $price = 330, array $overrides = []): Item
    {
        return Item::factory()->create(['name' => $name, 'weight' => $weight, 'price' => $price, 'is_reserved_only' => false, 'reservation_limit' => null, 'recommend' => false, ...$overrides]);
    }

    /**
     * 2つのルートは決められたコントローラーが受け、管理者の認証と no-store が付く
     */
    #[Test]
    public function ルートは決められたコントローラーが受け管理者の認証とno_storeを要する(): void
    {
        $routes = [
            'admin.items.index' => [AdminItemController::class.'@index', 'GET', '/admin/items'],
            'admin.items.delete' => [AdminItemController::class.'@delete', 'DELETE', '/admin/items/delete'],
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
    public function 未ログインでは一覧も削除もログインページへ移動する(): void
    {
        $item = $this->makeListed('じゃがいも', 2);

        $this->get(route('admin.items.index'))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.items.delete'), ['id' => $item->id])->assertRedirect(route('admin.login'));
        $this->assertNotSoftDeleted($item);
    }

    /**
     * 会員としてログインしていても、管理者としては未ログインのため入れない
     */
    #[Test]
    public function 会員としてログインしていても管理者のログインページへ移動する(): void
    {
        $item = $this->makeListed('じゃがいも', 2);
        $this->loginAsMember();

        $this->get(route('admin.items.index'))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.items.delete'), ['id' => $item->id])->assertRedirect(route('admin.login'));
        $this->assertNotSoftDeleted($item);
    }

    /**
     * 管理者には、ビュー・タイトル・見出し・6列の表・詳細モーダルが表示され、検索欄とページ送りはない
     */
    #[Test]
    public function 管理者には一覧の各部品が表示される(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed('じゃがいも', 2, 1320);

        $response = $this->get(route('admin.items.index'));

        $response->assertOk();
        $response->assertViewIs('admin.items');
        $response->assertSee('<title>掲載商品一覧 | 管理画面 | Shioya Farm</title>', false);
        $response->assertSeeInOrder([
            'しおや農園直販サイト管理画面',
            '<h1', '掲載商品一覧',
            '商品名', '商品重量（kg）', '商品金額（円・税込）', '詳細', '修正', '削除',
            'じゃがいも', '2', '1,320', '詳細', '修正', '削除',
            '&copy;Shioya Farm',
        ], false);
        $response->assertDontSee('role="search"', false);
        $response->assertDontSee('検索');
        $response->assertDontSee('aria-label="ページ送り"', false);
        // 詳細モーダルの Livewire コンポーネントと、「詳細」ボタンが送るイベント
        $response->assertSee('wire:id', false);
        $response->assertSee("Livewire.dispatch('open-item-detail', { id: {$item->id} })", false);
    }

    /**
     * 削除していない商品が全件出る（ページ送りはなく、10件を超えても全部出る）
     */
    #[Test]
    public function 削除していない商品が全件表示される(): void
    {
        $this->loginAsAdmin();
        for ($i = 1; $i <= 25; $i++) {
            $this->makeListed("商品{$i}", $i);
        }
        $deleted = $this->makeListed('削除済みの商品', 3);
        $deleted->delete();

        $response = $this->get(route('admin.items.index'));

        $this->assertCount(25, $response->viewData('items'));
        $response->assertSee('商品25');
        $response->assertDontSee('削除済みの商品');
    }

    /**
     * 並び順は、商品名ごとにまとまり、まとまりは同じ商品名の最小IDの順、その中は重さの軽い順（同じ重さはID順）
     */
    #[Test]
    public function 商品名ごとにまとまり重さの順に並ぶ(): void
    {
        $this->loginAsAdmin();
        $potato10 = $this->makeListed('じゃがいも', 10);
        $onion5 = $this->makeListed('玉ねぎ', 5);
        $potato2 = $this->makeListed('じゃがいも', 2);
        $onion2 = $this->makeListed('玉ねぎ', 2);
        $carrot2 = $this->makeListed('人参', 2);
        // あとから既存の商品名で別の重さを登録しても、その商品名のまとまりの中に入る
        $potato5 = $this->makeListed('じゃがいも', 5);
        // 同じ商品名・同じ重さは ID 順
        $carrot2b = $this->makeListed('人参', 2);

        $ids = $this->get(route('admin.items.index'))->viewData('items')->pluck('id')->all();

        $this->assertSame([
            $potato2->id, $potato5->id, $potato10->id,
            $onion2->id, $onion5->id,
            $carrot2->id, $carrot2b->id,
        ], $ids);
    }

    /**
     * 金額は3桁ごとにカンマ区切りで表示される
     */
    #[Test]
    public function 金額はカンマ区切りで表示される(): void
    {
        $this->loginAsAdmin();
        $this->makeListed('じゃがいも', 2, 1320);
        $this->makeListed('玉ねぎ', 5, 12345678);

        $this->get(route('admin.items.index'))
            ->assertSee('1,320')
            ->assertSee('12,345,678');
    }

    /**
     * 掲載中の商品がないときは、表の代わりに案内を出す
     */
    #[Test]
    public function 商品がないときは案内を表示する(): void
    {
        $this->loginAsAdmin();

        $this->get(route('admin.items.index'))
            ->assertOk()
            ->assertSee('掲載中の商品はありません。')
            ->assertDontSee('<table', false);

        // すべて削除した場合も同じ
        $this->makeListed('じゃがいも', 2)->delete();
        $this->get(route('admin.items.index'))->assertSee('掲載中の商品はありません。');
    }

    /**
     * 「修正」のリンク先は /admin/items/edit/{商品ID}（ルート名 admin.items.edit。押すと修正ページが開く）
     */
    #[Test]
    public function 修正のリンク先は商品idつきのパスになる(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed('じゃがいも', 2);

        $this->get(route('admin.items.index'))
            ->assertSee('href="'.url('/admin/items/edit/'.$item->id).'"', false);
        $this->get(url('/admin/items/edit/'.$item->id))->assertOk();
    }

    /**
     * 「削除」は確認のモーダルを開く。確認は2行の文言と「削除する」「キャンセル」で、DELETE で商品のIDを送る
     */
    #[Test]
    public function 削除の確認が表示される(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed('じゃがいも', 2);

        $this->get(route('admin.items.index'))
            ->assertSee('role="alertdialog"', false)
            ->assertSee(" を削除します。よろしいですか？'", false)
            ->assertSee('deleteLabel = ', false)
            ->assertSee('公開ページとカートから消えます。過去の注文・予約の記録は残ります。')
            ->assertSee('action="'.route('admin.items.delete').'"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('x-bind:value="deleteId"', false)
            ->assertSee('deleteId = '.$item->id, false)
            ->assertSeeInOrder(['削除する', 'キャンセル']);
    }

    /**
     * 削除の確認の1行目は「{商品名} {重さ}kg を削除します。よろしいですか？」。商品名は x-text（文字として入れる）に渡し、HTML の記号はエスケープされる
     */
    #[Test]
    public function 削除の確認に商品名と重さが入りエスケープされる(): void
    {
        $this->loginAsAdmin();
        $plain = $this->makeListed('じゃがいも', 5);
        $tagged = $this->makeListed('<b>"玉ねぎ"</b>', 10);

        $response = $this->get(route('admin.items.index'));
        $html = $response->getContent();

        // 1行目は Alpine の x-text で、行ごとに押したときの名前と重さを入れる
        $response->assertSee('x-text="deleteLabel + \' を削除します。よろしいですか？\'"', false);
        $this->assertStringContainsString('deleteId = '.$plain->id.'; deleteLabel = '.Js::from('じゃがいも 5kg').'; confirming = true', $html);
        // HTML の記号は属性の中でも解釈されない（生の <b> は出ず、JavaScript の文字列としてエスケープされる）
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('deleteId = '.$tagged->id.'; deleteLabel = '.Js::from('<b>"玉ねぎ"</b> 10kg').'; confirming = true', $html);
        $this->assertStringContainsString('\\u003Cb\\u003E', $html);
    }

    /**
     * 商品名に HTML の記号が含まれていても、文字のまま表示される
     */
    #[Test]
    public function html記号はエスケープされる(): void
    {
        $this->loginAsAdmin();
        $this->makeListed('<b>じゃが</b>', 2);

        $this->get(route('admin.items.index'))
            ->assertDontSee('<b>じゃが</b>', false)
            ->assertSee('&lt;b&gt;じゃが&lt;/b&gt;', false);
    }

    /**
     * 応答はブラウザに保存させない（ログアウト後に戻るボタンで内容が出ないようにする）
     */
    #[Test]
    public function 応答は保存させない(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.items.index'));

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * サイドメニューとトップページの「掲載商品一覧」のリンクは一覧のルートを指し、表示中は強調される
     */
    #[Test]
    public function メニューの掲載商品一覧は一覧のルートを指す(): void
    {
        $this->loginAsAdmin();

        $links = collect(AdminNav::groups())->pluck('links')->flatten(1)->keyBy('label');
        $this->assertSame(route('admin.items.index'), $links['掲載商品一覧']['url']);

        $this->get(route('admin.home'))->assertSee('href="'.route('admin.items.index').'"', false);
        $this->get(route('admin.items.index'))->assertSee('href="'.route('admin.items.index').'"', false);
    }
}
