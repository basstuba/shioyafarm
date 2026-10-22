<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Models\Item;
use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use App\Models\User;
use App\Services\Payment\BankTransferInstructions;
use App\Services\Payment\BankTransferIntentData;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\PendingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * 銀行振込の「振込先のご案内」ページ（GET /purchase/bank-transfer）と「戻る」（POST /purchase/bank-transfer/cancel）のテスト
 */
class BankTransferPageTest extends TestCase
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
     * 保存待ちの注文（S-2 のカート、北海道、合計3,723円）をセッションに入れる
     *
     * @param  array<string, Item>  $items
     */
    private function putOrder(User $user, array $items, PaymentMethod $method = PaymentMethod::CustomerBalance, bool $withBank = true, PurchaseSource $source = PurchaseSource::Cart): void
    {
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
            0,
            3723,
            null,
            $withBank ? 'pi_test_bank' : null,
            $withBank ? new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 3723) : null,
        ))->toArray()]);
    }

    /**
     * Stripe に何も問い合わせないことを保証する
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
     * 未ログインはログインページへ移動する（案内ページも「戻る」も）
     */
    #[Test]
    public function 未ログインはログインページへ移動する(): void
    {
        $this->get(route('shop.checkout.bank-transfer'))->assertRedirect(route('login'));
        $this->post(route('shop.checkout.bank-transfer.cancel'))->assertRedirect(route('login'));
    }

    /**
     * 振込先（銀行名・支店名・口座種別・口座番号・口座名義・振込金額）と注文内容、案内文、ボタンが表示される（S-16, AC-9）
     */
    #[Test]
    public function 振込先のご案内を表示する(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putOrder($user, $items);

        $this->actingAs($user)
            ->get(route('shop.checkout.bank-transfer'))
            ->assertOk()
            ->assertViewIs('shop.bank_transfer')
            ->assertSee('<title>振込先のご案内 | Shioya Farm</title>', false)
            ->assertSee('振込先のご案内')
            ->assertSeeInOrder(['銀行名', 'いろは銀行', '支店名', '中央支店', '口座種別', '普通', '口座番号', '1234567', '口座名義', 'ストライプジャパン', '振込金額', '3,723円'])
            ->assertSee('じゃがいも 2kg × 2個')
            ->assertSee('玉ねぎ 5kg × 1個')
            ->assertSee('人参 2kg × 1個')
            ->assertSeeInOrder(['小計', '1,873円', '送料', '1,850円', '合計', '3,723円'])
            ->assertSee('山田花子')
            ->assertSee('北海道札幌市中央区1-1-1')
            ->assertSee('下記の口座へお振込みください。お振込みの確認後、3~5営業日以内に発送いたします。')
            ->assertSee('action="'.route('shop.checkout.bank-transfer.cancel').'"', false)
            ->assertSee('戻る')
            ->assertSee('振込み')
            ->assertSee('action="'.route('shop.checkout.bank-transfer.confirm').'"', false)
            ->assertDontSee('href="'.route('shop.checkout.success').'"', false)
            ->assertSee('マイページ')
            ->assertSee('ログアウト');
    }

    /**
     * 口座種別が当座のときは「当座」と表示する
     */
    #[Test]
    public function 口座種別の当座を表示する(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putOrder($user, $items);
        $pending = session('pending_order');
        $pending['bank_transfer']['account_type'] = 'toza';
        session(['pending_order' => $pending]);

        $this->actingAs($user)->get(route('shop.checkout.bank-transfer'))->assertSeeInOrder(['口座種別', '当座']);
    }

    /**
     * 保存待ちの注文がないとき（購入完了後の開き直しなど）はトップページへリダイレクトする
     */
    #[Test]
    public function 保存待ちの注文がなければトップページへリダイレクトする(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('shop.checkout.bank-transfer'))
            ->assertRedirect(route('top.index'));
    }

    /**
     * 銀行振込でない注文・振込先のない注文のときは購入ページへリダイレクトする（商品詳細からならその商品の購入ページ）
     */
    #[Test]
    public function 銀行振込でなければ購入ページへリダイレクトする(): void
    {
        [$user, $items] = $this->cartScenario();

        $this->putOrder($user, $items, PaymentMethod::Cod, false);
        $this->actingAs($user)->get(route('shop.checkout.bank-transfer'))->assertRedirect(route('shop.checkout.index'));

        $this->putOrder($user, $items, PaymentMethod::CustomerBalance, false);
        $this->get(route('shop.checkout.bank-transfer'))->assertRedirect(route('shop.checkout.index'));

        $this->putOrder($user, $items, PaymentMethod::Card, false, PurchaseSource::Item);
        $this->get(route('shop.checkout.bank-transfer'))->assertRedirect(route('shop.checkout.index', $items['potato']));
    }

    /**
     * 別の会員の保存待ちの注文は表示しない
     */
    #[Test]
    public function 別の会員の注文は表示しない(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putOrder($this->makeUser(), $items);

        $this->actingAs($user)->get(route('shop.checkout.bank-transfer'))->assertRedirect(route('top.index'));
    }

    /**
     * 「戻る」：PaymentIntent をキャンセルして購入ページへ戻り、購入データは作らない。入力内容・カートは残る（S-24, AC-9）
     */
    #[Test]
    public function 戻るでキャンセルして購入ページへ戻り入力が残る(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putOrder($user, $items);
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_test_bank');
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.bank-transfer.cancel'))
            ->assertRedirect(route('shop.checkout.index'));

        $this->assertSame(0, Purchase_history::count());
        $this->assertNotNull(session('cart'));
        $pending = session('pending_order');
        $this->assertNull($pending['payment_intent_id']);
        $this->assertNull($pending['bank_transfer']);

        // 購入ページには、入力していた内容（氏名・電話番号・支払方法・数量）が残っている
        $this->get(route('shop.checkout.index'))
            ->assertOk()
            ->assertSee('value="山田花子"', false)
            ->assertSee('value="08011112222"', false)
            ->assertSee('value="customer_balance" wire:model.live="paymentMethod" checked', false)
            ->assertSee('value="2"', false);

        // 振込先の案内はもう表示できない（購入ページへ戻る）
        $this->get(route('shop.checkout.bank-transfer'))->assertRedirect(route('shop.checkout.index'));
    }

    /**
     * 「戻る」：商品詳細から来た注文は、その商品の購入ページへ戻る
     */
    #[Test]
    public function 戻るで商品詳細からの注文はその商品の購入ページへ戻る(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putOrder($user, $items, PaymentMethod::CustomerBalance, true, PurchaseSource::Item);
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntent')->once();
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.bank-transfer.cancel'))
            ->assertRedirect(route('shop.checkout.index', $items['potato']));
    }

    /**
     * 「戻る」：キャンセルに失敗し、PaymentIntent がすでにキャンセル済みなら、購入ページへ戻して振込先の情報を外す
     */
    #[Test]
    public function 戻るでキャンセルに失敗してもキャンセル済みなら購入ページへ戻る(): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putOrder($user, $items);
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntent')->once()->andThrow(new PaymentGatewayException('通信エラー'));
            $mock->shouldReceive('retrieveBankTransferIntent')->once()->with('pi_test_bank')->andReturn(new BankTransferIntentData('pi_test_bank', 'canceled', 3723, null));
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.bank-transfer.cancel'))
            ->assertRedirect(route('shop.checkout.index'));

        $this->assertNull(session('pending_order')['bank_transfer']);
        $this->assertNull(session('pending_order')['payment_intent_id']);
        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * 「戻る」：キャンセルに失敗し、PaymentIntent がすでに入金済み・入金処理中だったときは、外さずに購入データを保存してサンクスページへ進む
     */
    #[Test]
    #[DataProvider('入金済みの状態')]
    public function 戻るでキャンセルに失敗して入金済みなら購入データを保存する(string $status): void
    {
        [$user, $items] = $this->cartScenario();
        $this->putOrder($user, $items);
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($status): void {
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_test_bank')->andThrow(new PaymentGatewayException('すでに入金済み'));
            // 戻る（状態の確認）と、保存のための確認の2回
            $mock->shouldReceive('retrieveBankTransferIntent')->twice()->with('pi_test_bank')->andReturn(new BankTransferIntentData('pi_test_bank', $status, 3723, null));
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.bank-transfer.cancel'))
            ->assertRedirect(route('shop.checkout.thanks'));

        $this->get(route('shop.checkout.thanks'))->assertOk()->assertViewIs('shop.purchase_thanks');
        $this->assertNull(session('pending_order'));
        $this->assertEmpty(session('cart'));
        $this->assertSame(1, Purchase_history::count());
        $this->assertSame(1, Purchase_bank_transfer::count());
    }

    /**
     * キャンセルに失敗したとき、入金済み・入金処理中とみなす PaymentIntent の状態
     *
     * @return array<string, array{string}>
     */
    public static function 入金済みの状態(): array
    {
        return ['入金済み' => ['succeeded'], '入金処理中' => ['processing']];
    }

    /**
     * 「戻る」：キャンセルに失敗し、状態も確認できない・まだ振込待ちのときは、外さずに案内ページへ戻してエラーを表示する（購入データは作らない）
     */
    #[Test]
    public function 戻るでキャンセルに失敗したら案内ページへ戻りエラーを表示する(): void
    {
        [$user, $items] = $this->cartScenario();

        foreach ([
            new PaymentGatewayException('通信エラー'),
            new BankTransferIntentData('pi_test_bank', 'requires_action', 3723, new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 3723)),
        ] as $retrieveResult) {
            $this->putOrder($user, $items);
            $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($retrieveResult): void {
                $mock->shouldReceive('cancelPaymentIntent')->once()->andThrow(new PaymentGatewayException('通信エラー'));
                $expectation = $mock->shouldReceive('retrieveBankTransferIntent')->once();
                $retrieveResult instanceof \Throwable ? $expectation->andThrow($retrieveResult) : $expectation->andReturn($retrieveResult);
            });

            $this->actingAs($user)
                ->post(route('shop.checkout.bank-transfer.cancel'))
                ->assertRedirect(route('shop.checkout.bank-transfer'))
                ->assertSessionHasErrors('payment');

            // 振込先の情報は外さない（案内ページをそのまま開ける）
            $this->assertSame('pi_test_bank', session('pending_order')['payment_intent_id']);
            $this->get(route('shop.checkout.bank-transfer'))->assertOk()->assertSee('振込みの取り消しができませんでした');
        }

        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * 「戻る」：保存待ちの注文がなければ何もせずトップページへ戻る。GET では実行できない
     */
    #[Test]
    public function 戻るは保存待ちの注文がなければトップページへ戻り_ge_tでは実行できない(): void
    {
        $this->gatewayNeverCalled();
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('shop.checkout.bank-transfer.cancel'))->assertRedirect(route('top.index'));
        $this->get(route('shop.checkout.bank-transfer.cancel'))->assertStatus(405);
    }

    /**
     * 注文の確定から「振込み」までの一連の流れ：POST /payment → 案内ページ → 振込み
     */
    #[Test]
    public function 注文確定から振込みまでの流れ(): void
    {
        [$user, $items] = $this->cartScenario();
        $quantities = [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1];
        $instructions = new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 3723);

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($instructions): void {
            $this->stubCustomer($mock);
            $mock->shouldReceive('createBankTransferIntent')->once()->andReturn(new BankTransferIntentData('pi_flow', 'requires_action', 3723, $instructions));
            $mock->shouldReceive('retrieveBankTransferIntent')->once()->with('pi_flow')->andReturn(new BankTransferIntentData('pi_flow', 'requires_action', 3723, $instructions));
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, $quantities, ['payment_method' => 'customer_balance']))
            ->assertRedirect(route('shop.checkout.bank-transfer'));
        $this->get(route('shop.checkout.bank-transfer'))->assertOk()->assertSee('いろは銀行');
        $this->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('shop.checkout.thanks'));
        $this->get(route('shop.checkout.thanks'))->assertOk()->assertSee('ご注文ありがとうございました。');

        $history = Purchase_history::sole();
        $this->assertSame('customer_balance', $history->payment_method);
        $this->assertSame(3723, $history->total_price);
        $this->assertEmpty(session('cart'));
    }
}
