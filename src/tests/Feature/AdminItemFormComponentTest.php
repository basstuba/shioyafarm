<?php

namespace Tests\Feature;

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * admin-item-form コンポーネント（管理画面の掲載商品の登録・修正フォーム）の出力のテスト
 *
 * 初期値とクラスの分岐は tests/Unit/AdminItemFormTest、ページ全体と登録・修正の動きは AdminItemFormTest で確認している。
 */
class AdminItemFormComponentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ページを通さずに描画するため、ミドルウェアが共有する $errors を空で共有する
     */
    protected function setUp(): void
    {
        parent::setUp();

        view()->share('errors', new ViewErrorBag);
    }

    /**
     * 登録は橙の「登録する」ボタンで、id の hidden は出ない
     */
    #[Test]
    public function 登録は橙の登録するボタンになる(): void
    {
        $html = Blade::render('<x-admin-item-form mode="create" />');

        $this->assertStringContainsString('action="'.route('admin.items.store').'"', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*bg-primary text-ink[^>]*>登録する<\/button>/', $html);
        $this->assertStringNotContainsString('bg-mail-link', $html);
        $this->assertStringNotContainsString('name="id"', $html);
    }

    /**
     * 修正は青の「修正する」ボタンで、商品の id を hidden で送り、今の値が入る
     */
    #[Test]
    public function 修正は青の修正するボタンで今の値が入る(): void
    {
        $item = Item::factory()->create(['name' => 'さつまいも', 'weight' => 5, 'price' => 700, 'recommend' => true, 'is_reserved_only' => false, 'reservation_limit' => null]);

        $html = Blade::render('<x-admin-item-form mode="edit" :item="$item" />', ['item' => $item]);

        $this->assertStringContainsString('action="'.route('admin.items.update').'"', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*bg-mail-link text-white[^>]*>修正する<\/button>/', $html);
        $this->assertStringContainsString('<input type="hidden" name="id" value="'.$item->id.'">', $html);
        $this->assertStringContainsString('value="さつまいも"', $html);
        $this->assertStringContainsString('value="700"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="weight"[^>]*value="5"[^>]*\schecked/', $html);
    }

    /**
     * 検証エラーのある項目は赤い枠線と aria-invalid になる
     */
    #[Test]
    public function エラーのある項目は赤い枠線になる(): void
    {
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag(['name' => '商品名を入力してください']));
        view()->share('errors', $errors);

        $html = Blade::render('<x-admin-item-form mode="create" />');

        $this->assertMatchesRegularExpression('/<input\s+class="[^"]*border-danger[^"]*"\s+id="name"/', $html);
        $this->assertStringContainsString('aria-describedby="name-error"', $html);
        $this->assertStringContainsString('商品名を入力してください', $html);
        $this->assertMatchesRegularExpression('/<input\s+class="[^"]*border-line-strong[^"]*"\s+id="price"/', $html);
    }
}
