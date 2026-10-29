<?php

namespace Tests\Feature;

use App\Models\Delivery_address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * 購入ページ（GET /purchase/{item?}）のテスト
 */
class PurchasePageTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 未ログインはログインページへ移動し、ログインすると購入ページ（カートが空ならカートページ）へ戻る（AC-1, S-1）
     */
    #[Test]
    public function 未ログインはログインページへ移動しログイン後に購入ページへ戻る(): void
    {
        $item = $this->makeItem('人参', 2, 418);
        $user = $this->makeUser();

        $this->get(route('shop.checkout.index', $item))->assertRedirect(route('login'));
        $this->get(route('shop.checkout.index'))->assertRedirect(route('login'));

        // ログイン後は元の購入ページへ
        $this->get(route('shop.checkout.index', $item));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('shop.checkout.index', $item));
    }

    /**
     * カートの商品（画像・商品名・単価・数量・小計）と金額、会員登録の内容が入ったお届け先が表示される（AC-2, S-2）
     */
    #[Test]
    public function カートの中身と初期値が表示される(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $potato = $this->makeItem('じゃがいも', 2, 330);
        $onion = $this->makeItem('玉ねぎ', 5, 795);
        $carrot = $this->makeItem('人参', 2, 418);

        $response = $this->actingAs($user)
            ->withSession(['cart' => [$potato->id => 2, $onion->id => 1, $carrot->id => 1]])
            ->get(route('shop.checkout.index'));

        $response->assertOk()
            ->assertViewIs('shop.purchase')
            ->assertSee('ご注文内容')
            ->assertSee('じゃがいも 2kg')
            ->assertSee('玉ねぎ 5kg')
            ->assertSee('人参 2kg')
            ->assertSee('330円(税込)')
            ->assertSee('660円')
            ->assertSee('storage/image/product/potato-card.webp', false)
            ->assertSeeInOrder(['小計', '1,873円', '送料', '1,850円', '合計', '3,723円'])
            ->assertDontSee('代引手数料')
            ->assertSee('value="塩谷太郎"', false)
            ->assertSee('value="09012345678"', false)
            ->assertSee('value="user_'.$user->id.'"', false)
            ->assertSee('北海道札幌市中央区1-1-1')
            ->assertSee('注文を確定する')
            ->assertSee('商品一覧に戻る')
            ->assertSee('action="'.route('shop.checkout.payment').'"', false);
    }

    /**
     * ヘッダーはカート・購入手続き用のナビ（マイページ・ログアウト）になる
     */
    #[Test]
    public function ヘッダーはカート用のナビになる(): void
    {
        $item = $this->makeItem('人参', 2, 418);

        $this->actingAs($this->makeUser())
            ->get(route('shop.checkout.index', $item))
            ->assertOk()
            ->assertSee('マイページ')
            ->assertSee('ログアウト');
    }

    /**
     * カートが空のときはカートページへ戻される（AC-2, S-3）
     */
    #[Test]
    public function カートが空ならカートページへリダイレクトされる(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('shop.checkout.index'))
            ->assertRedirect(route('shop.cart'));
    }

    /**
     * カートに予約限定・存在しない商品しかないときも、カートページへ戻される
     */
    #[Test]
    public function カートに購入できない商品しかなければカートページへリダイレクトされる(): void
    {
        $reserved = $this->makeReservedItem();

        $this->actingAs($this->makeUser())
            ->withSession(['cart' => [$reserved->id => 1, 9999 => 1]])
            ->get(route('shop.checkout.index'))
            ->assertRedirect(route('shop.cart'));
    }

    /**
     * 商品詳細から来た場合は、その商品1個だけの購入ページになる。カートの中身は表示しない（AC-3, S-4）
     */
    #[Test]
    public function 商品詳細からはその商品1個だけの購入ページが開く(): void
    {
        $this->makeAreas();
        $carrot = $this->makeItem('人参', 2, 418);
        $onion = $this->makeItem('玉ねぎ', 5, 795);

        $this->actingAs($this->makeUser())
            ->withSession(['cart' => [$onion->id => 3]])
            ->get(route('shop.checkout.index', $carrot))
            ->assertOk()
            ->assertSee('人参 2kg')
            ->assertDontSee('玉ねぎ')
            ->assertSeeInOrder(['小計', '418円', '送料', '940円', '合計', '1,358円']);
    }

    /**
     * 予約限定の商品・存在しない商品は404（AC-3, S-5）
     */
    #[Test]
    public function 予約限定と存在しない商品は404(): void
    {
        $reserved = $this->makeReservedItem();
        $user = $this->makeUser();

        $this->actingAs($user)->get(route('shop.checkout.index', $reserved))->assertNotFound();
        $this->actingAs($user)->get(route('shop.checkout.index', 9999))->assertNotFound();
        $this->actingAs($user)->get('/purchase/abc')->assertNotFound();
        $this->actingAs($user)->get('/purchase/0')->assertNotFound();
    }

    /**
     * 形式が不正な商品ID（URL に入れる文字列）
     *
     * @return array<string, array{string}>
     */
    public static function 不正な商品ID(): array
    {
        return [
            '英字' => ['abc'],
            '数字と英字' => ['12abc'],
            '0' => ['0'],
            '先頭が0' => ['01'],
            '小数' => ['1.5'],
            'マイナス' => ['-1'],
            '19桁以上' => ['12345678901234567890'],
            '制御文字のエンコード' => ['%00'],
        ];
    }

    /**
     * 商品IDが数字以外・形式が不正な URL は、TypeError（500）ではなく404になる
     */
    #[Test]
    #[DataProvider('不正な商品ID')]
    public function 不正な商品_i_dは404になる(string $id): void
    {
        $this->actingAs($this->makeUser())->get('/purchase/'.$id)->assertNotFound();
    }

    /**
     * 購入ページのルートには、商品IDの形式（先頭が0でない18桁までの数字）の制約が付いている（グローバルな pattern に頼らない）
     */
    #[Test]
    public function 購入ページのルートに商品_i_dの制約がある(): void
    {
        $route = app('router')->getRoutes()->getByName('shop.checkout.index');

        $this->assertSame('[1-9][0-9]{0,17}', $route->wheres['item']);
    }

    /**
     * /purchase/success は購入ページに吸われず、購入完了のルートに届く
     */
    #[Test]
    public function purchase_successは購入ページに吸われない(): void
    {
        $this->assertSame(url('/purchase/success'), route('shop.checkout.success'));

        // 保存待ちの注文がないのでトップへ戻る（404でも購入ページのリダイレクトでもない）
        $this->actingAs($this->makeUser())
            ->get('/purchase/success')
            ->assertRedirect(route('top.index'));
    }

    /**
     * お届け先住所の選択肢は、会員登録の住所と自分の配送先住所だけ（他人の住所は出ない）（AC-2, AC-4）
     */
    #[Test]
    public function お届け先住所の選択肢は会員登録の住所と自分の配送先住所(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $mine = Delivery_address::factory()->create(['user_id' => $user->id, 'delivery_prefecture' => '東京都', 'delivery_city' => '千代田区1-1']);
        $others = Delivery_address::factory()->create(['delivery_prefecture' => '沖縄県', 'delivery_city' => '那覇市9-9']);
        $item = $this->makeItem('人参', 2, 418);

        $this->actingAs($user)
            ->get(route('shop.checkout.index', $item))
            ->assertOk()
            ->assertSee('value="user_'.$user->id.'"', false)
            ->assertSee('value="delivery_'.$mine->id.'"', false)
            ->assertSee('東京都千代田区1-1')
            ->assertDontSee('value="delivery_'.$others->id.'"', false)
            ->assertDontSee('那覇市9-9');
    }

    /**
     * お届け先住所のセレクトは、SP（縦に並ぶ幅）でも他の入力欄と同じ高さ（h-11）で、幅いっぱいに表示される
     */
    #[Test]
    public function お届け先住所のセレクトは他の入力欄と同じ高さのクラスを持つ(): void
    {
        $item = $this->makeItem('人参', 2, 418);

        $html = $this->actingAs($this->makeUser())->get(route('shop.checkout.index', $item))->getContent();

        preg_match('#<select[^>]*id="address_id"[^>]*>#', $html, $select);
        $this->assertNotEmpty($select);
        $this->assertStringContainsString('h-11', $select[0]);
        $this->assertStringContainsString('w-full', $select[0]);
        // 縦並び（SP）で高さが潰れる flex-1 は md 以上だけに付ける
        $this->assertDoesNotMatchRegularExpression('#[\s"]flex-1[\s"]#', $select[0]);
        $this->assertStringContainsString('md:flex-1', $select[0]);
    }

    /**
     * 支払方法の3つのカードが表示され、初期選択はクレジットカード
     */
    #[Test]
    public function 支払方法の3つのカードが表示される(): void
    {
        $item = $this->makeItem('人参', 2, 418);

        $this->actingAs($this->makeUser())
            ->get(route('shop.checkout.index', $item))
            ->assertOk()
            ->assertSee('お支払い方法を選択してください')
            ->assertSee('クレジットカード')
            ->assertSee('Visa / Master / JCB')
            ->assertSee('銀行振込')
            ->assertSee('銀行口座にお振込み')
            ->assertSee('代金引換')
            ->assertSee('受け取り時にお支払い')
            ->assertSee('credit-card-icon.webp', false)
            ->assertSee('bank-icon.webp', false)
            ->assertSee('box-icon.webp', false)
            ->assertSee('ご注文内容をご確認のうえ、よろしければ「注文を確定する」ボタンを押してください');
    }

    /**
     * カートページの「購入する」と商品詳細の「購入する」が購入ページを指す（AC-2, AC-3）
     */
    #[Test]
    public function カートと商品詳細の購入するボタンが購入ページを指す(): void
    {
        $item = $this->makeItem('人参', 2, 418);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->withSession(['cart' => [$item->id => 1]])
            ->get(route('shop.cart'))
            ->assertSee('href="'.route('shop.checkout.index').'"', false);

        $this->actingAs($user)
            ->get(route('shop.items.show', $item))
            ->assertSee('href="'.route('shop.checkout.index', $item).'"', false);
    }
}
