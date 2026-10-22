<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use App\Models\Purchase_history_item;
use App\Services\Payment\BankTransferInstructions;
use App\Services\Payment\BankTransferIntentData;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * 銀行振込の「振込み」（POST /purchase/bank-transfer/confirm）と、サンクスページ（GET /purchase/thanks）のテスト
 */
class BankTransferConfirmTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 銀行振込の PaymentIntent を返すモックに差し替える（retrieve は指定した回数だけ呼ばれる）
     */
    private function mockBankIntent(string $id, string $status, int $amount, bool $withInstructions = true, int $times = 1): void
    {
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($id, $status, $amount, $withInstructions, $times): void {
            $mock->shouldReceive('retrieveBankTransferIntent')
                ->times($times)
                ->with($id)
                ->andReturn(new BankTransferIntentData(
                    $id,
                    $status,
                    $amount,
                    $withInstructions ? new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', $amount) : null,
                ));
        });
    }

    /**
     * Stripe に何も問い合わせないことを保証する
     */
    private function gatewayNeverCalled(): void
    {
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            foreach (['createCheckoutSession', 'retrieveCheckoutSession', 'expireCheckoutSession', 'ensureCustomer', 'createBankTransferIntent', 'retrieveBankTransferIntent', 'cancelPaymentIntent'] as $method) {
                $mock->shouldNotReceive($method);
            }
        });
    }

    /**
     * 未ログインはログインページへ移動する
     */
    #[Test]
    public function 未ログインはログインページへ移動する(): void
    {
        $this->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('login'));
        $this->get(route('shop.checkout.thanks'))->assertRedirect(route('login'));
    }

    /**
     * 「振込み」は POST だけ。GET では実行できない（プリフェッチ・外部ページの画像タグなどで確定しない）
     */
    #[Test]
    public function 振込みは_ge_tでは実行できない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->gatewayNeverCalled();

        $this->actingAs($user)->get(route('shop.checkout.bank-transfer.confirm'))->assertStatus(405);
        // カードの success_url（GET）で銀行振込の注文は保存されず、案内ページへ戻る
        $this->get(route('shop.checkout.success'))->assertRedirect(route('shop.checkout.bank-transfer'));

        $this->assertSame(0, Purchase_history::count());
        $this->assertNotNull(session('pending_order'));
    }

    /**
     * 「振込み」で PaymentIntent の状態（requires_action で振込先あり）と金額を確認して保存し、サンクスページへリダイレクトする（S-16, AC-9）
     */
    #[Test]
    public function 振込みで確認して保存しサンクスページへ進む(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mockBankIntent('pi_test_bank', 'requires_action', 3723);

        $this->actingAs($user)
            ->post(route('shop.checkout.bank-transfer.confirm'))
            ->assertRedirect(route('shop.checkout.thanks'));

        $this->get(route('shop.checkout.thanks'))
            ->assertOk()
            ->assertViewIs('shop.purchase_thanks')
            ->assertSee('ご注文ありがとうございました。');

        $history = Purchase_history::sole();
        $this->assertSame('customer_balance', $history->payment_method);
        $this->assertSame(1850, $history->shipping_fee);
        $this->assertSame(0, $history->cod_fee);
        $this->assertSame(3723, $history->total_price);
        $this->assertSame(3, $history->purchaseHistoryItems()->count());
        $this->assertNull(session('pending_order'));
        $this->assertEmpty(session('cart'));
    }

    /**
     * 振込先データも同じトランザクションで1件保存され、内容は案内ページに表示した振込先・金額と同じ。口座種別は Stripe の値のまま保存する
     */
    #[Test]
    public function 振込先を同時に保存し案内ページの内容と一致する(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mockBankIntent('pi_test_bank', 'requires_action', 3723);

        $this->actingAs($user)->get(route('shop.checkout.bank-transfer'))
            ->assertSeeInOrder(['いろは銀行', '中央支店', '普通', '1234567', 'ストライプジャパン', '3,723円']);

        $this->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('shop.checkout.thanks'));

        $history = Purchase_history::sole();
        $transfer = Purchase_bank_transfer::sole();
        $this->assertSame($history->id, $transfer->purchase_history_id);
        $this->assertSame('pi_test_bank', $transfer->stripe_payment_intent_id);
        $this->assertSame('いろは銀行', $transfer->bank_name);
        $this->assertSame('中央支店', $transfer->branch_name);
        $this->assertSame('futsu', $transfer->account_type);
        $this->assertSame('1234567', $transfer->account_number);
        $this->assertSame('ストライプジャパン', $transfer->account_holder_name);
        $this->assertSame(3723, $transfer->amount);
    }

    /**
     * 入金処理中（processing）・入金済み（succeeded）の PaymentIntent でも保存する
     *
     * @return array<string, array{string}>
     */
    public static function 保存してよい状態(): array
    {
        return ['入金処理中' => ['processing'], '入金済み' => ['succeeded']];
    }

    #[Test]
    #[DataProvider('保存してよい状態')]
    public function 入金処理中や入金済みでも保存する(string $status): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mockBankIntent('pi_test_bank', $status, 3723, false);

        $this->actingAs($user)->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('shop.checkout.thanks'));

        $this->assertSame(1, Purchase_history::count());
        $this->assertSame(1, Purchase_bank_transfer::count());
    }

    /**
     * キャンセル済み・振込先なし・金額の不一致のときは保存せず、購入ページへ戻してエラーを表示する（保存待ちの注文とカートは残す）
     */
    #[Test]
    public function 確認できなければ保存されない(): void
    {
        [$user, $items] = $this->makeCartScenario();

        foreach ([
            ['canceled', 3723, false],
            ['requires_payment_method', 3723, false],
            ['requires_action', 3723, false],
            ['requires_action', 3000, true],
        ] as [$status, $amount, $withInstructions]) {
            $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_x');
            $this->mockBankIntent('pi_test_x', $status, $amount, $withInstructions);

            $this->actingAs($user)
                ->post(route('shop.checkout.bank-transfer.confirm'))
                ->assertRedirect(route('shop.checkout.index'))
                ->assertSessionHasErrors('payment');

            $this->assertSame(0, Purchase_history::count());
            $this->assertNotNull(session('pending_order'));
            $this->assertNotNull(session('cart'));
        }
    }

    /**
     * Stripe に問い合わせられなかった（通信エラー）ときは、保存待ちの注文を残したまま案内ページへ戻し、
     * 「もう一度『振込み』を押してください」と案内する（購入ページへ戻して再決済させない）
     */
    #[Test]
    public function 通信エラーのときは案内ページへ戻って再度の振込みを案内する(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_err');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveBankTransferIntent')->twice()->andReturnUsing(
                fn () => throw new PaymentGatewayException('通信エラー'),
                fn () => new BankTransferIntentData('pi_test_err', 'requires_action', 3723, new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 3723)),
            );
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.bank-transfer.confirm'))
            ->assertRedirect(route('shop.checkout.bank-transfer'))
            ->assertSessionHasErrors('payment');

        $this->get(route('shop.checkout.bank-transfer'))
            ->assertOk()
            ->assertSee('お支払いの確認に失敗しました。時間をおいて、もう一度「振込み」を押してください')
            ->assertDontSee('もう一度「注文を確定する」');
        $this->assertSame(0, Purchase_history::count());
        $this->assertNotNull(session('pending_order'));

        // もう一度「振込み」を押すと、確認できて保存される
        $this->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('shop.checkout.thanks'));
        $this->assertSame(1, Purchase_history::count());
    }

    /**
     * PaymentIntent がない保存待ちの注文（「戻る」のあと）・銀行振込でない注文では保存されない
     */
    #[Test]
    public function 銀行振込でないか_payment_intentがなければ保存されない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->gatewayNeverCalled();

        $this->putPending($user, $items, PaymentMethod::CustomerBalance);
        $this->actingAs($user)->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('shop.checkout.index'));

        $this->putPending($user, $items, PaymentMethod::Cod);
        $this->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('shop.checkout.index'));

        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * 保存待ちの注文がなければトップページへ戻る。別の会員の注文では保存されない
     */
    #[Test]
    public function 保存待ちの注文がなければトップへ戻り別の会員の注文は保存されない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->gatewayNeverCalled();

        $this->actingAs($user)->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('top.index'));

        $this->putPending($this->makeUser(), $items, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('top.index'));

        $this->assertSame(0, Purchase_history::count());
    }

    /**
     * サンクスページは保存した直後の1回だけ表示する。再読み込み・直接アクセスはトップページへ戻る
     */
    #[Test]
    public function サンクスページは1回だけ表示する(): void
    {
        [$user, $items] = $this->makeCartScenario();

        // 直接アクセスはトップへ
        $this->actingAs($user)->get(route('shop.checkout.thanks'))->assertRedirect(route('top.index'));

        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mockBankIntent('pi_test_bank', 'requires_action', 3723);
        $this->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('shop.checkout.thanks'));

        $this->get(route('shop.checkout.thanks'))->assertOk();
        // 再読み込み
        $this->get(route('shop.checkout.thanks'))->assertRedirect(route('top.index'));
    }

    /**
     * 保存後に「振込み」の再押下・案内ページの開き直しをしても、購入データ・振込先データは増えない（S-25, AC-12）
     */
    #[Test]
    public function 二重に登録されない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mockBankIntent('pi_test_bank', 'requires_action', 3723);

        $this->actingAs($user)->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('shop.checkout.thanks'));
        $this->get(route('shop.checkout.thanks'))->assertOk();

        // ブラウザの「戻る」で案内ページを開き直す・「振込み」をもう一度押す（Stripe へは問い合わせない＝モックは1回だけ）
        $this->get(route('shop.checkout.bank-transfer'))->assertRedirect(route('top.index'));
        $this->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('top.index'));
        $this->get(route('shop.checkout.success'))->assertRedirect(route('top.index'));

        $this->assertSame(1, Purchase_history::count());
        $this->assertSame(3, Purchase_history_item::count());
        $this->assertSame(1, Purchase_bank_transfer::count());
    }

    /**
     * 同時に届いた2つ目のリクエスト（セッションに保存待ちの注文が残っている）でも、二重に保存せず、500にもならない。
     * 保存済みの注文として扱い、サンクスページへ進める
     */
    #[Test]
    public function 同時リクエストでも二重に保存されない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $order = $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $cart = session('cart');
        $this->mockBankIntent('pi_test_bank', 'requires_action', 3723);

        $this->actingAs($user)->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('shop.checkout.thanks'));

        // 1つ目の保存が終わる前に読み込まれたセッション（同じ保存待ちの注文・カートが残っている）で届く2つ目のリクエスト
        session(['pending_order' => $order->toArray(), 'cart' => $cart]);
        $this->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('shop.checkout.thanks'));

        $this->assertSame(1, Purchase_history::count());
        $this->assertSame(1, Purchase_bank_transfer::count());
        // 2つ目のリクエストも、保存待ちの注文とカートを片づける
        $this->assertNull(session('pending_order'));
        $this->assertEmpty(session('cart'));
    }

    /**
     * 記録（キャッシュ）が無くても、同じ PaymentIntent の振込先がすでにある（unique 違反）ときは、
     * 500にせず、購入履歴・明細は作らずに（ロールバック）保存済みとして扱う
     */
    #[Test]
    public function 振込先のunique違反は保存済みとして扱い500にならない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        Purchase_bank_transfer::factory()->create(['stripe_payment_intent_id' => 'pi_test_bank']);
        $before = Purchase_history::count();
        $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        $this->mockBankIntent('pi_test_bank', 'requires_action', 3723);

        $this->actingAs($user)->post(route('shop.checkout.bank-transfer.confirm'))->assertRedirect(route('shop.checkout.thanks'));

        // 購入履歴・明細は増えず（トランザクションでロールバック）、保存待ちの注文は片づく
        $this->assertSame($before, Purchase_history::count());
        $this->assertSame(0, Purchase_history_item::count());
        $this->assertSame(1, Purchase_bank_transfer::count());
        $this->assertNull(session('pending_order'));
    }

    /**
     * unique 違反以外の保存の失敗は握りつぶさず、購入履歴も明細も作られず（トランザクション）、保存待ちの注文とカートが残る
     */
    #[Test]
    public function 保存に失敗したらどちらも作られない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $order = $this->putPending($user, $items, PaymentMethod::CustomerBalance, null, 'pi_test_bank');
        // 3件目の明細の商品を、存在しない商品IDにして外部キーエラーにする
        $pending = $order->toArray();
        $pending['items'][2]['item_id'] = 999999;
        session(['pending_order' => $pending]);
        $this->mockBankIntent('pi_test_bank', 'requires_action', 3723);

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($user)->post(route('shop.checkout.bank-transfer.confirm'));
            $this->fail('保存に失敗するはずが、例外が発生しなかった');
        } catch (QueryException) {
            // 想定どおり
        }

        $this->assertSame(0, Purchase_history::count());
        $this->assertSame(0, Purchase_bank_transfer::count());
        $this->assertNotNull(session('pending_order'));
        $this->assertNotNull(session('cart'));
    }
}
