<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * カートページ（GET /cart）のテスト
 */
class CartPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 商品を作る
     */
    private function makeItem(string $name, int $weight, int $price): Item
    {
        return Item::factory()->create([
            'name' => $name,
            'weight' => $weight,
            'price' => $price,
            'img_url' => 'image/product/potato-card.webp',
            'is_reserved_only' => false,
        ]);
    }

    /**
     * 未ログインはログインページへ移動し、ログインするとカートページに戻る
     */
    #[Test]
    public function 未ログインはログインページへ移動しログイン後にカートへ戻る(): void
    {
        User::factory()->create(['email' => 'cart@example.com', 'password' => bcrypt('password123')]);

        $this->get(route('shop.cart'))->assertRedirect(route('login'));

        // ログインページを開き、ログインする
        $this->get(route('login'))->assertOk();
        $this->post(route('login'), ['email' => 'cart@example.com', 'password' => 'password123'])
            ->assertRedirect(route('shop.cart'));
    }

    /**
     * カートが空のときの表示（タイトル・見出し・空の案内・ヘッダー・フッター）
     */
    #[Test]
    public function カートが空のときは案内と買い物を続けるだけを表示する(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('shop.cart'));

        $response->assertOk();
        $response->assertViewIs('shop.cart');
        $response->assertSee('<title>カート | Shioya Farm</title>', false);
        $response->assertSee('カートに入っている商品');
        $response->assertSee('カートに商品はありません');
        $response->assertSee('買い物を続ける');
        $response->assertSee('href="'.route('shop.items.index').'"', false);
        $response->assertDontSee('小計');
        $response->assertDontSee('購入する');
        $response->assertDontSee('削除');
        $response->assertSee('<footer', false);
    }

    /**
     * カートページのヘッダーは「カート」を出さない
     */
    #[Test]
    public function ヘッダーはカートを出さずマイページとログアウトを出す(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('shop.cart'));

        $response->assertSeeInOrder(['お知らせ', 'しおや農園について', 'マイページ', 'ログアウト']);
        $header = $response->getContent();
        $this->assertStringNotContainsString('>カート</', $header);
    }

    /**
     * 商品の画像・名前と重さ・単価・数量・金額・削除、小計、購入する、買い物を続けるを表示する
     */
    #[Test]
    public function カートの商品と小計を表示する(): void
    {
        $potato = $this->makeItem('じゃがいも', 2, 330);
        $onion = $this->makeItem('玉ねぎ', 5, 795);
        $carrot = $this->makeItem('人参', 2, 418);

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['cart' => [$potato->id => 2, $onion->id => 1, $carrot->id => 1]])
            ->get(route('shop.cart'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'じゃがいも 2kg', '330円(税込)', '660円',
            '玉ねぎ 5kg', '795円(税込)', '795円',
            '人参 2kg', '418円(税込)', '418円',
            '小計', '1,873円',
            '購入する',
            '買い物を続ける',
        ]);
        $response->assertSee('alt="じゃがいも 2kg"', false);
        $response->assertSee(asset('storage/image/product/potato-card.webp'), false);
        $response->assertSee('value="2"', false);
        $response->assertSee('min="1"', false);
        $response->assertSee('max="10"', false);
        $response->assertSee('>個<', false);
        $response->assertSee('削除');
        $response->assertDontSee('カートに商品はありません');
        // 購入機能ができるまでの仮リンク
        $this->assertMatchesRegularExpression('#<a\s[^>]*href="\#"[^>]*>購入する</a>#s', $response->getContent());
    }

    /**
     * 3桁区切りの金額（1,000円以上）を表示する
     */
    #[Test]
    public function 金額は3桁区切りで表示する(): void
    {
        $item = $this->makeItem('じゃがいも', 10, 1320);

        $this->actingAs(User::factory()->create())
            ->withSession(['cart' => [$item->id => 3]])
            ->get(route('shop.cart'))
            ->assertSee('1,320円(税込)')
            ->assertSee('3,960円')
            ->assertSee('小計');
    }

    /**
     * DBに存在しない商品は表示せず、小計にも含めず、エラーにもしない
     */
    #[Test]
    public function 存在しない商品は表示も計算もせずエラーにしない(): void
    {
        $item = $this->makeItem('じゃがいも', 2, 330);

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['cart' => [$item->id => 1, 99999 => 5]])
            ->get(route('shop.cart'));

        $response->assertOk();
        $response->assertSee('じゃがいも 2kg');
        $response->assertSee('330円');
        $response->assertSeeInOrder(['小計', '330円']);
    }

    /**
     * カートに入れた後で予約限定になった商品は表示も計算もせず、セッションからも取り除く
     */
    #[Test]
    public function 予約限定になった商品は表示も計算もせずセッションから取り除く(): void
    {
        $item = $this->makeItem('じゃがいも', 2, 330);
        $reserved = Item::factory()->create(['name' => '北の恵みセット', 'weight' => 5, 'price' => 5000, 'is_reserved_only' => true, 'reservation_limit' => 200]);

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['cart' => [$item->id => 1, $reserved->id => 2]])
            ->get(route('shop.cart'));

        $response->assertOk();
        $response->assertSee('じゃがいも 2kg');
        $response->assertDontSee('北の恵みセット');
        $response->assertSeeInOrder(['小計', '330円']);
        $this->assertSame([$item->id => 1], session('cart'));
    }

    /**
     * セッションの商品が全部存在しないときは空のカートと同じ表示になる
     */
    #[Test]
    public function 全部存在しない商品のときは空の表示になる(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['cart' => [99999 => 5]])
            ->get(route('shop.cart'))
            ->assertOk()
            ->assertSee('カートに商品はありません')
            ->assertDontSee('小計');
    }

    /**
     * ログアウトするとセッションが破棄され、再度ログインしたカートは空になる
     */
    #[Test]
    public function ログアウトするとカートは空になる(): void
    {
        $item = $this->makeItem('じゃがいも', 2, 330);
        $user = User::factory()->create(['email' => 'cart@example.com', 'password' => bcrypt('password123')]);

        $this->actingAs($user)->withSession(['cart' => [$item->id => 2]])->get(route('shop.cart'))->assertSee('じゃがいも 2kg');

        $this->post(route('logout'));
        $this->post(route('login'), ['email' => 'cart@example.com', 'password' => 'password123']);

        $this->get(route('shop.cart'))->assertSee('カートに商品はありません');
        $this->assertNull(session('cart'));
    }

    /**
     * カートページは Livewire の cart-list コンポーネントを含む
     */
    #[Test]
    public function カートページにカート一覧のコンポーネントが含まれる(): void
    {
        $this->actingAs(User::factory()->create())->get(route('shop.cart'))->assertSeeLivewire('cart-list');

        // 単体でも描画できる
        Livewire::actingAs(User::factory()->create())->test('cart-list')->assertSee('カートに商品はありません');
    }
}
