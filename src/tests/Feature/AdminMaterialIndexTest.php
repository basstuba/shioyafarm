<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminMaterialController;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\Concerns\MaterialTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の資材一覧（admin.materials.index）の表示・認証・強調・モーダルの部品のテスト
 */
class AdminMaterialIndexTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use MaterialTestHelpers;
    use RefreshDatabase;

    /**
     * 4つのルートは決められたコントローラーが受け、管理者の認証と no-store が付く
     */
    #[Test]
    public function ルートは決められたコントローラーが受け管理者の認証とno_storeを要する(): void
    {
        $routes = [
            'admin.materials.index' => ['index', 'GET', '/admin/materials'],
            'admin.materials.store' => ['store', 'POST', '/admin/materials/store'],
            'admin.materials.update' => ['update', 'POST', '/admin/materials/update'],
            'admin.materials.delete' => ['delete', 'DELETE', '/admin/materials/delete'],
        ];

        foreach ($routes as $name => [$method, $httpMethod, $uri]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertSame(AdminMaterialController::class.'@'.$method, $route->getActionName(), $name);
            $this->assertContains($httpMethod, $route->methods(), $name);
            $this->assertSame($uri, '/'.$route->uri(), $name);
            $this->assertContains('auth:admin', $route->gatherMiddleware(), $name);
            $this->assertContains('no-store', $route->gatherMiddleware(), $name);
        }
    }

    /**
     * 未ログインでは、一覧も追加・修正・削除の送信も管理者用のログインページへ移動し、資材は変わらない
     */
    #[Test]
    public function 未ログインではどのルートもログインページへ移動する(): void
    {
        $material = Material::factory()->create(['name' => '元の資材', 'stock' => 10]);

        $this->get(route('admin.materials.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.materials.store'), ['name' => '新規', 'stock' => 1, 'location' => '倉庫', 'threshold' => 1])->assertRedirect(route('admin.login'));
        $this->post(route('admin.materials.update'), ['id' => $material->id, 'name' => '変更', 'stock' => 1, 'location' => '倉庫', 'threshold' => 1])->assertRedirect(route('admin.login'));
        $this->delete(route('admin.materials.delete'), ['id' => $material->id])->assertRedirect(route('admin.login'));

        $this->assertDatabaseCount('materials', 1);
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'name' => '元の資材', 'stock' => 10]);
    }

    /**
     * 会員としてログインしていても、管理者としては未ログインのため入れず、送信も受け付けない
     */
    #[Test]
    public function 会員としてログインしていても管理者のログインページへ移動する(): void
    {
        $material = Material::factory()->create(['name' => '元の資材']);
        $this->loginAsMember();

        $this->get(route('admin.materials.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.materials.store'), ['name' => '新規', 'stock' => 1, 'location' => '倉庫', 'threshold' => 1])->assertRedirect(route('admin.login'));
        $this->post(route('admin.materials.update'), ['id' => $material->id, 'name' => '変更', 'stock' => 1, 'location' => '倉庫', 'threshold' => 1])->assertRedirect(route('admin.login'));
        $this->delete(route('admin.materials.delete'), ['id' => $material->id])->assertRedirect(route('admin.login'));

        $this->assertDatabaseCount('materials', 1);
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'name' => '元の資材']);
    }

    /**
     * 一覧はビュー・タイトル・見出しが出て、Cache-Control: no-store で返る。サイドメニューの「資材一覧」が選択中になる
     */
    #[Test]
    public function 一覧のページは見出しとno_storeとサイドメニューの選択中の表示を持つ(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.materials.index'));

        $response->assertOk()->assertViewIs('admin.materials');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertSee('<title>資材一覧 | 管理画面 | Shioya Farm</title>', false);
        $html = $response->getContent();
        $this->assertStringContainsString('<h1 class="text-center text-[28px] font-normal text-accent-hover lg:text-[32px]">資材一覧</h1>', $html);
        $this->assertMatchesRegularExpression('#class="[^"]*bg-admin-bar-end font-bold[^"]*" href="'.preg_quote(route('admin.materials.index'), '#').'" aria-current="page">資材一覧</a>#u', $html);
        $this->assertSame(1, preg_match_all('#aria-current="page"#', $html));
    }

    /**
     * 資材は登録した順（古い順）に全件表示され、列は 資材名・在庫数・置き場所・基準値・修正・削除。数字は3桁区切り
     */
    #[Test]
    public function 資材が登録順に全件表示され数字は3桁区切りになる(): void
    {
        $this->loginAsAdmin();
        $first = Material::factory()->create(['name' => '資材A', 'stock' => 12345, 'location' => '倉庫1階', 'threshold' => 20000]);
        $second = Material::factory()->create(['name' => '資材B', 'stock' => 40, 'location' => '倉庫2階', 'threshold' => 10]);
        $third = Material::factory()->create(['name' => '資材C', 'stock' => 7, 'location' => '作業小屋', 'threshold' => 0]);

        $response = $this->get(route('admin.materials.index'))->assertOk();

        $this->assertSame([$first->id, $second->id, $third->id], $response->viewData('materials')->pluck('id')->all());
        $response->assertSeeInOrder(['資材名', '在庫数', '置き場所', '基準値', '資材A', '12,345', '倉庫1階', '20,000', '資材B', '資材C'], false);
        $html = $response->getContent();
        foreach (['資材名', '在庫数', '置き場所', '基準値', '修正', '削除'] as $heading) {
            $this->assertMatchesRegularExpression('#<th scope="col"[^>]*>(<span class="sr-only">)?'.$heading.'#u', $html, $heading);
        }
        $this->assertSame(3, preg_match_all('#<button\s+type="button"\s+class="[^"]*text-mail-link[^"]*"#', $html));
        $this->assertSame(3, preg_match_all('#<button\s+type="button"\s+class="[^"]*text-delete[^"]*"#', $html));
    }

    /**
     * 在庫数が基準値を下回った資材だけ、在庫数が赤字の太字になり、資材名の下に「残りわずか」が出る。同じ・上回る・基準値0は強調しない
     */
    #[Test]
    public function 在庫数が基準値を下回った資材だけが強調される(): void
    {
        $this->loginAsAdmin();
        Material::factory()->create(['name' => '箱A', 'stock' => 40, 'threshold' => 10]);
        Material::factory()->create(['name' => '箱B', 'stock' => 9, 'threshold' => 10]);
        Material::factory()->create(['name' => '箱C', 'stock' => 10, 'threshold' => 10]);
        Material::factory()->create(['name' => '箱D', 'stock' => 0, 'threshold' => 0]);
        Material::factory()->create(['name' => '箱E', 'stock' => 12345, 'threshold' => 20000]);

        $html = $this->get(route('admin.materials.index'))->assertOk()->getContent();

        // 残りわずかは箱Bと箱Eの2件だけ（箱B・箱Eの行に出る）
        $this->assertSame(2, substr_count($html, '残りわずか</span>'));
        $this->assertSame(2, substr_count($html, '<span class="font-bold text-danger">'));
        $this->assertMatchesRegularExpression('#箱B\s*<span class="block text-danger">残りわずか</span>#u', $html);
        $this->assertMatchesRegularExpression('#箱E\s*<span class="block text-danger">残りわずか</span>#u', $html);
        $this->assertMatchesRegularExpression('#<span class="font-bold text-danger">9</span>#', $html);
        $this->assertMatchesRegularExpression('#<span class="font-bold text-danger">12,345</span>#', $html);
        foreach (['箱A', '箱C', '箱D'] as $name) {
            $this->assertDoesNotMatchRegularExpression('#'.$name.'\s*<span class="block text-danger">#u', $html, $name);
        }
    }

    /**
     * 資材が1件もないときは、表の代わりに「登録されている資材はありません。」が出る。「資材を追加」は押せる
     */
    #[Test]
    public function 資材がないときは表の代わりに案内が出る(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.materials.index'))->assertOk();

        $response->assertSee('登録されている資材はありません。')->assertSee('資材を追加');
        $this->assertStringNotContainsString('<table', $response->getContent());
        // 削除の確認は表がないので出さない
        $this->assertStringNotContainsString('admin-material-delete-title', $response->getContent());
    }

    /**
     * 追加・修正のモーダルの部品：4項目・プレースホルダー・補足・ボタンの色・数字のキーボード・閉じる
     */
    #[Test]
    public function 追加修正のモーダルの部品が表示される(): void
    {
        $this->loginAsAdmin();

        $html = $this->get(route('admin.materials.index'))->assertOk()->getContent();

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('x-trap.noautofocus="formOpen"', $html);
        foreach (['資材名', '在庫数', '置き場所', '基準値'] as $label) {
            $this->assertStringContainsString('>'.$label.'</label>', $html, $label);
        }
        $this->assertStringContainsString('placeholder="例：化粧箱 5kg用"', $html);
        $this->assertStringContainsString('placeholder="例：作業小屋の棚の上段"', $html);
        $this->assertStringContainsString('在庫数がこの数を下回ると「残りわずか」と表示します。0にすると表示しません。', $html);
        $this->assertSame(2, preg_match_all('#inputmode="numeric"#', $html));
        $this->assertMatchesRegularExpression('#id="material-stock"\s+name="stock"\s+type="text"\s+inputmode="numeric"\s+value="0"#', $html);
        $this->assertMatchesRegularExpression('#id="material-threshold"\s+name="threshold"\s+type="text"\s+inputmode="numeric"\s+value="0"#', $html);
        // 入力欄の枠と文字（#acacac・SPも16px以上）。フォーカスは灰色系の黒い縁
        $this->assertStringContainsString('border-line-strong', $html);
        $this->assertStringContainsString('placeholder:text-line-strong', $html);
        $this->assertStringContainsString('text-base', $html);
        // 「資材を追加」「追加する」は橙の地に ink の文字、「修正する」は青の地に白文字。ホバー・フォーカスで濃くなり影がつく
        $this->assertMatchesRegularExpression('#<button\s+type="button"\s+class="[^"]*bg-primary px-8 text-base text-ink[^"]*hover:bg-primary-dark hover:shadow-button focus-visible:bg-primary-dark focus-visible:shadow-button[^"]*"[^>]*>資材を追加</button>#u', $html);
        $this->assertMatchesRegularExpression('#<button type="submit" class="[^"]*bg-primary text-ink hover:bg-primary-dark focus-visible:bg-primary-dark"[^>]*>追加する</button>#u', $html);
        $this->assertMatchesRegularExpression('#<button type="submit" class="[^"]*bg-mail-link text-white hover:bg-mail-link-hover focus-visible:bg-mail-link-hover"[^>]*>修正する</button>#u', $html);
        $this->assertStringContainsString('hover:shadow-button focus-visible:shadow-button', $html);
        $this->assertStringContainsString('>閉じる</button>', $html);
        // 二重送信の防止
        $this->assertStringContainsString('x-bind:disabled="sending"', $html);
        $this->assertStringContainsString('if (sending) { $event.preventDefault(); return }; sending = true', $html);
        // aria-describedby は、エラーを表示しているときだけエラー文を指す（エラーがないときは基準値の補足だけ）
        $this->assertStringNotContainsString('aria-describedby="material-name-error"', $html);
        $this->assertStringContainsString('aria-describedby="material-threshold-note"', $html);
        $this->assertStringContainsString("x-bind:aria-describedby=\"showErrors && invalid.name ? 'material-name-error' : null\"", $html);
        // 初期の入力欄の値は MaterialFormState の既定値を1か所から渡す（追加を押したときの値も同じ）
        $this->assertStringContainsString('blank: JSON.parse(', $html);
        $this->assertStringContainsString('Object.assign(this, this.blank,', $html);
        // 公開側の部品（橙の縁・クリーム系）は使わない
        $this->assertStringNotContainsString('border-primary', $html);
        $this->assertStringNotContainsString('cream', $html);
        // 閉じた状態で開く（入力の誤りで戻ったときだけ開く）。追加の既定の送り先
        $state = $this->formState($html);
        $this->assertFalse($state['formOpen']);
        $this->assertSame(['create', '', '0', '0'], [$state['mode'], $state['name'], $state['stock'], $state['threshold']]);
    }

    /**
     * 「修正」は今の値を持ってモーダルを開き、「削除」は確認を開く。資材名・置き場所のHTMLの記号は文字のまま扱う
     */
    #[Test]
    public function 修正と削除のボタンは値を安全に渡し_h_t_m_lの記号をそのまま出さない(): void
    {
        $this->loginAsAdmin();
        $material = Material::factory()->create(['name' => '<b>箱</b>', 'stock' => 5, 'location' => '<script>alert(1)</script>', 'threshold' => 2]);

        $html = $this->get(route('admin.materials.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<b>箱</b>', $html);
        // 一覧の表では文字のままエスケープされる
        $this->assertStringContainsString('&lt;b&gt;箱&lt;/b&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        // 修正は openEdit(...)、削除は deleteId・deleteLabel を渡す
        $this->assertStringContainsString('openEdit(', $html);
        $this->assertStringContainsString('deleteId = '.$material->id.';', $html);
        $this->assertStringContainsString('aria-label="&lt;b&gt;箱&lt;/b&gt;を修正"', $html);
        // 削除の確認の文言
        $this->assertStringContainsString('在庫数と置き場所の記録も消えます。', $html);
        $this->assertStringContainsString('」を削除します。よろしいですか？', $html);
        $this->assertStringContainsString('action="'.route('admin.materials.delete').'"', $html);
        $this->assertStringContainsString('削除する</button>', $html);
        $this->assertStringContainsString('キャンセル</button>', $html);
    }

    /**
     * SP：表はカード形式（lg 未満は block・grid）。ボタンは高さ44px（h-11）で全幅、文字は text-base（16px）
     */
    #[Test]
    public function _s_p向けのカード形式とボタンの大きさのクラスを持つ(): void
    {
        $this->loginAsAdmin();
        Material::factory()->create();

        $html = $this->get(route('admin.materials.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<table class="mt-6 block w-full lg:table lg:table-fixed">', $html);
        $this->assertStringContainsString('grid grid-cols-2', $html);
        $this->assertStringContainsString('inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-[30px] bg-primary', $html);
        $this->assertStringContainsString('h-11 w-full rounded-[10px] border bg-white px-4 text-base', $html);
        $this->assertDoesNotMatchRegularExpression('/(?<![:\w-])text-(xs|sm)\b/', $html);
    }

    /**
     * 処理の最初と最後にログを出す
     */
    #[Test]
    public function 処理の最初と最後にログを出す(): void
    {
        $this->loginAsAdmin();
        $material = Material::factory()->create();
        Log::spy();

        $this->get(route('admin.materials.index'));
        $this->post(route('admin.materials.store'), ['name' => '新規', 'stock' => 1, 'location' => '倉庫', 'threshold' => 1]);
        $this->post(route('admin.materials.update'), ['id' => $material->id, 'name' => '変更', 'stock' => 1, 'location' => '倉庫', 'threshold' => 1]);
        $this->delete(route('admin.materials.delete'), ['id' => $material->id]);

        foreach (['index', 'store', 'update', 'delete'] as $method) {
            Log::shouldHaveReceived('debug')->with("AdminMaterialController@{$method} start")->once();
            Log::shouldHaveReceived('debug')->with("AdminMaterialController@{$method} end")->once();
        }
    }
}
