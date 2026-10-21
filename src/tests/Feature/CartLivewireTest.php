<?php

namespace Tests\Feature;

use App\Livewire\AddToCartButton;
use App\Livewire\CartList;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * カートの Livewire コンポーネント（カートに入れる・数量変更・削除）のテスト
 */
class CartLivewireTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 通常の商品を作る
     */
    private function makeItem(int $price = 330, string $name = 'じゃがいも'): Item
    {
        return Item::factory()->create([
            'name' => $name,
            'weight' => 2,
            'price' => $price,
            'is_reserved_only' => false,
        ]);
    }

    /**
     * 「カートに入れる」を押すと1個追加され、成功のメッセージとモーダルのリンクが出る
     */
    #[Test]
    public function カートに入れるとセッションに追加されモーダルが開く(): void
    {
        $item = $this->makeItem();

        Livewire::actingAs(User::factory()->create())
            ->test(AddToCartButton::class, ['itemId' => $item->id])
            ->assertSet('open', false)
            ->call('addToCart')
            ->assertSet('open', true)
            ->assertSet('added', true)
            ->assertSee('カートに追加しました')
            ->assertSee('カートを見る')
            ->assertSee('買い物を続ける')
            ->assertSee('href="'.route('shop.cart').'"', false)
            ->assertSee('href="'.route('shop.items.index').'"', false);

        $this->assertSame([$item->id => 1], session('cart'));
    }

    /**
     * すでにカートにある商品は数量が1増え、他の商品は変わらない
     */
    #[Test]
    public function すでにある商品は数量が1増える(): void
    {
        $item = $this->makeItem();
        $other = $this->makeItem(795, '玉ねぎ');
        session(['cart' => [$item->id => 2, $other->id => 1]]);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(AddToCartButton::class, ['itemId' => $item->id])
            ->call('addToCart');

        $this->assertSame([$item->id => 3, $other->id => 1], session('cart'));

        // 続けて押すと、さらに1増える
        $component->call('addToCart');
        $this->assertSame([$item->id => 4, $other->id => 1], session('cart'));
    }

    /**
     * 10個入っている商品は追加せず、モーダルに上限のメッセージを出す
     */
    #[Test]
    public function 上限の10個が入っている商品は追加せず上限のメッセージを出す(): void
    {
        $item = $this->makeItem();
        session(['cart' => [$item->id => 10]]);

        Livewire::actingAs(User::factory()->create())
            ->test(AddToCartButton::class, ['itemId' => $item->id])
            ->call('addToCart')
            ->assertSet('open', true)
            ->assertSet('added', false)
            ->assertSee('この商品はカートに10個入っています。1つの商品は10個までです。')
            ->assertSet('rejected', false)
            ->assertSee('カートを見る')
            ->assertSee('買い物を続ける')
            ->assertDontSee('カートに追加しました');

        $this->assertSame([$item->id => 10], session('cart'));
    }

    /**
     * 9個から追加して10個になるまでは追加でき、メッセージは成功のもの（境界値）
     */
    #[Test]
    public function 上限の1つ手前の9個から追加して10個にできる(): void
    {
        $item = $this->makeItem();
        session(['cart' => [$item->id => 9]]);

        Livewire::actingAs(User::factory()->create())
            ->test(AddToCartButton::class, ['itemId' => $item->id])
            ->call('addToCart')
            ->assertSee('カートに追加しました');

        $this->assertSame([$item->id => 10], session('cart'));
    }

    /**
     * 予約限定の商品はアクションを直接呼ばれても追加しない
     */
    #[Test]
    public function 予約限定の商品は追加しない(): void
    {
        $item = Item::factory()->create(['is_reserved_only' => true, 'reservation_limit' => 200]);

        Livewire::actingAs(User::factory()->create())
            ->test(AddToCartButton::class, ['itemId' => $item->id])
            ->call('addToCart')
            ->assertSet('added', false)
            ->assertSee('この商品はカートに入れられません。')
            ->assertSet('rejected', true)
            ->assertDontSee('カートを見る')
            ->assertSee('買い物を続ける');

        $this->assertNull(session('cart'));
    }

    /**
     * 存在しない商品IDは追加しない
     */
    #[Test]
    public function 存在しない商品_i_dは追加しない(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(AddToCartButton::class, ['itemId' => 99999])
            ->call('addToCart')
            ->assertSet('added', false);

        $this->assertNull(session('cart'));
    }

    /**
     * 未ログインのユーザーがアクションを呼んでも追加しない
     */
    #[Test]
    public function 未ログインはカートに入れられない(): void
    {
        $item = $this->makeItem();

        Livewire::test(AddToCartButton::class, ['itemId' => $item->id])
            ->call('addToCart')
            ->assertForbidden();

        $this->assertNull(session('cart'));
    }

    /**
     * 商品IDはブラウザから書き換えられない
     */
    #[Test]
    public function 商品_i_dは書き換えられない(): void
    {
        $item = $this->makeItem();
        $other = $this->makeItem(795, '玉ねぎ');

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs(User::factory()->create())
            ->test(AddToCartButton::class, ['itemId' => $item->id])
            ->set('itemId', $other->id);
    }

    /**
     * 商品詳細ページに「カートに入れる」のコンポーネントがあり、予約限定の商品と未ログインには無い
     */
    #[Test]
    public function 商品詳細ページのカートボタンはログイン中の通常商品にだけある(): void
    {
        $item = $this->makeItem();
        $reserved = Item::factory()->create(['is_reserved_only' => true, 'reservation_limit' => 200]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('shop.items.show', $item))
            ->assertSeeLivewire(AddToCartButton::class)
            ->assertSee('カートに入れる')
            ->assertSee('wire:click="addToCart"', false);
        $this->actingAs($user)->get(route('shop.items.show', $reserved))
            ->assertDontSeeLivewire(AddToCartButton::class);
        auth()->logout();
        $this->get(route('shop.items.show', $item))->assertDontSeeLivewire(AddToCartButton::class);
    }

    /**
     * モーダルは閉じるボタン・外側クリック・Escで閉じられる（初期は非表示）
     */
    #[Test]
    public function モーダルは閉じるボタンと外側のクリックで閉じられる(): void
    {
        $item = $this->makeItem();

        Livewire::actingAs(User::factory()->create())
            ->test(AddToCartButton::class, ['itemId' => $item->id])
            ->assertSee('x-show="$wire.open"', false)
            ->assertSee('x-cloak', false)
            ->assertSee('aria-label="閉じる"', false)
            ->assertSeeHtml('x-on:click="$wire.open = false"')
            ->assertSeeHtml('x-on:keydown.escape.window="$wire.open = false"');
    }

    /**
     * カートページの数量を変更すると、セッションに保存され、金額と小計が更新される
     */
    #[Test]
    public function 数量を変更すると金額と小計が更新される(): void
    {
        $potato = $this->makeItem(330);
        $onion = $this->makeItem(795, '玉ねぎ');
        session(['cart' => [$potato->id => 2, $onion->id => 1]]);

        Livewire::actingAs(User::factory()->create())
            ->test(CartList::class)
            ->assertSee('1,455円')
            ->call('updateQuantity', $potato->id, '5')
            ->assertSee('1,650円')
            ->assertSee('2,445円')
            ->assertHasNoErrors()
            ->call('updateQuantity', $potato->id, '10')
            ->assertSee('3,300円')
            ->assertSee('4,095円');

        $this->assertSame([$potato->id => 10, $onion->id => 1], session('cart'));
    }

    /**
     * 数量が1と10（境界値）は有効
     */
    #[Test]
    public function 数量は1と10を受け付ける(): void
    {
        $item = $this->makeItem();
        session(['cart' => [$item->id => 5]]);

        $component = Livewire::actingAs(User::factory()->create())->test(CartList::class);

        $component->call('updateQuantity', $item->id, '1')->assertHasNoErrors();
        $this->assertSame([$item->id => 1], session('cart'));

        $component->call('updateQuantity', $item->id, '10')->assertHasNoErrors();
        $this->assertSame([$item->id => 10], session('cart'));
    }

    /**
     * 不正な数量はエラーを出し、セッションの数量・金額・小計を変えない
     *
     * @return array<string, array{mixed, string}>
     */
    public static function invalidQuantities(): array
    {
        return [
            '11' => ['11', '商品の数は10個以下にしてください'],
            '0' => ['0', '商品の数は1個以上にしてください'],
            '負の数' => ['-1', '商品の数は1個以上にしてください'],
            '空欄' => ['', '商品の数を入力してください'],
            '空白のみ' => ['  ', '商品の数を入力してください'],
            '小数' => ['1.5', '商品の数は整数で入力してください'],
            '文字' => ['abc', '商品の数は整数で入力してください'],
            '配列' => [['5'], '商品の数を入力してください'],
            '真偽値' => [true, '商品の数を入力してください'],
            'null' => [null, '商品の数を入力してください'],
        ];
    }

    #[Test]
    #[DataProvider('invalidQuantities')]
    public function 不正な数量はエラーを出し数量を変えない(mixed $input, string $message): void
    {
        $item = $this->makeItem(330);
        session(['cart' => [$item->id => 3]]);

        Livewire::actingAs(User::factory()->create())
            ->test(CartList::class)
            ->call('updateQuantity', $item->id, $input)
            ->assertHasErrors(["quantity.{$item->id}"])
            ->assertSee($message.'（数量は3個のままです）')
            ->assertSee('990円')
            ->assertSee('小計');

        $this->assertSame([$item->id => 3], session('cart'));
    }

    /**
     * エラーのたびに入力欄を作り直して保存済みの数量に戻す（同じ値で連続してエラーになっても毎回戻る）
     */
    #[Test]
    public function エラーのたびに入力欄が保存済みの数量に戻る(): void
    {
        $item = $this->makeItem(330);
        session(['cart' => [$item->id => 10]]);
        $key = fn (int $count): string => 'wire:key="cart-quantity-'.$item->id.'-'.$count.'"';

        $component = Livewire::actingAs(User::factory()->create())
            ->test(CartList::class)
            ->assertSeeHtml([$key(0), 'value="10"']);

        $component->call('updateQuantity', $item->id, '11')
            ->assertSeeHtml([$key(1), 'value="10"'])
            ->assertDontSeeHtml($key(0))
            ->assertDontSeeHtml('value="11"')
            ->assertSee('商品の数は10個以下にしてください（数量は10個のままです）');

        // 同じ値でもう一度エラーになっても、入力欄は作り直される
        $component->call('updateQuantity', $item->id, '11')
            ->assertSeeHtml([$key(2), 'value="10"'])
            ->assertDontSeeHtml($key(1));

        // 正しい値にしたときは入力欄を作り直さず、エラーも消える
        $component->call('updateQuantity', $item->id, '4')
            ->assertSeeHtml([$key(2), 'value="4"'])
            ->assertDontSee('数量は10個のままです');

        $this->assertSame([$item->id => 4], session('cart'));
    }

    /**
     * エラーのあと正しい数量を入力するとエラーが消える
     */
    #[Test]
    public function エラーのあと正しい数量を入力するとエラーが消える(): void
    {
        $item = $this->makeItem(330);
        session(['cart' => [$item->id => 3]]);

        Livewire::actingAs(User::factory()->create())
            ->test(CartList::class)
            ->call('updateQuantity', $item->id, '11')
            ->assertHasErrors(["quantity.{$item->id}"])
            ->call('updateQuantity', $item->id, '4')
            ->assertHasNoErrors()
            ->assertSee('1,320円');

        $this->assertSame([$item->id => 4], session('cart'));
    }

    /**
     * カートにない商品の数量変更はカートに追加しない
     */
    #[Test]
    public function カートにない商品の数量変更は追加しない(): void
    {
        $item = $this->makeItem();
        $other = $this->makeItem(795, '玉ねぎ');
        session(['cart' => [$item->id => 1]]);

        Livewire::actingAs(User::factory()->create())
            ->test(CartList::class)
            ->call('updateQuantity', $other->id, '3');

        $this->assertSame([$item->id => 1], session('cart'));
    }

    /**
     * 削除すると商品が消えて小計が更新され、全部削除すると空の表示になる
     */
    #[Test]
    public function 削除すると商品が消えて小計が更新される(): void
    {
        $potato = $this->makeItem(330);
        $onion = $this->makeItem(795, '玉ねぎ');
        session(['cart' => [$potato->id => 2, $onion->id => 1]]);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(CartList::class)
            ->call('remove', $onion->id)
            ->assertDontSee('玉ねぎ 2kg')
            ->assertSee('じゃがいも 2kg')
            ->assertSee('660円');

        $this->assertSame([$potato->id => 2], session('cart'));

        $component->call('remove', $potato->id)
            ->assertSee('カートに商品はありません')
            ->assertDontSee('小計')
            ->assertDontSee('購入する')
            ->assertSee('買い物を続ける');

        $this->assertSame([], session('cart'));
    }

    /**
     * カートにない商品の削除はエラーにならない
     */
    #[Test]
    public function カートにない商品の削除はエラーにならない(): void
    {
        $item = $this->makeItem();
        session(['cart' => [$item->id => 2]]);

        Livewire::actingAs(User::factory()->create())
            ->test(CartList::class)
            ->call('remove', 99999)
            ->assertHasNoErrors();

        $this->assertSame([$item->id => 2], session('cart'));
    }

    /**
     * 「カートに入れる」で追加した商品がカートページに表示される（通しの確認）
     */
    #[Test]
    public function カートに入れた商品がカートページに表示される(): void
    {
        $item = $this->makeItem(330);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(AddToCartButton::class, ['itemId' => $item->id])
            ->call('addToCart')->call('addToCart');

        $this->actingAs($user)->get(route('shop.cart'))
            ->assertSee('じゃがいも 2kg')
            ->assertSee('660円');
    }
}
