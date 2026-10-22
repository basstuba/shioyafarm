<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Models\Item;
use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use App\Models\Purchase_history_item;
use App\Models\User;
use App\Services\Payment\BankTransferInstructions;
use App\Services\Payment\CheckoutSessionData;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\PendingOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * 購入完了ページ（GET /purchase/success）のテスト。決済の確認と購入データの保存を確かめる
 */
class PurchaseSuccessTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * シナリオ S-2 のカート（じゃがいも2kg×2、玉ねぎ5kg×1、人参2kg×1）を作る
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
     * 保存待ちの注文（S-2 のカート、北海道）をセッションに入れる
     *
     * @param  array<string, Item>  $items
     */
    private function putPendingOrder(User $user, array $items, PaymentMethod $method, ?string $sessionId = null, PurchaseSource $source = PurchaseSource::Cart, ?string $paymentIntentId = null): void
    {
        $codFee = $method === PaymentMethod::Cod ? 330 : 0;

        session(['pending_order' => (new PendingOrder(
            $user->id,
            $source,
            'user_'.$user->id,
            '山田花子',
            '08011112222',
            '北海道',
            '札幌市中央区1-1-1',
            $method,
            [
                ['item_id' => $items['potato']->id, 'name' => 'じゃがいも 2kg', 'quantity' => 2, 'price' => 330, 'subtotal' => 660],
                ['item_id' => $items['onion']->id, 'name' => '玉ねぎ 5kg', 'quantity' => 1, 'price' => 795, 'subtotal' => 795],
                ['item_id' => $items['carrot']->id, 'name' => '人参 2kg', 'quantity' => 1, 'price' => 418, 'subtotal' => 418],
            ],
            1873,
            1850,
            $codFee,
            1873 + 1850 + $codFee,
            $sessionId,
            $paymentIntentId,
            $paymentIntentId === null ? null : new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 3723),
        ))->toArray()]);
    }

    /**
     * 決済状態を返すモックに差し替える
     */
    private function mockSession(string $sessionId, string $status, ?string $paymentStatus = null): void
    {
        $paymentStatus ??= $status === 'complete' ? 'paid' : 'unpaid';

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($sessionId, $status, $paymentStatus): void {
            $mock->shouldReceive('retrieveCheckoutSession')
                ->once()
                ->with($sessionId)
                ->andReturn(new CheckoutSessionData($sessionId, null, $status, $paymentStatus));
        });
    }

    /**
     * Stripe への問い合わせが無いことを保証する
     */
    private function gatewayNeverCalled(): void
    {
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createCheckoutSession');
            $mock->shouldNotReceive('retrieveCheckoutSession');
            $mock->shouldNotReceive('createBankTransferIntent');
            $mock->shouldNotReceive('retrieveBankTransferIntent');
            $mock->shouldNotReceive('cancelPaymentIntent');
            $mock->shouldNotReceive('expireCheckoutSession');
            $mock->shouldNotReceive('ensureCustomer');
        });
    }

    /**
     * 未ログインはログインページへ移動する
     */
    #[Test]
    public function 未ログインはログインページへ移動する(): void
    {
        $this->get(route('shop.checkout.success'))->assertRedirect(route('login'));
    }

    /**
     * 保存待ちの注文が無いとき（直接アクセス）は、購入データを作らずトップページへ戻す（S-20）
     */
    #[Test]
    public function 保存待ちの注文がなければトップページへリダイレクトされる(): void
    {
        $this->gatewayNeverCalled();

        $this->actingAs($this->makeUser())
            ->get(route('shop.checkout.success'))
            ->assertRedirect(route('top.index'));

        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * サンクスページに背景画像・お礼・発送の案内・「ホームに戻る」が表示される（S-21, AC-13）
     */
    #[Test]
    public function サンクスページの表示内容(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_ok');
        $this->mockSession('cs_test_ok', 'complete');

        $this->actingAs($user)
            ->get(route('shop.checkout.success', ['session_id' => 'cs_test_ok']))
            ->assertOk()
            ->assertSee('<title>ご注文完了 | Shioya Farm</title>', false)
            ->assertSee('storage/image/tool/thanks-page-photo.webp', false)
            ->assertSee('ご注文ありがとうございました。')
            ->assertSee('心を込めてお届けいたします。')
            ->assertSee('商品はお支払い確定後3~5営業日以内、')
            ->assertSee('代金引換の場合はご注文確定後3~5営業日以内に発送いたします。')
            ->assertSee('ホームに戻る')
            ->assertSee('href="'.route('top.index').'"', false)
            ->assertDontSee('ログアウト');
    }

    /**
     * サンクスページを再読み込みしても、購入データは増えずトップページへ戻る（S-20, AC-12）
     */
    #[Test]
    public function 再読み込みしても二重に登録されない(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_ok');
        $this->mockSession('cs_test_ok', 'complete');

        $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => 'cs_test_ok']))->assertOk();
        $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => 'cs_test_ok']))->assertRedirect(route('top.index'));
        $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => 'cs_test_ok']))->assertRedirect(route('top.index'));

        $this->assertSame(1, Purchase_history::count());
        $this->assertSame(3, Purchase_history_item::count());
    }

    /**
     * 商品詳細から購入した場合、カートは変わらない（S-18, AC-11）
     */
    #[Test]
    public function 商品詳細から購入してもカートは変わらない(): void
    {
        [$user, $items] = $this->cartScenario();
        $cart = session('cart');
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_ok', PurchaseSource::Item);
        $this->mockSession('cs_test_ok', 'complete');

        $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => 'cs_test_ok']))->assertOk();

        $this->assertSame(1, Purchase_history::count());
        $this->assertSame($cart, session('cart'));
    }

    /**
     * クレジットカード：セッションIDが一致し、Checkout Session が完了していれば保存する（S-15, AC-8）
     */
    #[Test]
    public function クレジットカードは決済完了を確認して保存する(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_ok');
        $this->mockSession('cs_test_ok', 'complete');

        $this->actingAs($user)
            ->get(route('shop.checkout.success', ['session_id' => 'cs_test_ok']))
            ->assertOk()
            ->assertViewIs('shop.purchase_thanks');

        $history = Purchase_history::sole();
        $this->assertSame('card', $history->payment_method);
        $this->assertSame(1850, $history->shipping_fee);
        $this->assertSame(0, $history->cod_fee);
        $this->assertSame(3723, $history->total_price);
        $this->assertSame(3, $history->purchaseHistoryItems()->count());
        $this->assertNull(session('pending_order'));
        // 購入した商品の数量だけカートから減るため、カートは空になる
        $this->assertEmpty(session('cart'));
    }

    /**
     * カードでは振込先データを作らない
     */
    #[Test]
    public function カードでは振込先を保存しない(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_ok');
        $this->mockSession('cs_test_ok', 'complete');

        $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => 'cs_test_ok']))->assertOk();

        $this->assertSame(1, Purchase_history::count());
        $this->assertSame(0, Purchase_bank_transfer::count());
    }

    /**
     * 購入していない商品はカートに残り、購入した商品は数量の分だけ減る（カードで支払ったあと）
     */
    #[Test]
    public function カートに残る商品は購入後も残る(): void
    {
        [$user, $items] = $this->cartScenario();
        $other = $this->makeItem('かぼちゃ', 3, 500);
        // 注文（じゃがいも2・玉ねぎ1・人参1）より、じゃがいもを多く、かぼちゃを別に入れている
        session(['cart' => [$items['potato']->id => 5, $items['onion']->id => 1, $items['carrot']->id => 1, $other->id => 2]]);
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_ok');
        $this->mockSession('cs_test_ok', 'complete');

        $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => 'cs_test_ok']))->assertOk();

        $this->assertSame([$items['potato']->id => 3, $other->id => 2], session('cart'));
    }

    /**
     * GET /purchase/success はクレジットカード専用：保存待ちの注文が代金引換のままのセッション（古いセッション）で開いても、
     * 購入データを保存せず、Stripe にも問い合わせず、購入ページへ戻す
     */
    #[Test]
    public function 代金引換の保存待ちの注文は保存されない(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->gatewayNeverCalled();
        $this->putPendingOrder($user, $items, PaymentMethod::Cod);

        $this->actingAs($user)
            ->get(route('shop.checkout.success'))
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors('payment');
        $this->get(route('shop.checkout.success', ['session_id' => 'cs_test_x']))->assertRedirect(route('shop.checkout.index'));

        $this->assertSame(0, Purchase_history::count());
        $this->assertNotEmpty(session('cart'));
    }

    /**
     * セッションIDが保存待ちの注文のものと違うときは、Stripe に問い合わせず、保存もせず、購入ページへ戻す
     */
    #[Test]
    public function セッション_i_dが一致しなければ保存されない(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_mine');
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->get(route('shop.checkout.success', ['session_id' => 'cs_test_other']))
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors('payment');

        $this->assertSame(0, Purchase_history::count());
        // やり直せるよう、保存待ちの注文とカートは残す
        $this->assertNotNull(session('pending_order'));
        $this->assertNotNull(session('cart'));
    }

    /**
     * セッションIDが付いていない・配列で渡されたときも保存されない
     */
    #[Test]
    public function セッション_i_dがないまたは不正なら保存されない(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_mine');
        $this->gatewayNeverCalled();

        $this->actingAs($user)->get(route('shop.checkout.success'))->assertRedirect(route('shop.checkout.index'));
        $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => ['cs_test_mine']]))->assertRedirect(route('shop.checkout.index'));
        $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => '']))->assertRedirect(route('shop.checkout.index'));

        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * Checkout Session が完了していない（open など）ときは保存せず、購入ページへ戻す
     */
    #[Test]
    public function 未完了の_checkout_sessionは保存されない(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_open');
        $this->mockSession('cs_test_open', 'open');

        $this->actingAs($user)
            ->get(route('shop.checkout.success', ['session_id' => 'cs_test_open']))
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors('payment');

        $this->assertSame(0, Purchase_history::count());
        $this->assertNotNull(session('pending_order'));
        $this->assertNotNull(session('cart'));
    }

    /**
     * 期限切れ（expired）の Checkout Session も保存しない
     */
    #[Test]
    public function 期限切れの_checkout_sessionは保存されない(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_expired');
        $this->mockSession('cs_test_expired', 'expired');

        $this->actingAs($user)
            ->get(route('shop.checkout.success', ['session_id' => 'cs_test_expired']))
            ->assertRedirect(route('shop.checkout.index'));

        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * Stripe に問い合わせられなかったとき（通信エラー）は、保存せず、保存待ちの注文とカートを残し、再読み込みを案内する。
     * 支払い済みかもしれないので、再決済（購入ページへ戻して「注文を確定する」）は促さない
     */
    #[Test]
    public function stripeに問い合わせられなければ保存されない(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_err');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->andThrow(new PaymentGatewayException('通信エラー'));
        });

        $this->actingAs($user)
            ->get(route('shop.checkout.success', ['session_id' => 'cs_test_err']))
            ->assertStatus(503)
            ->assertViewIs('shop.payment_pending')
            ->assertSee('お支払いの確認に失敗しました。時間をおいてこのページを再読み込みしてください')
            ->assertSee('再度のお手続きは不要です')
            ->assertSee('再読み込み')
            ->assertSee('session_id=cs_test_err', false)
            ->assertDontSee('もう一度「注文を確定する」');

        $this->assertSame(0, Purchase_history::count());
        $this->assertNotNull(session('pending_order'));
        $this->assertNotNull(session('cart'));
    }

    /**
     * 通信エラーのあと、もう一度（再読み込み）開いて確認できたら、そのまま保存できる（再決済は不要）
     */
    #[Test]
    public function 通信エラーのあと再読み込みで確認できれば保存される(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_retry');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->twice()->with('cs_test_retry')->andReturnUsing(
                fn () => throw new PaymentGatewayException('通信エラー'),
                fn () => new CheckoutSessionData('cs_test_retry', null, 'complete', 'paid'),
            );
        });

        $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => 'cs_test_retry']))->assertStatus(503);
        $this->assertSame(0, Purchase_history::count());

        $this->get(route('shop.checkout.success', ['session_id' => 'cs_test_retry']))->assertOk()->assertViewIs('shop.purchase_thanks');
        $this->assertSame(1, Purchase_history::count());
        $this->assertNull(session('pending_order'));
    }

    /**
     * 商品詳細から購入した注文で決済を確認できなかったときは、その商品の購入ページへ戻す
     */
    #[Test]
    public function 商品詳細からの注文は未完了ならその商品の購入ページへ戻る(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_open', PurchaseSource::Item);
        $this->mockSession('cs_test_open', 'open');

        // 商品詳細からの注文は明細が1件のため、先頭の商品（じゃがいも）が戻り先になる
        $this->actingAs($user)
            ->get(route('shop.checkout.success', ['session_id' => 'cs_test_open']))
            ->assertRedirect(route('shop.checkout.index', $items['potato']));
    }

    /**
     * 別の会員の保存待ちの注文では保存されない
     */
    #[Test]
    public function 別の会員の保存待ちの注文は保存されない(): void
    {
        [$user, $items] = $this->cartScenario();
        $other = $this->makeUser();
        $this->putPendingOrder($other, $items, PaymentMethod::Cod);
        $this->gatewayNeverCalled();

        $this->actingAs($user)
            ->get(route('shop.checkout.success'))
            ->assertRedirect(route('top.index'));

        $this->assertSame(0, Purchase_history::count());
        $this->assertNotNull(session('pending_order'));
    }

    /**
     * セッションの保存待ちの注文が壊れているときは何も保存せずトップページへ戻す
     */
    #[Test]
    public function 壊れた保存待ちの注文は無視される(): void
    {
        [$user] = $this->cartScenario();
        $this->gatewayNeverCalled();
        session(['pending_order' => ['payment_method' => 'cod', 'items' => 'x']]);

        $this->actingAs($user)
            ->get(route('shop.checkout.success'))
            ->assertRedirect(route('top.index'));

        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * 保存に失敗したときは購入履歴も購入明細も作られず（トランザクション）、保存待ちの注文とカートが残る
     */
    #[Test]
    public function 保存に失敗したらどちらも作られない(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_ok');
        $this->mockSession('cs_test_ok', 'complete');
        // 3件目の明細の商品を、存在しない商品IDにして外部キーエラーにする
        $pending = session('pending_order');
        $pending['items'][2]['item_id'] = 999999;
        session(['pending_order' => $pending]);

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => 'cs_test_ok']));
            $this->fail('保存に失敗するはずが、例外が発生しなかった');
        } catch (QueryException) {
            // 想定どおり
        }

        $this->assertSame(0, Purchase_history::count());
        $this->assertSame(0, Purchase_history_item::count());
        $this->assertNotNull(session('pending_order'));
        $this->assertNotNull(session('cart'));
    }

    /**
     * 決済から保存までの間に商品の価格が変わっても、請求した金額（注文時の単価）で保存する
     */
    #[Test]
    public function 保存する金額は注文時に再計算した金額(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putPendingOrder($user, $items, PaymentMethod::Card, 'cs_test_ok');
        $this->mockSession('cs_test_ok', 'complete');
        $items['potato']->update(['price' => 9999]);

        $this->actingAs($user)->get(route('shop.checkout.success', ['session_id' => 'cs_test_ok']))->assertOk();

        $this->assertSame(330, Purchase_history_item::where('item_id', $items['potato']->id)->sole()->price);
        $this->assertSame(3723, Purchase_history::sole()->total_price);
    }

    /**
     * 注文の確定から購入完了までの一連の流れ（クレジットカード）：決済ページへ移動 → 戻る → もう一度確定 → 支払い完了
     */
    #[Test]
    public function カードで決済ページから戻ってやり直せる(): void
    {
        [$user, $items] = $this->cartScenario();
        $quantities = [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1];

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createCheckoutSession')->twice()->andReturn(
                new CheckoutSessionData('cs_test_first', 'https://checkout.stripe.test/first', 'open'),
                new CheckoutSessionData('cs_test_second', 'https://checkout.stripe.test/second', 'open'),
            );
            // 2回目の「注文を確定する」で、前の Session（まだ開いている）を失効させる
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_test_first')
                ->andReturn(new CheckoutSessionData('cs_test_first', null, 'open', 'unpaid'));
            $mock->shouldReceive('expireCheckoutSession')->once()->with('cs_test_first')
                ->andReturn(new CheckoutSessionData('cs_test_first', null, 'expired', 'unpaid'));
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_test_second')
                ->andReturn(new CheckoutSessionData('cs_test_second', null, 'complete', 'paid'));
        });

        $this->actingAs($user)->post(route('shop.checkout.payment'), $this->paymentData($user, $quantities))->assertRedirect('https://checkout.stripe.test/first');
        // Stripe の「戻る」で購入ページに戻る。購入データはまだ作られない
        $this->get(route('shop.checkout.index'))->assertOk();
        $this->assertSame(0, Purchase_history::count());

        // 最初のセッションIDでは完了できない（上書きされている）
        $this->post(route('shop.checkout.payment'), $this->paymentData($user, $quantities))->assertRedirect('https://checkout.stripe.test/second');
        $this->get(route('shop.checkout.success', ['session_id' => 'cs_test_first']))->assertRedirect(route('shop.checkout.index'));
        $this->assertSame(0, Purchase_history::count());

        $this->get(route('shop.checkout.success', ['session_id' => 'cs_test_second']))->assertOk();
        $this->assertSame(1, Purchase_history::count());
    }
}
