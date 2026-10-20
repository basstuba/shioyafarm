<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Support\Nav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 商品一覧ページのテスト
 */
class ItemIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 未ログインでも商品一覧が表示され、タイトルが設定される
     */
    #[Test]
    public function 未ログインでも商品一覧が表示される(): void
    {
        $response = $this->get(route('shop.items.index'));

        $response->assertOk();
        $response->assertViewIs('shop.items');
        $response->assertSee('<title>商品一覧 | Shioya Farm</title>', false);
        $response->assertSee('作り手の顔が見える、');
        $response->assertSee('新鮮な畑の恵みを、あなたの食卓へ。');
        $response->assertSee('online-fv-family-photo.webp');
    }

    /**
     * ログイン中でも商品一覧が表示される
     */
    #[Test]
    public function ログイン中でも商品一覧が表示される(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('shop.items.index'));

        $response->assertOk();
        $response->assertViewIs('shop.items');
    }

    /**
     * 商品は品目ごとにまとまり、品目は最小IDの順、品目内は重さの昇順で並ぶ
     */
    #[Test]
    public function 品目ごとにまとめて並び順どおりに表示される(): void
    {
        Item::factory()->create(['name' => '玉ねぎ', 'weight' => 10, 'is_reserved_only' => false]);
        Item::factory()->create(['name' => 'じゃがいも', 'weight' => 5, 'is_reserved_only' => false]);
        Item::factory()->create(['name' => '玉ねぎ', 'weight' => 2, 'is_reserved_only' => false]);
        Item::factory()->create(['name' => 'じゃがいも', 'weight' => 2, 'is_reserved_only' => false]);

        $response = $this->get(route('shop.items.index'));

        $response->assertViewHas('itemGroups', function ($groups) {
            return $groups->keys()->all() === ['玉ねぎ', 'じゃがいも']
                && $groups['玉ねぎ']->pluck('weight')->all() === [2, 10]
                && $groups['じゃがいも']->pluck('weight')->all() === [2, 5];
        });
        $response->assertSeeInOrder([
            '玉ねぎ', '玉ねぎ 2kg', '玉ねぎ 10kg',
            'じゃがいも', 'じゃがいも 2kg', 'じゃがいも 5kg',
        ]);
    }

    /**
     * 商品名・重さ・価格（3桁区切り）・画像・詳しく見るボタンを表示する
     */
    #[Test]
    public function 商品カードの内容を表示する(): void
    {
        $item = Item::factory()->create([
            'name' => 'じゃがいも',
            'weight' => 10,
            'price' => 1320,
            'img_url' => 'image/product/potato-card.webp',
            'is_reserved_only' => false,
        ]);

        $response = $this->get(route('shop.items.index'));

        $response->assertSee('じゃがいも 10kg');
        $response->assertSee('1,320円(税込)');
        $response->assertSee(asset('storage/image/product/potato-card.webp'), false);
        $response->assertSee('alt="じゃがいも 10kg"', false);
        $response->assertSee('aria-label="じゃがいも 10kgの詳細を見る"', false);
        $response->assertSee('詳しく見る');
        $response->assertSee('href="'.route('shop.items.show', $item).'"', false);
    }

    /**
     * 予約限定の商品にだけバッジを表示する
     */
    #[Test]
    public function 予約限定の商品にだけバッジを表示する(): void
    {
        Item::factory()->create(['name' => 'じゃがいも', 'is_reserved_only' => false]);

        $this->get(route('shop.items.index'))->assertDontSee('>予約限定</span>', false);

        Item::factory()->create(['name' => '北の恵みセット', 'is_reserved_only' => true]);

        $this->get(route('shop.items.index'))->assertSee('>予約限定</span>', false);
    }

    /**
     * 未ログインのときは会員登録の案内を表示する
     */
    #[Test]
    public function 未ログインのときは会員登録の案内を表示する(): void
    {
        $response = $this->get(route('shop.items.index'));

        $response->assertSee('※ご購入の際は会員登録と');
        $response->assertSee('ログインをお願いします。');
        $response->assertSee('href="'.route('register').'"', false);
    }

    /**
     * ログイン中は会員登録の案内を表示しない
     */
    #[Test]
    public function ログイン中は会員登録の案内を表示しない(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('shop.items.index'));

        $response->assertDontSee('※ご購入の際は会員登録と');
        $response->assertDontSee('href="'.route('register').'"', false);
    }

    /**
     * 未ログインとログイン中でヘッダーのナビが切り替わる
     */
    #[Test]
    public function ヘッダーのナビがログイン状態で切り替わる(): void
    {
        $this->get(route('shop.items.index'))
            ->assertSeeInOrder(['お知らせ', 'しおや農園について', 'ログイン'])
            ->assertDontSee('マイページ');

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('shop.items.index'))
            ->assertSeeInOrder(['お知らせ', 'しおや農園について', 'カート', 'マイページ', 'ログアウト']);
    }

    /**
     * 商品が0件のときはメッセージを表示し、ご案内は表示する
     */
    #[Test]
    public function 商品が0件のときはメッセージを表示する(): void
    {
        $response = $this->get(route('shop.items.index'));

        $response->assertOk();
        $response->assertSee('現在販売中の商品はありません。');
        $response->assertDontSee('詳しく見る');
        $response->assertSee('送料について');
    }

    /**
     * 商品があるときは0件のメッセージを表示しない
     */
    #[Test]
    public function 商品があるときは0件のメッセージを表示しない(): void
    {
        Item::factory()->create();

        $this->get(route('shop.items.index'))->assertDontSee('現在販売中の商品はありません。');
    }

    /**
     * ご案内の4項目と文章を表示し、各項目は独立して開閉できる（details要素）
     */
    #[Test]
    public function ご案内の4項目と文章を表示する(): void
    {
        $response = $this->get(route('shop.items.index'));

        $response->assertSeeInOrder(['送料について', '支払方法について', 'お届けについて', '返品について']);
        $response->assertSee('送料は別途ご負担となります。');
        $response->assertSee('送料は提携運送会社の価格に準じます。');
        $response->assertSee('クレジットカード・銀行振込・代金引換をご利用いただけます。');
        $response->assertSee('代金引換の場合は、別途手数料'.number_format(config('shipping.cod_fee')).'円（税込）がかかります。');
        $response->assertSee('予約限定商品は、クレジットカードまたは銀行振込による先払いとなります。');
        $response->assertSee('お支払いの確定後、3〜5営業日以内に発送いたします（代金引換はご注文の確定後）。');
        $response->assertSee('収穫状況により、発送が前後する場合がございます。');
        $response->assertSee('北の恵みセットは9月中の発送となります。');
        $response->assertSee('発送元：北海道架空郡架空町567-34');
        $response->assertSee('商品の特性上、お客様のご都合による返品はお受けできません。');
        $response->assertSee('万が一不良品が届いた場合は、商品の到着後3日以内にご連絡ください。交換または返金にて対応いたします。');
        $this->assertSame(4, substr_count($response->getContent(), '<details'));
        // 初期表示はすべて閉じている
        $response->assertDontSee('<details open', false);
    }

    /**
     * トップページの「すべての商品を見る」ボタンが商品一覧を指す
     */
    #[Test]
    public function トップページのすべての商品を見るボタンが商品一覧を指す(): void
    {
        $response = $this->get(route('top.index'));

        // ヘッダーのリンクと取り違えないよう、ボタンの href と文言を同じ a 要素の中で確認する
        $pattern = '#<a\s[^>]*href="'.preg_quote(route('shop.items.index'), '#').'"[^>]*>すべての商品を見る</a>#s';
        $this->assertMatchesRegularExpression($pattern, $response->getContent());
    }

    /**
     * ヘッダーの「オンラインショップ」が商品一覧を指す
     */
    #[Test]
    public function トップ用ナビのオンラインショップは商品一覧を指す(): void
    {
        $nav = collect(Nav::top())->firstWhere('label', 'オンラインショップ');

        $this->assertSame(route('shop.items.index'), $nav['url']);
    }

    /**
     * ボタンのホバー時の文字色は濃い茶色のトークンで、白ではない
     */
    #[Test]
    public function ボタンのホバー時の文字色は濃い茶色になる(): void
    {
        Item::factory()->create();

        $content = $this->get(route('shop.items.index'))->getContent();

        $this->assertStringContainsString('hover:text-action-hover-text', $content);
        $this->assertStringContainsString('focus-visible:text-action-hover-text', $content);
        $this->assertStringNotContainsString('hover:text-white', $content);
    }

    /**
     * ご案内の本文は文と文の間を8px（gap-2）空ける
     */
    #[Test]
    public function ご案内の本文は文の間が8px空く(): void
    {
        $content = $this->get(route('shop.items.index'))->getContent();

        $this->assertSame(4, substr_count($content, 'flex flex-col gap-2 rounded-b-[10px]'));
    }
}
