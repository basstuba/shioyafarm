<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 商品詳細ページのテスト
 */
class ItemShowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 通常の商品を作る
     */
    private function normalItem(): Item
    {
        return Item::factory()->create([
            'name' => 'じゃがいも',
            'weight' => 10,
            'price' => 1320,
            'img_url' => 'image/product/potato-card.webp',
            'detail' => 'ホクホク食感のじゃがいもです',
            'is_reserved_only' => false,
        ]);
    }

    /**
     * 予約限定の商品を作る
     */
    private function reservedItem(): Item
    {
        return Item::factory()->create([
            'name' => '北の恵みセット',
            'weight' => 5,
            'price' => 5000,
            'is_reserved_only' => true,
            'reservation_limit' => 200,
        ]);
    }

    /**
     * 未ログインでも商品詳細が表示され、タイトル・戻るリンク・フッターがある
     */
    #[Test]
    public function 未ログインでも商品詳細が表示される(): void
    {
        $item = $this->normalItem();

        $response = $this->get(route('shop.items.show', $item));

        $response->assertOk();
        $response->assertViewIs('shop.item_detail');
        $response->assertViewHas('item', fn (Item $viewItem) => $viewItem->is($item));
        $response->assertSee('<title>商品詳細 | Shioya Farm</title>', false);
        $response->assertSee('<footer', false);
        $response->assertSee('href="'.route('shop.items.index').'"', false);
        $response->assertSee('商品一覧に戻る');
    }

    /**
     * 画像・名前と重さ・説明文・価格（3桁区切り）を表示する
     */
    #[Test]
    public function 商品の内容を表示する(): void
    {
        $item = $this->normalItem();

        $response = $this->get(route('shop.items.show', $item));

        $response->assertSee(asset('storage/image/product/potato-card.webp'), false);
        $response->assertSee('alt="じゃがいも 10kg"', false);
        $response->assertSee('じゃがいも 10kg');
        $response->assertSee('ホクホク食感のじゃがいもです');
        $response->assertSee('1,320円(税込)');
    }

    /**
     * 説明文の改行は保たれ（whitespace-pre-line）、HTMLはエスケープされる
     */
    #[Test]
    public function 説明文の改行を保ち_htm_lはエスケープする(): void
    {
        $item = Item::factory()->create(['detail' => "1行目\n<b>2行目</b>", 'is_reserved_only' => false]);

        $response = $this->get(route('shop.items.show', $item));

        $response->assertSee('whitespace-pre-line', false);
        $response->assertSee("1行目\n&lt;b&gt;2行目&lt;/b&gt;", false);
    }

    /**
     * 3桁未満の価格は区切りなしで表示する
     */
    #[Test]
    public function 千円未満の価格はそのまま表示する(): void
    {
        $item = Item::factory()->create(['price' => 330, 'is_reserved_only' => false]);

        $this->get(route('shop.items.show', $item))->assertSee('330円(税込)');
    }

    /**
     * ログイン中の通常商品は「カートに入れる」「購入する」を表示し、「予約する」は出さない
     */
    #[Test]
    public function ログイン中の通常商品はカートと購入のボタンを表示する(): void
    {
        $item = $this->normalItem();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('shop.items.show', $item));

        $response->assertSee('カートに入れる');
        $response->assertSee('購入する');
        $response->assertDontSee('予約する');
        $response->assertDontSee('※ご購入の際は会員登録と');
        // 「カートに入れる」は Livewire のボタン（ページを移動しない）。「購入する」は仮のリンク「#」
        $response->assertSeeLivewire('add-to-cart-button');
        $this->assertMatchesRegularExpression('#<button\s[^>]*wire:click="addToCart"[^>]*>カートに入れる</button>#s', $response->getContent());
        $this->assertMatchesRegularExpression('#<a\s[^>]*href="\#"[^>]*>購入する</a>#s', $response->getContent());
    }

    /**
     * ログイン中の予約限定商品は「予約する」だけを表示し、バッジが付く
     */
    #[Test]
    public function ログイン中の予約限定商品は予約するボタンだけを表示する(): void
    {
        $item = $this->reservedItem();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('shop.items.show', $item));

        $response->assertSee('予約する');
        $response->assertDontSee('カートに入れる');
        $response->assertDontSee('購入する');
        $response->assertSee('>予約限定</span>', false);
        $this->assertMatchesRegularExpression('#<a\s[^>]*href="\#"[^>]*>予約する</a>#s', $response->getContent());
    }

    /**
     * 通常の商品には予約限定のバッジを付けない
     */
    #[Test]
    public function 通常商品には予約限定のバッジを付けない(): void
    {
        $item = $this->normalItem();

        $this->get(route('shop.items.show', $item))->assertDontSee('>予約限定</span>', false);
    }

    /**
     * 未ログインのときは3つのボタンを出さず、会員登録の案内を表示する
     */
    #[Test]
    public function 未ログインのときは会員登録の案内を表示する(): void
    {
        foreach ([$this->normalItem(), $this->reservedItem()] as $item) {
            $response = $this->get(route('shop.items.show', $item));

            $response->assertDontSee('カートに入れる');
            $response->assertDontSee('購入する');
            $response->assertDontSee('予約する');
            $response->assertSee('※ご購入の際は会員登録と');
            $response->assertSee('ログインをお願いします。');
            $pattern = '#<a\s[^>]*href="'.preg_quote(route('register'), '#').'"[^>]*>会員登録</a>#s';
            $this->assertMatchesRegularExpression($pattern, $response->getContent());
        }
    }

    /**
     * ヘッダーのナビがログイン状態で切り替わる
     */
    #[Test]
    public function ヘッダーのナビがログイン状態で切り替わる(): void
    {
        $item = $this->normalItem();

        $this->get(route('shop.items.show', $item))
            ->assertSeeInOrder(['お知らせ', 'しおや農園について', 'ログイン'])
            ->assertDontSee('マイページ');

        $this->actingAs(User::factory()->create())->get(route('shop.items.show', $item))
            ->assertSeeInOrder(['お知らせ', 'しおや農園について', 'カート', 'マイページ', 'ログアウト'])
            ->assertSee('href="'.route('shop.cart').'"', false);
    }

    /**
     * 存在しない商品IDは404になる
     */
    #[Test]
    public function 存在しない商品_i_dは404になる(): void
    {
        $this->get('/items/99999')->assertNotFound();
    }

    /**
     * 数字でない商品IDは404になる
     */
    #[Test]
    public function 数字でない商品_i_dは404になる(): void
    {
        $this->get('/items/abc')->assertNotFound();
        $this->get('/items/1abc')->assertNotFound();
    }

    /**
     * PHPの整数の上限を超える桁数のIDは500にならず404になる
     */
    #[Test]
    public function 桁数が大きすぎる商品_i_dは404になる(): void
    {
        $this->get('/items/99999999999999999999')->assertNotFound();
    }

    /**
     * 先頭が0のIDや0は404になる（/items/0001 で商品1を表示しない）
     */
    #[Test]
    public function 先頭が0の商品_i_dは404になる(): void
    {
        $item = Item::factory()->create();

        $this->get('/items/000'.$item->id)->assertNotFound();
        $this->get('/items/0')->assertNotFound();
    }

    /**
     * 商品一覧の「詳しく見る」から詳細ページへ遷移できる
     */
    #[Test]
    public function 商品一覧の詳しく見るから詳細ページへ遷移できる(): void
    {
        $item = $this->normalItem();

        $index = $this->get(route('shop.items.index'));
        $index->assertSee('href="'.route('shop.items.show', $item).'"', false);

        $this->get(route('shop.items.show', $item))->assertOk();
    }
}
