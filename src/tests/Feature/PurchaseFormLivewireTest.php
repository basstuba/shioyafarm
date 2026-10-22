<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Livewire\PurchaseForm;
use App\Models\Delivery_address;
use App\Models\Item;
use App\Models\User;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\PendingOrder;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * 購入ページの Livewire コンポーネント（数量・住所・支払方法・住所追加モーダル）のテスト
 */
class PurchaseFormLivewireTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * シナリオ S-2 のカート（じゃがいも2kg×2、玉ねぎ5kg×1、人参2kg×1）を作り、会員でログインしたコンポーネントを返す
     *
     * @return array{User, array<string, Item>}
     */
    private function cartScenario(): array
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $items = [
            'potato' => $this->makeItem('じゃがいも', 2, 330),
            'onion' => $this->makeItem('玉ねぎ', 5, 795),
            'carrot' => $this->makeItem('人参', 2, 418),
        ];
        session(['cart' => [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1]]);

        return [$user, $items];
    }

    /**
     * 初期表示：会員登録の内容が入り、金額は小計1,873円・送料1,850円・合計3,723円（S-2）
     */
    #[Test]
    public function 初期表示は会員登録の内容と北海道の送料(): void
    {
        [$user, $items] = $this->cartScenario();

        Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->assertSet('source', 'cart')
            ->assertSet('quantities', [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1])
            ->assertSet('deliveryName', '塩谷太郎')
            ->assertSet('deliveryTell', '09012345678')
            ->assertSet('addressId', 'user_'.$user->id)
            ->assertSet('paymentMethod', 'card')
            ->assertViewHas('summary', function ($summary): bool {
                return $summary->subtotal === 1873 && $summary->shippingFee === 1850 && $summary->codFee === 0 && $summary->total === 3723;
            })
            ->assertSee('北海道札幌市中央区1-1-1');
    }

    /**
     * 数量を変えると、再読み込みなしで小計・送料・合計が更新され、カートの数量も変わる（S-6）
     */
    #[Test]
    public function 数量を変えると金額が更新されカートにも反映される(): void
    {
        [$user, $items] = $this->cartScenario();

        Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->call('updateQuantity', $items['potato']->id, '3')
            ->assertSet('quantities.'.$items['potato']->id, 3)
            ->assertViewHas('summary', function ($summary): bool {
                // 13kg → 120サイズ
                return $summary->subtotal === 2203 && $summary->shippingFee === config('shipping.fees.120.北海道') && $summary->total === 4053;
            })
            ->assertSee('2,203円')
            ->assertSee('4,053円');

        $this->assertSame(3, session('cart')[$items['potato']->id]);
    }

    /**
     * 商品詳細から来た場合、数量を変えてもカートは変わらない（AC-11, S-18）
     */
    #[Test]
    public function 商品詳細から来た場合は数量を変えてもカートは変わらない(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $carrot = $this->makeItem('人参', 2, 418);
        $onion = $this->makeItem('玉ねぎ', 5, 795);
        session(['cart' => [$onion->id => 1, $carrot->id => 4]]);

        Livewire::actingAs($user)
            ->test(PurchaseForm::class, ['itemId' => $carrot->id])
            ->assertSet('source', 'item')
            ->assertSet('quantities', [$carrot->id => 1])
            ->call('updateQuantity', $carrot->id, '2')
            ->assertSet('quantities.'.$carrot->id, 2)
            ->assertViewHas('summary', fn ($summary): bool => $summary->subtotal === 836);

        $this->assertSame([$onion->id => 1, $carrot->id => 4], session('cart'));
    }

    /**
     * 数量が範囲外・数字以外のときはエラーになり、数量も金額も変わらない（S-14）
     */
    #[Test]
    public function 範囲外の数量はエラーで金額が変わらない(): void
    {
        [$user, $items] = $this->cartScenario();
        $potatoId = $items['potato']->id;

        foreach (['0', '11', '', 'abc', '1.5'] as $input) {
            Livewire::actingAs($user)
                ->test(PurchaseForm::class)
                ->call('updateQuantity', $potatoId, $input)
                ->assertHasErrors(['quantities.'.$potatoId])
                ->assertSet('quantities.'.$potatoId, 2)
                ->assertViewHas('summary', fn ($summary): bool => $summary->subtotal === 1873);
        }

        $this->assertSame(2, session('cart')[$potatoId]);
    }

    /**
     * 数量の境界値：1個と10個は受け付ける
     */
    #[Test]
    public function 数量の境界値は一個と十個まで受け付ける(): void
    {
        [$user, $items] = $this->cartScenario();
        $potatoId = $items['potato']->id;

        Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->call('updateQuantity', $potatoId, '1')
            ->assertHasNoErrors()
            ->assertSet('quantities.'.$potatoId, 1)
            ->call('updateQuantity', $potatoId, '10')
            ->assertHasNoErrors()
            ->assertSet('quantities.'.$potatoId, 10)
            ->assertSee('max="10"', false)
            ->assertSee('min="1"', false);
    }

    /**
     * クライアントが読み込んだ商品以外のキーを数量に追加しても、取り除かれる（送信される items[] に載らない）
     */
    #[Test]
    public function 読み込んでいない商品の数量は追加できない(): void
    {
        [$user, $items] = $this->cartScenario();
        $other = $this->makeItem('その他', 2, 100);
        $expected = [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1];

        $component = Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->set('quantities.'.$other->id, 3)
            ->assertSet('quantities', $expected)
            ->set('quantities.999999', 1)
            ->assertSet('quantities', $expected)
            ->set('quantities', $expected + [$other->id => 5])
            ->assertSet('quantities', $expected);

        // 画面に出る商品（送信される items[]）は読み込んだ商品だけ
        $component->assertViewHas('lines', fn ($lines): bool => $lines->pluck('item.id')->sort()->values()->all() === collect($expected)->keys()->sort()->values()->all());
        $this->assertStringNotContainsString('value="'.$other->id.'"', $component->html());
    }

    /**
     * 読み込んだ商品のIDはクライアントから書き換えられない
     */
    #[Test]
    public function 読み込んだ商品のidは書き換えられない(): void
    {
        [$user] = $this->cartScenario();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->set('itemIds', [1, 2, 3, 999]);
    }

    /**
     * 「注文を確定する」は送信中に押せなくする（二重送信の防止）
     */
    #[Test]
    public function 注文を確定するボタンは送信中に押せなくなる(): void
    {
        [$user] = $this->cartScenario();

        $html = Livewire::actingAs($user)->test(PurchaseForm::class)->html();

        $this->assertStringContainsString('x-on:submit="submitting = true"', $html);
        $this->assertMatchesRegularExpression('#<button[^>]*x-bind:disabled="submitting \|\| false"[^>]*>\s*注文を確定する#', $html);
    }

    /**
     * 画面にない商品の数量は変更できない
     */
    #[Test]
    public function 画面にない商品の数量は変更しない(): void
    {
        [$user, $items] = $this->cartScenario();
        $other = $this->makeItem('その他', 2, 100);

        Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->call('updateQuantity', $other->id, '3')
            ->assertSet('quantities', [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1]);

        $this->assertArrayNotHasKey($other->id, session('cart'));
    }

    /**
     * お届け先住所を東京都に切り替えると、送料が関東の金額になり、確認用住所も切り替わる（S-7）
     */
    #[Test]
    public function 住所を東京都に切り替えると送料と確認用住所が変わる(): void
    {
        [$user] = $this->cartScenario();
        $tokyo = Delivery_address::factory()->create(['user_id' => $user->id, 'delivery_prefecture' => '東京都', 'delivery_city' => '千代田区1-1']);

        Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->set('addressId', 'delivery_'.$tokyo->id)
            ->assertViewHas('summary', fn ($summary): bool => $summary->shippingFee === 2370 && $summary->total === 4243)
            ->assertViewHas('confirmAddress', '東京都千代田区1-1')
            ->assertSee('2,370円')
            ->assertSee('4,243円');
    }

    /**
     * 35kg（じゃがいも10kg×3 + 玉ねぎ5kg×1）は2個口で、北海道は 3,060 + 1,230 = 4,290円（S-8）
     */
    #[Test]
    public function 三十五キロは二個口の送料になる(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $potato = $this->makeItem('じゃがいも', 10, 1320);
        $onion = $this->makeItem('玉ねぎ', 5, 795);
        session(['cart' => [$potato->id => 3, $onion->id => 1]]);

        Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->assertViewHas('summary', function ($summary): bool {
                return $summary->totalWeight === 35
                    && $summary->subtotal === 4755
                    && $summary->shippingFee === config('shipping.fees.180.北海道') + config('shipping.fees.80.北海道')
                    && $summary->shippingFee === 4290
                    && $summary->total === 9045;
            });
    }

    /**
     * 沖縄県の配送先に切り替えると、2kgで送料2,340円・合計2,670円（S-9）
     */
    #[Test]
    public function 沖縄県の送料は2340円(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $potato = $this->makeItem('じゃがいも', 2, 330);
        $okinawa = Delivery_address::factory()->create(['user_id' => $user->id, 'delivery_prefecture' => '沖縄県', 'delivery_city' => '那覇市1-1']);

        Livewire::actingAs($user)
            ->test(PurchaseForm::class, ['itemId' => $potato->id])
            ->set('addressId', 'delivery_'.$okinawa->id)
            ->assertViewHas('summary', fn ($summary): bool => $summary->shippingFee === 2340 && $summary->total === 2670);
    }

    /**
     * 送料の地域に対応していない都道府県（設定にない住所）でも、描画で500にならず、住所欄にエラーを出す。
     * 金額は小計だけを表示し、「注文を確定する」は押せない
     */
    #[Test]
    public function 送料を計算できない住所でも描画でエラーにならず注文を押せない(): void
    {
        $this->makeAreas();
        $user = $this->makeUser('存在県');
        $item = $this->makeItem('人参', 2, 418);

        $component = Livewire::actingAs($user)
            ->test(PurchaseForm::class, ['itemId' => $item->id])
            ->assertOk()
            ->assertViewHas('shippingError', fn ($error): bool => $error !== null)
            ->assertViewHas('summary', fn ($summary): bool => $summary->subtotal === 418 && $summary->shippingFee === 0)
            ->assertSee('このお届け先住所は送料を計算できません。別の住所を選択してください')
            ->assertSee('data-testid="shipping-error"', false);

        $this->assertMatchesRegularExpression('#<button[^>]*\sdisabled(?=[=\s>])[^>]*>\s*注文を確定する#', $component->html());
    }

    /**
     * 送料を計算できない住所から、正しい住所に切り替えるとエラーが消え、注文を押せるようになる
     */
    #[Test]
    public function 送料を計算できる住所に切り替えるとエラーが消える(): void
    {
        $this->makeAreas();
        $user = $this->makeUser('存在県');
        $item = $this->makeItem('人参', 2, 418);
        $tokyo = Delivery_address::factory()->create(['user_id' => $user->id, 'delivery_prefecture' => '東京都', 'delivery_city' => '千代田区1-1']);

        $component = Livewire::actingAs($user)
            ->test(PurchaseForm::class, ['itemId' => $item->id])
            ->assertViewHas('shippingError', fn ($error): bool => $error !== null)
            ->set('addressId', 'delivery_'.$tokyo->id)
            ->assertViewHas('shippingError', null)
            ->assertViewHas('summary', fn ($summary): bool => $summary->shippingFee === 1460)
            ->assertDontSee('送料を計算できません');

        $this->assertDoesNotMatchRegularExpression('#<button[^>]*\sdisabled(?=[=\s>])[^>]*>\s*注文を確定する#', $component->html());
    }

    /**
     * 送料を計算できない住所のまま「注文を確定する」を送っても、サーバー側でエラーにする（Stripe へは進まない）
     */
    #[Test]
    public function 送料を計算できない住所の注文はサーバーでもエラーになる(): void
    {
        $this->makeAreas();
        $user = $this->makeUser('存在県');
        $item = $this->makeItem('人参', 2, 418);
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('ensureCustomer');
            $mock->shouldNotReceive('createCheckoutSession');
        });

        $this->actingAs($user)
            ->from(route('shop.checkout.index', $item))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$item->id => 1], ['source' => 'item']))
            ->assertRedirect(route('shop.checkout.index', $item))
            ->assertSessionHasErrors(['address_id' => 'お届け先住所の都道府県が正しくありません']);

        $this->assertNull(session('pending_order'));
    }

    /**
     * 他人の配送先住所・不正な値は選べず、会員登録の住所に戻る（認可）
     */
    #[Test]
    public function 他人の住所や不正な値は会員登録の住所に戻る(): void
    {
        [$user] = $this->cartScenario();
        $others = Delivery_address::factory()->create(['delivery_prefecture' => '沖縄県', 'delivery_city' => '那覇市9-9']);

        foreach (['delivery_'.$others->id, 'delivery_9999', 'user_'.($user->id + 1), "user_{$user->id}x", 'foo', ''] as $addressId) {
            Livewire::actingAs($user)
                ->test(PurchaseForm::class)
                ->set('addressId', $addressId)
                ->assertSet('addressId', 'user_'.$user->id)
                ->assertViewHas('confirmAddress', '北海道札幌市中央区1-1-1')
                ->assertViewHas('summary', fn ($summary): bool => $summary->shippingFee === 1850)
                ->assertDontSee('那覇市9-9');
        }
    }

    /**
     * 代金引換を選ぶと代引手数料330円の行が出て合計に加わり、クレジットカードに戻すと消える（S-10）
     */
    #[Test]
    public function 代金引換で代引手数料が加わりカードに戻すと消える(): void
    {
        [$user] = $this->cartScenario();
        $codFee = config('shipping.cod_fee');

        $component = Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->assertDontSee('代引手数料')
            ->set('paymentMethod', 'cod')
            ->assertSet('paymentMethod', 'cod')
            ->assertSee('代引手数料')
            ->assertSee('330円')
            ->assertViewHas('summary', fn ($summary): bool => $summary->codFee === $codFee && $summary->total === 3723 + $codFee)
            ->assertSee('4,053円');

        $component->set('paymentMethod', 'card')
            ->assertDontSee('代引手数料')
            ->assertViewHas('summary', fn ($summary): bool => $summary->codFee === 0 && $summary->total === 3723)
            ->assertSee('3,723円');

        $component->set('paymentMethod', 'customer_balance')
            ->assertDontSee('代引手数料')
            ->assertViewHas('summary', fn ($summary): bool => $summary->codFee === 0);
    }

    /**
     * 住所追加：都道府県と住所を入れて「住所を追加」で登録され、モーダルが閉じ、新しい住所が選択される（S-11）
     */
    #[Test]
    public function 住所を追加するとモーダルが閉じて新しい住所が選択される(): void
    {
        [$user] = $this->cartScenario();

        $component = Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->call('openAddressModal')
            ->assertSet('showAddressModal', true)
            ->set('delivery_prefecture', '東京都')
            ->set('delivery_city', 'UAT区テスト町1-2-3')
            ->call('addAddress')
            ->assertHasNoErrors()
            ->assertSet('showAddressModal', false)
            ->assertSet('delivery_prefecture', '')
            ->assertSet('delivery_city', '');

        $address = Delivery_address::where('user_id', $user->id)->sole();
        $this->assertSame('東京都', $address->delivery_prefecture);
        $this->assertSame('UAT区テスト町1-2-3', $address->delivery_city);

        $component->assertSet('addressId', 'delivery_'.$address->id)
            ->assertViewHas('confirmAddress', '東京都UAT区テスト町1-2-3')
            ->assertViewHas('summary', fn ($summary): bool => $summary->shippingFee === config('shipping.fees.120.関東'))
            ->assertSee('東京都UAT区テスト町1-2-3');
    }

    /**
     * 住所追加：未入力ではエラーがモーダル内に出て、住所は登録されない（S-12）
     */
    #[Test]
    public function 住所追加は未入力だとエラーになる(): void
    {
        [$user] = $this->cartScenario();

        Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->call('openAddressModal')
            ->call('addAddress')
            ->assertHasErrors(['delivery_prefecture' => 'required', 'delivery_city' => 'required'])
            ->assertSet('showAddressModal', true)
            ->assertSee('都道府県を選択してください')
            ->assertSee('住所を入力してください');

        $this->assertSame(0, Delivery_address::count());
    }

    /**
     * 住所追加：住所の文字数の境界値（255文字は登録でき、256文字はエラー）
     */
    #[Test]
    public function 住所追加の文字数は255文字まで(): void
    {
        [$user] = $this->cartScenario();

        Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->set('delivery_prefecture', '東京都')
            ->set('delivery_city', str_repeat('あ', 256))
            ->call('addAddress')
            ->assertHasErrors(['delivery_city' => 'max'])
            ->assertSee('住所は255文字以内で入力してください');
        $this->assertSame(0, Delivery_address::count());

        Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->set('delivery_prefecture', '東京都')
            ->set('delivery_city', str_repeat('あ', 255))
            ->call('addAddress')
            ->assertHasNoErrors();
        $this->assertSame(1, Delivery_address::count());
    }

    /**
     * 住所追加：areas にない都道府県はエラー
     */
    #[Test]
    public function 住所追加は存在しない都道府県だとエラーになる(): void
    {
        [$user] = $this->cartScenario();

        Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->set('delivery_prefecture', '存在県')
            ->set('delivery_city', '住所1-2-3')
            ->call('addAddress')
            ->assertHasErrors(['delivery_prefecture']);

        $this->assertSame(0, Delivery_address::count());
    }

    /**
     * モーダルを開き直すと、前回の入力とエラーは消えている
     */
    #[Test]
    public function モーダルを開き直すと前回の入力とエラーが消える(): void
    {
        [$user] = $this->cartScenario();

        Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->set('delivery_city', 'あ')
            ->call('addAddress')
            ->assertHasErrors(['delivery_prefecture'])
            ->call('openAddressModal')
            ->assertHasNoErrors()
            ->assertSet('delivery_city', '');
    }

    /**
     * 住所追加モーダルの都道府県は areas テーブルの選択肢、プレースホルダ付き
     */
    #[Test]
    public function モーダルの都道府県は地域テーブルから選べる(): void
    {
        [$user] = $this->cartScenario();

        Livewire::actingAs($user)
            ->test(PurchaseForm::class)
            ->assertSee('都道府県を選択')
            ->assertSee('選択してください')
            ->assertSee('<option value="東京都">東京都</option>', false)
            ->assertSee('<option value="沖縄県">沖縄県</option>', false)
            ->assertSee('住所を追加')
            ->assertSee('＋新しい住所を追加')
            ->assertSee('例：適当市適当町３４－５６');
    }

    /**
     * 検証エラーで戻ったときは、入力していた氏名・電話番号・住所・支払方法・数量が残る
     */
    #[Test]
    public function 検証エラーで戻ったときは入力が残る(): void
    {
        [$user, $items] = $this->cartScenario();
        $tokyo = Delivery_address::factory()->create(['user_id' => $user->id, 'delivery_prefecture' => '東京都', 'delivery_city' => '千代田区1-1']);

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 5, $items['onion']->id => 1, $items['carrot']->id => 1], [
                'delivery_name' => '',
                'delivery_tell' => '090-1234-5678',
                'address_id' => 'delivery_'.$tokyo->id,
                'payment_method' => 'cod',
            ]))
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors(['delivery_name', 'delivery_tell']);

        $this->get(route('shop.checkout.index'))
            ->assertOk()
            ->assertSee('value="090-1234-5678"', false)
            ->assertSee('お届け先氏名を入力してください')
            ->assertSee('お届け先電話番号はハイフンなしの数字のみで10〜11桁で入力してください')
            ->assertSee('東京都千代田区1-1')
            ->assertSee('代引手数料');
    }

    /**
     * 空欄で送信して検証エラーで戻ったときは、氏名・電話番号・数量が空欄のまま残る（会員の初期値に戻らない）（S-13）
     */
    #[Test]
    public function 空欄で送信して戻ったときは空欄のまま残る(): void
    {
        [$user, $items] = $this->cartScenario();

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), [
                ...$this->paymentData($user, []),
                'items' => [
                    ['item_id' => $items['potato']->id, 'quantity' => ''],
                    ['item_id' => $items['onion']->id, 'quantity' => 1],
                    ['item_id' => $items['carrot']->id, 'quantity' => 1],
                ],
                'delivery_name' => '',
                'delivery_tell' => '',
            ])
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors(['delivery_name', 'delivery_tell', 'items.0.quantity']);

        $html = $this->get(route('shop.checkout.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<input[^>]*id="delivery_name"[^>]*value=""#', $html);
        $this->assertMatchesRegularExpression('#<input[^>]*id="delivery_tell"[^>]*value=""#', $html);
        $this->assertMatchesRegularExpression('#<input[^>]*id="purchase-quantity-'.$items['potato']->id.'"[^>]*value=""#', $html);
        $this->assertStringNotContainsString('value="塩谷太郎"', $html);
        $this->assertStringNotContainsString('value="09012345678"', $html);
        $this->assertStringContainsString('お届け先氏名を入力してください', $html);
        $this->assertStringContainsString('お届け先電話番号を入力してください', $html);
        $this->assertStringContainsString('商品の数を入力してください', $html);
    }

    /**
     * 氏名だけを空にして送信したときは、氏名は空欄、他の欄（電話番号）は入力した値が残る
     */
    #[Test]
    public function 氏名だけ空にして送信したときは他の欄の入力が残る(): void
    {
        [$user, $items] = $this->cartScenario();

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], [
                'delivery_name' => '',
                'delivery_tell' => '08011112222',
            ]))
            ->assertSessionHasErrors(['delivery_name']);

        $html = $this->get(route('shop.checkout.index'))->getContent();

        $this->assertMatchesRegularExpression('#<input[^>]*id="delivery_name"[^>]*value=""#', $html);
        $this->assertStringContainsString('value="08011112222"', $html);
    }

    /**
     * 範囲外の数量で戻ったときは、入力した値と数量のエラーが商品の行に表示される（S-14）
     */
    #[Test]
    public function 範囲外の数量で戻ったときは入力値とエラーが表示される(): void
    {
        [$user, $items] = $this->cartScenario();

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 11, $items['onion']->id => 1, $items['carrot']->id => 1]))
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors(['items.0.quantity']);

        $this->get(route('shop.checkout.index'))
            ->assertOk()
            ->assertSee('value="11"', false)
            ->assertSee('商品の数は10個以下にしてください');
    }

    /**
     * Stripe の決済ページから「戻る」で戻ったときは、保存待ちの注文の内容が残る（S-19）
     */
    #[Test]
    public function stripeから戻ったときは保存待ちの注文の内容が残る(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $carrot = $this->makeItem('人参', 2, 418);
        $tokyo = Delivery_address::factory()->create(['user_id' => $user->id, 'delivery_prefecture' => '東京都', 'delivery_city' => '千代田区1-1']);

        session(['pending_order' => (new PendingOrder(
            $user->id,
            PurchaseSource::Item,
            'delivery_'.$tokyo->id,
            '別の人',
            '08011112222',
            '東京都',
            '千代田区1-1',
            PaymentMethod::CustomerBalance,
            [['item_id' => $carrot->id, 'name' => '人参 2kg', 'quantity' => 4, 'price' => 418, 'subtotal' => 1672]],
            1672,
            1460,
            0,
            3132,
            'cs_test_1',
        ))->toArray()]);

        // 通常の訪問（「戻る」で戻った直後ではない）では、保存待ちの注文があっても会員登録の内容で表示する
        Livewire::actingAs($user)
            ->test(PurchaseForm::class, ['itemId' => $carrot->id])
            ->assertSet('deliveryName', '塩谷太郎')
            ->assertSet('paymentMethod', 'card')
            ->assertSet('quantities', [$carrot->id => 1]);

        // 「戻る」で戻った直後（復元の合図あり）は復元する
        session([PurchaseService::RESTORE_FLASH_KEY => true]);
        Livewire::actingAs($user)
            ->test(PurchaseForm::class, ['itemId' => $carrot->id])
            ->assertSet('deliveryName', '別の人')
            ->assertSet('deliveryTell', '08011112222')
            ->assertSet('addressId', 'delivery_'.$tokyo->id)
            ->assertSet('paymentMethod', 'customer_balance')
            ->assertSet('quantities', [$carrot->id => 4]);

        // 別の商品・カートの購入ページには、合図があっても引き継がない
        $other = $this->makeItem('玉ねぎ', 5, 795);
        Livewire::actingAs($user)
            ->test(PurchaseForm::class, ['itemId' => $other->id])
            ->assertSet('deliveryName', '塩谷太郎')
            ->assertSet('paymentMethod', 'card')
            ->assertSet('quantities', [$other->id => 1]);
    }

    /**
     * 別の会員の保存待ちの注文は引き継がない
     */
    #[Test]
    public function 別の会員の保存待ちの注文は引き継がない(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $carrot = $this->makeItem('人参', 2, 418);

        session(['pending_order' => (new PendingOrder(
            $user->id + 1,
            PurchaseSource::Item,
            'user_'.($user->id + 1),
            '別の人',
            '08011112222',
            '東京都',
            '千代田区1-1',
            PaymentMethod::Cod,
            [['item_id' => $carrot->id, 'name' => '人参 2kg', 'quantity' => 4, 'price' => 418, 'subtotal' => 1672]],
            1672,
            1460,
            330,
            3462,
        ))->toArray()]);

        session([PurchaseService::RESTORE_FLASH_KEY => true]);
        Livewire::actingAs($user)
            ->test(PurchaseForm::class, ['itemId' => $carrot->id])
            ->assertSet('deliveryName', '塩谷太郎')
            ->assertSet('paymentMethod', 'card');
    }

    /**
     * 商品IDと購入元は Livewire の更新リクエストから書き換えられない
     */
    #[Test]
    public function 商品_i_dと購入元は書き換えられない(): void
    {
        [$user, $items] = $this->cartScenario();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($user)
            ->test(PurchaseForm::class, ['itemId' => $items['carrot']->id])
            ->set('itemId', $items['potato']->id);
    }

    /**
     * 予約限定の商品は、数量に含まれていても金額計算から外れる
     */
    #[Test]
    public function 予約限定の商品は計算に含めない(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $reserved = $this->makeReservedItem();

        Livewire::actingAs($user)
            ->test(PurchaseForm::class, ['itemId' => $reserved->id])
            ->assertSee('購入できる商品がありません');
    }

    /**
     * 未ログインでは使えない
     */
    #[Test]
    public function 未ログインでは表示できない(): void
    {
        Livewire::test(PurchaseForm::class)->assertStatus(401);
    }
}
