<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use App\Models\Purchase_history_item;
use App\Models\User;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * 代金引換の購入（POST /payment で購入データを保存し、サンクスページ /purchase/thanks へ移動する）のテスト
 */
class CodPurchaseTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * Stripe への問い合わせが無いことを保証する
     */
    private function gatewayNeverCalled(): void
    {
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('ensureCustomer');
            $mock->shouldNotReceive('createCheckoutSession');
            $mock->shouldNotReceive('retrieveCheckoutSession');
            $mock->shouldNotReceive('createBankTransferIntent');
        });
    }

    /**
     * 代金引換で「注文を確定する」（カートの全商品）
     *
     * @param  array<string, Item>  $items
     */
    private function orderCartByCod(User $user, array $items): TestResponse
    {
        return $this->actingAs($user)->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['payment_method' => 'cod']));
    }

    /**
     * 注文を確定すると、その場で購入履歴・購入明細（手数料込み）を保存し、サンクスページ（/purchase/thanks）へ移動する（S-17, AC-10）
     */
    #[Test]
    public function 注文を確定するとその場で保存されサンクスページへ移動する(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->gatewayNeverCalled();

        $this->orderCartByCod($user, $items)
            ->assertRedirect(route('shop.checkout.thanks'))
            ->assertSessionHas(PurchaseService::THANKS_FLASH_KEY, true);

        $history = Purchase_history::sole();
        $this->assertSame($user->id, $history->user_id);
        $this->assertSame('塩谷太郎', $history->delivery_name);
        $this->assertSame('北海道', $history->delivery_prefecture);
        $this->assertSame('札幌市中央区1-1-1', $history->delivery_city);
        $this->assertSame('09012345678', $history->delivery_tell);
        $this->assertSame('cod', $history->payment_method);
        $this->assertSame(1850, $history->shipping_fee);
        $this->assertSame(config('shipping.cod_fee'), $history->cod_fee);
        $this->assertSame(4053, $history->total_price);

        $rows = $history->purchaseHistoryItems()->orderBy('item_id')->get();
        $this->assertSame([2, 1, 1], $rows->pluck('quantity')->all());
        $this->assertSame([330, 795, 418], $rows->pluck('price')->all());
        $this->assertSame([660, 795, 418], $rows->pluck('subtotal')->all());
        $this->assertSame(0, Purchase_bank_transfer::count());

        // 保存待ちの注文は残らない
        $this->assertNull(session('pending_order'));
    }

    /**
     * 注文を確定したあとのサンクスページは、保存した直後の1回だけ表示する（再読み込みしても二重に登録されない）（S-20, AC-12）
     */
    #[Test]
    public function サンクスページは1回だけ表示され再読み込みでは二重に登録されない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->gatewayNeverCalled();

        $this->orderCartByCod($user, $items)->assertRedirect(route('shop.checkout.thanks'));

        $this->get(route('shop.checkout.thanks'))
            ->assertOk()
            ->assertViewIs('shop.purchase_thanks')
            ->assertSee('ご注文ありがとうございました。')
            ->assertSee('代金引換の場合はご注文確定後3~5営業日以内に発送いたします。');
        $this->get(route('shop.checkout.thanks'))->assertRedirect(route('top.index'));
        $this->get(route('shop.checkout.success'))->assertRedirect(route('top.index'));

        $this->assertSame(1, Purchase_history::count());
        $this->assertSame(3, Purchase_history_item::count());
    }

    /**
     * カートから購入した場合は、購入した商品の数量だけがカートから減る。購入していない商品はカートに残る（AC-11）
     */
    #[Test]
    public function カートから購入すると購入した分だけカートから減る(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $other = $this->makeItem('かぼちゃ', 3, 500);
        session(['cart' => [$items['potato']->id => 5, $items['onion']->id => 1, $items['carrot']->id => 1, $other->id => 2]]);
        $this->gatewayNeverCalled();

        $this->orderCartByCod($user, $items)->assertRedirect(route('shop.checkout.thanks'));

        $this->assertSame([$items['potato']->id => 3, $other->id => 2], session('cart'));
    }

    /**
     * 商品詳細から購入した場合は、カートは変わらない（S-18, AC-11）
     */
    #[Test]
    public function 商品詳細から購入してもカートは変わらない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $cart = session('cart');
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['carrot']->id => 1], ['source' => 'item', 'payment_method' => 'cod']))
            ->assertRedirect(route('shop.checkout.thanks'));

        $this->assertSame(1, Purchase_history::count());
        $this->assertSame($cart, session('cart'));
    }

    /**
     * 保存する金額は、サーバーが再計算した金額（商品の価格が変わっていても、確定した時点の単価）
     */
    #[Test]
    public function 保存する金額はサーバーで再計算した金額(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->gatewayNeverCalled();

        $this->orderCartByCod($user, $items);

        $this->assertSame(4053, Purchase_history::sole()->total_price);
        $this->assertSame(330, Purchase_history_item::where('item_id', $items['potato']->id)->sole()->price);
    }
}
