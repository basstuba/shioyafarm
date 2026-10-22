<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Models\Delivery_address;
use App\Models\Item;
use App\Models\Purchase_history;
use App\Models\User;
use App\Services\Payment\BankTransferInstructions;
use App\Services\Payment\BankTransferIntentData;
use App\Services\Payment\CheckoutSessionData;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\PendingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * 購入ページの入力の復元（「戻る」で戻った直後の1回だけ）と、使われなくなった保存待ちの注文のテスト
 */
class PurchaseRestoreTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;

    private User $user;

    private Item $carrot;

    private Delivery_address $okinawa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeAreas();
        $this->user = $this->makeUser();
        $this->carrot = $this->makeItem('人参', 2, 418);
        $this->okinawa = Delivery_address::factory()->create(['user_id' => $this->user->id, 'delivery_prefecture' => '沖縄県', 'delivery_city' => '那覇市1-1']);
    }

    /**
     * 商品詳細（人参）から、沖縄県・別の氏名・銀行振込・数量3で確定した保存待ちの注文をセッションに入れる
     */
    private function putOrder(PaymentMethod $method, ?string $sessionId = null, ?string $paymentIntentId = null): void
    {
        session(['pending_order' => (new PendingOrder(
            $this->user->id,
            PurchaseSource::Item,
            'delivery_'.$this->okinawa->id,
            '別の人',
            '08011112222',
            '沖縄県',
            '那覇市1-1',
            $method,
            [['item_id' => $this->carrot->id, 'name' => '人参 2kg', 'quantity' => 3, 'price' => 418, 'subtotal' => 1254]],
            1254,
            2950,
            0,
            4204,
            $sessionId,
            $paymentIntentId,
            $paymentIntentId === null ? null : new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 4204),
        ))->toArray()]);
    }

    /**
     * 購入ページが、前回の入力で復元されている（別の氏名・沖縄県・銀行振込・数量3）ことを確認する
     */
    private function assertRestored(string $html, bool $bankTransfer = true): void
    {
        $this->assertStringContainsString('value="別の人"', $html);
        $this->assertStringContainsString('value="08011112222"', $html);
        $this->assertStringContainsString('沖縄県那覇市1-1', $html);
        // 支払方法がカードのときは初期値と同じなので、銀行振込のときだけ確認する
        if ($bankTransfer) {
            $this->assertMatchesRegularExpression('#value="customer_balance"[^>]*checked#', $html);
        }
        $this->assertMatchesRegularExpression('#id="purchase-quantity-'.$this->carrot->id.'"[^>]*value="3"#', $html);
    }

    /**
     * 購入ページが、会員登録の内容（初期値）で表示されている（氏名・電話番号・住所・カード・数量1）ことを確認する
     */
    private function assertDefaults(string $html): void
    {
        $this->assertStringContainsString('value="塩谷太郎"', $html);
        $this->assertStringContainsString('value="09012345678"', $html);
        $this->assertStringContainsString('北海道札幌市中央区1-1-1', $html);
        $this->assertStringNotContainsString('value="別の人"', $html);
        $this->assertDoesNotMatchRegularExpression('#value="customer_balance"[^>]*checked#', $html);
        $this->assertMatchesRegularExpression('#value="card"[^>]*checked#', $html);
        $this->assertMatchesRegularExpression('#id="purchase-quantity-'.$this->carrot->id.'"[^>]*value="1"#', $html);
    }

    /**
     * Stripe Checkout の cancel_url（?restore=1）から戻った直後は復元し、再読み込み・開き直しでは会員登録の内容に戻る
     */
    #[Test]
    public function チェックアウトのキャンセルから戻った直後だけ復元する(): void
    {
        $this->putOrder(PaymentMethod::Card, 'cs_test_cancel');
        $url = route('shop.checkout.index', ['item' => $this->carrot, 'restore' => 1]);

        // cancel_url → 復元の合図をフラッシュに置き換え、URLから restore を外してリダイレクトする
        $this->actingAs($this->user)->get($url)->assertRedirect(route('shop.checkout.index', $this->carrot));

        // 戻った直後の1回目は復元される
        $this->assertRestored($this->get(route('shop.checkout.index', $this->carrot))->assertOk()->getContent(), false);

        // 再読み込み・商品詳細などから開き直しても、復元されない
        $this->assertDefaults($this->get(route('shop.checkout.index', $this->carrot))->assertOk()->getContent());
        $this->get(route('shop.items.show', $this->carrot))->assertOk();
        $this->assertDefaults($this->get(route('shop.checkout.index', $this->carrot))->assertOk()->getContent());
    }

    /**
     * 振込先のご案内の「戻る」で戻った直後だけ復元し、開き直しでは会員登録の内容に戻る
     */
    #[Test]
    public function 振込先のご案内の戻るから戻った直後だけ復元する(): void
    {
        $this->putOrder(PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_test_bank');
        });

        $this->actingAs($this->user)
            ->post(route('shop.checkout.bank-transfer.cancel'))
            ->assertRedirect(route('shop.checkout.index', $this->carrot));

        $this->assertRestored($this->get(route('shop.checkout.index', $this->carrot))->assertOk()->getContent());
        $this->assertDefaults($this->get(route('shop.checkout.index', $this->carrot))->assertOk()->getContent());
        $this->get(route('shop.items.show', $this->carrot))->assertOk();
        $this->assertDefaults($this->get(route('shop.checkout.index', $this->carrot))->assertOk()->getContent());
    }

    /**
     * 通常の訪問（保存待ちの注文が残っているだけ）では、会員登録の内容で表示する
     */
    #[Test]
    public function 通常の訪問では会員登録の内容で表示する(): void
    {
        $this->putOrder(PaymentMethod::Card, 'cs_test_stale');

        $this->assertDefaults($this->actingAs($this->user)->get(route('shop.checkout.index', $this->carrot))->assertOk()->getContent());
    }

    /**
     * 復元の合図だけで、別の商品の購入ページには復元しない（合図は1回で消える）
     */
    #[Test]
    public function 別の購入ページには復元しない(): void
    {
        $this->putOrder(PaymentMethod::Card, 'cs_test_other');
        $other = $this->makeItem('玉ねぎ', 5, 795);

        $this->actingAs($this->user)->get(route('shop.checkout.index', ['item' => $other, 'restore' => 1]))->assertRedirect(route('shop.checkout.index', $other));
        $html = $this->get(route('shop.checkout.index', $other))->getContent();

        $this->assertStringContainsString('value="塩谷太郎"', $html);
        $this->assertStringNotContainsString('value="別の人"', $html);
    }

    /**
     * 決済を確認できず購入ページへ戻されたとき（success の失敗）も、戻った直後の1回だけ復元する
     */
    #[Test]
    public function 決済を確認できず戻されたときも1回だけ復元する(): void
    {
        $this->putOrder(PaymentMethod::Card, 'cs_test_mine');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->andReturn(new CheckoutSessionData('cs_test_mine', null, 'open', 'unpaid'));
        });

        $this->actingAs($this->user)
            ->get(route('shop.checkout.success', ['session_id' => 'cs_test_mine']))
            ->assertRedirect(route('shop.checkout.index', $this->carrot));

        $this->assertRestored($this->get(route('shop.checkout.index', $this->carrot))->assertSee('お支払いを確認できませんでした')->getContent(), false);
        $this->assertDefaults($this->get(route('shop.checkout.index', $this->carrot))->getContent());
    }

    /**
     * 検証エラーで戻ったときの old() の復元は従来どおり（保存待ちの注文の有無に関係しない）
     */
    #[Test]
    public function 検証エラーで戻ったときは入力が復元される(): void
    {
        $this->actingAs($this->user)
            ->from(route('shop.checkout.index', $this->carrot))
            ->post(route('shop.checkout.payment'), $this->paymentData($this->user, [$this->carrot->id => 4], [
                'source' => 'item',
                'delivery_name' => '',
                'delivery_tell' => '08099998888',
                'address_id' => 'delivery_'.$this->okinawa->id,
                'payment_method' => 'cod',
            ]))
            ->assertRedirect(route('shop.checkout.index', $this->carrot));

        $html = $this->get(route('shop.checkout.index', $this->carrot))->getContent();
        $this->assertStringContainsString('value="08099998888"', $html);
        $this->assertStringContainsString('沖縄県那覇市1-1', $html);
        $this->assertMatchesRegularExpression('#value="cod"[^>]*checked#', $html);
        $this->assertMatchesRegularExpression('#id="purchase-quantity-'.$this->carrot->id.'"[^>]*value="4"#', $html);
    }

    /**
     * 残っているだけのカードの保存待ちの注文は、セッションIDなしの購入完了では保存されない（二重登録・誤登録にならない）
     */
    #[Test]
    public function 残っているカードの注文はセッション_i_dなしでは保存されない(): void
    {
        $this->putOrder(PaymentMethod::Card, 'cs_test_stale');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('retrieveCheckoutSession');
        });

        $this->actingAs($this->user)->get(route('shop.checkout.index', $this->carrot))->assertOk();
        $this->get(route('shop.checkout.success'))->assertRedirect(route('shop.checkout.index', $this->carrot));
        $this->get(route('shop.checkout.success', ['session_id' => 'cs_test_other']))->assertRedirect(route('shop.checkout.index', $this->carrot));

        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * 「戻る」のあとも保存待ちのカードの注文は残し、Stripe の画面（別タブ・ブラウザの戻る）で支払いが済んだ場合は
     * 一致するセッションIDの購入完了で保存できる（支払い済みなのに購入データが無い、にならない）
     */
    #[Test]
    public function 戻ったあとに_stripe側で支払いが済んでも保存できる(): void
    {
        $this->putOrder(PaymentMethod::Card, 'cs_test_paid');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_test_paid')->andReturn(new CheckoutSessionData('cs_test_paid', null, 'complete', 'paid'));
        });

        // 戻って、開き直した（復元は終わっている）あとでも保存待ちの注文は残っている
        $this->actingAs($this->user)->get(route('shop.checkout.index', ['item' => $this->carrot, 'restore' => 1]));
        $this->get(route('shop.checkout.index', $this->carrot))->assertOk();
        $this->get(route('shop.checkout.index', $this->carrot))->assertOk();
        $this->assertNotNull(session('pending_order'));

        $this->get(route('shop.checkout.success', ['session_id' => 'cs_test_paid']))->assertOk();
        $this->assertSame(1, Purchase_history::count());
        $this->assertNull(session('pending_order'));

        // 再読み込みでは増えない
        $this->get(route('shop.checkout.success', ['session_id' => 'cs_test_paid']))->assertRedirect(route('top.index'));
        $this->assertSame(1, Purchase_history::count());
    }

    /**
     * 「戻る」で PaymentIntent をキャンセルしたあとに残る保存待ちの注文（振込先なし）では、購入完了を開いても保存されない
     */
    #[Test]
    public function キャンセル後に残る銀行振込の注文は保存されない(): void
    {
        $this->putOrder(PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntent')->once();
            $mock->shouldNotReceive('retrieveBankTransferIntent');
        });

        $this->actingAs($this->user)->post(route('shop.checkout.bank-transfer.cancel'));
        // 銀行振込の保存は「振込み」（POST）でだけ行う。GET の購入完了では保存されない
        $this->get(route('shop.checkout.success'))->assertRedirect(route('shop.checkout.bank-transfer'));
        // PaymentIntent が無いので「振込み」でも保存されない
        $this->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('shop.checkout.index', $this->carrot));

        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * 案内ページを離れて（「戻る」を押さずに）購入ページを開き直しても、振込先の案内が有効な注文は残り、
     * 新しい「注文を確定する」で前の PaymentIntent をキャンセルする（既存の動き）
     */
    #[Test]
    public function 新しい確定で前の銀行振込をキャンセルする(): void
    {
        $this->putOrder(PaymentMethod::CustomerBalance, null, 'pi_test_old');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_test_old');
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn(new BankTransferIntentData('pi_test_new', 'requires_action', 4844, new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '7654321', 'ストライプジャパン', 4844)));
        });

        // 通常の訪問では会員登録の内容で表示されるが、保存待ちの注文は残っている
        $this->assertDefaults($this->actingAs($this->user)->get(route('shop.checkout.index', $this->carrot))->getContent());
        $this->assertSame('pi_test_old', session('pending_order')['payment_intent_id']);

        $this->post(route('shop.checkout.payment'), $this->paymentData($this->user, [$this->carrot->id => 3], [
            'source' => 'item',
            'address_id' => 'delivery_'.$this->okinawa->id,
            'payment_method' => 'customer_balance',
        ]))->assertRedirect(route('shop.checkout.bank-transfer'));

        // 人参2kg×3=6kg → 100サイズ（沖縄）で、418×3 + 3,590 = 4,844円
        $this->assertSame('pi_test_new', session('pending_order')['payment_intent_id']);
        $this->assertSame(4844, session('pending_order')['total_price']);
    }

    /**
     * 保存待ちの注文に代金引換が残っている古いセッションで GET /purchase/success を開いても、購入データを保存しない。
     * 購入ページへ戻して、お支払いを確認できなかったことを案内する
     */
    #[Test]
    public function 残っている代金引換の注文は購入完了ページを開いても保存されない(): void
    {
        $this->putOrder(PaymentMethod::Cod);

        $this->actingAs($this->user)
            ->get(route('shop.checkout.success'))
            ->assertRedirect(route('shop.checkout.index', $this->carrot))
            ->assertSessionHasErrors('payment');

        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * 「注文を確定する」（代金引換）は、その場で保存してサンクスページへ移動する。購入完了ページ（success）を開き直しても二重に保存されない
     */
    #[Test]
    public function 代金引換は確定した場で保存されサンクスページへ移動する(): void
    {
        $this->actingAs($this->user)
            ->post(route('shop.checkout.payment'), $this->paymentData($this->user, [$this->carrot->id => 1], ['source' => 'item', 'payment_method' => 'cod']))
            ->assertRedirect(route('shop.checkout.thanks'));

        $this->assertSame(1, Purchase_history::count());

        $this->get(route('shop.checkout.success'))->assertRedirect(route('top.index'));

        $this->assertSame(1, Purchase_history::count());
    }
}
