<?php

namespace Tests\Unit;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Services\Payment\BankTransferInstructions;
use App\Services\PendingOrder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 保存待ちの注文（セッションに入れる値）のテスト
 */
class PendingOrderTest extends TestCase
{
    /**
     * テスト用の注文を作る
     */
    private function makeOrder(PurchaseSource $source = PurchaseSource::Item, ?string $sessionId = null): PendingOrder
    {
        return new PendingOrder(
            1,
            $source,
            'user_1',
            '塩谷太郎',
            '09012345678',
            '北海道',
            '札幌市1-1',
            PaymentMethod::Card,
            [['item_id' => 7, 'name' => '人参 2kg', 'quantity' => 1, 'price' => 418, 'subtotal' => 418]],
            418,
            940,
            0,
            1358,
            $sessionId,
        );
    }

    /**
     * 配列にして復元しても、同じ内容になる
     */
    #[Test]
    public function 配列にして復元できる(): void
    {
        $order = $this->makeOrder(PurchaseSource::Item, 'cs_test_1');

        $restored = PendingOrder::fromArray($order->toArray());

        $this->assertEquals($order, $restored);
        $this->assertSame('cs_test_1', $restored->checkoutSessionId);
    }

    /**
     * 作った時点で支払い済みだった銀行振込の注文（PaymentIntent があり、振込先がない）だけを判別できる
     */
    #[Test]
    public function 支払い済みの銀行振込の注文を判別できる(): void
    {
        $bank = new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 1358);
        $card = $this->makeOrder();
        $transfer = new PendingOrder(1, PurchaseSource::Item, 'user_1', '塩谷太郎', '09012345678', '北海道', '札幌市1-1', PaymentMethod::CustomerBalance, $card->items, 418, 940, 0, 1358);

        $this->assertFalse($card->isPaidBankTransfer());
        $this->assertFalse($transfer->isPaidBankTransfer());
        $this->assertFalse($transfer->withBankTransfer('pi_test_1', $bank)->isPaidBankTransfer());
        $this->assertTrue($transfer->withPaymentIntentId('pi_test_1')->isPaidBankTransfer());
        $this->assertFalse($transfer->withPaymentIntentId('pi_test_1')->withoutBankTransfer()->isPaidBankTransfer());
    }

    /**
     * 銀行振込の PaymentIntent ID と振込先を付けて配列にしても復元でき、外すと元に戻る
     */
    #[Test]
    public function 銀行振込の振込先を付けたり外したりできる(): void
    {
        $bank = new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 1358);
        $order = $this->makeOrder();

        $with = $order->withBankTransfer('pi_test_1', $bank);

        $this->assertNull($order->paymentIntentId);
        $this->assertSame('pi_test_1', $with->paymentIntentId);
        $this->assertEquals($bank, $with->bankTransfer);
        $this->assertEquals($with, PendingOrder::fromArray($with->toArray()));

        $without = $with->withoutBankTransfer();
        $this->assertNull($without->paymentIntentId);
        $this->assertNull($without->bankTransfer);
        $this->assertEquals($order, $without);
    }

    /**
     * 振込先の形式が正しくないときは、振込先なしとして復元する
     */
    #[Test]
    public function 壊れた振込先は無視して復元する(): void
    {
        $valid = $this->makeOrder()->toArray();

        $restored = PendingOrder::fromArray([...$valid, 'payment_intent_id' => 'pi_1', 'bank_transfer' => ['bank_name' => 'x']]);

        $this->assertSame('pi_1', $restored->paymentIntentId);
        $this->assertNull($restored->bankTransfer);
        $this->assertNull(BankTransferInstructions::fromArray(['bank_name' => 'x', 'branch_name' => 'y', 'account_type' => 'futsu', 'account_number' => '1', 'account_holder_name' => 'z', 'amount' => '100']));
    }

    /**
     * 口座種別の日本語表記（普通・当座。未知の値はそのまま）
     */
    #[Test]
    public function 口座種別を日本語で表記する(): void
    {
        $label = fn (string $type): string => (new BankTransferInstructions('b', 'b', $type, '1', 'n', 1))->accountTypeLabel();

        $this->assertSame('普通', $label('futsu'));
        $this->assertSame('当座', $label('toza'));
        $this->assertSame('other', $label('other'));
    }

    /**
     * セッションIDを付けた新しい注文を返し、元の注文は変わらない
     */
    #[Test]
    public function セッション_i_dを付けても元の注文は変わらない(): void
    {
        $order = $this->makeOrder();

        $with = $order->withCheckoutSessionId('cs_test_2');

        $this->assertNull($order->checkoutSessionId);
        $this->assertSame('cs_test_2', $with->checkoutSessionId);
        $this->assertSame(1358, $with->totalPrice);
    }

    /**
     * 商品詳細からの注文は商品IDを返し、カートからの注文は null を返す。購入ページとの一致も判定できる
     */
    #[Test]
    public function 購入ページと一致するかを判定できる(): void
    {
        $item = $this->makeOrder(PurchaseSource::Item);
        $cart = $this->makeOrder(PurchaseSource::Cart);

        $this->assertSame(7, $item->itemId());
        $this->assertNull($cart->itemId());
        $this->assertTrue($item->matches(PurchaseSource::Item, 7));
        $this->assertFalse($item->matches(PurchaseSource::Item, 8));
        $this->assertFalse($item->matches(PurchaseSource::Cart, null));
        $this->assertTrue($cart->matches(PurchaseSource::Cart, null));
        $this->assertFalse($cart->matches(PurchaseSource::Item, 7));
    }

    /**
     * 形式が正しくない配列からは復元しない
     */
    #[Test]
    public function 壊れた配列からは復元しない(): void
    {
        $valid = $this->makeOrder()->toArray();

        $this->assertNull(PendingOrder::fromArray([]));
        $this->assertNull(PendingOrder::fromArray([...$valid, 'source' => 'unknown']));
        $this->assertNull(PendingOrder::fromArray([...$valid, 'payment_method' => 'paypay']));
        $this->assertNull(PendingOrder::fromArray([...$valid, 'items' => []]));
        $this->assertNull(PendingOrder::fromArray([...$valid, 'items' => 'x']));
        $this->assertNull(PendingOrder::fromArray([...$valid, 'total_price' => '1358']));
        $this->assertNull(PendingOrder::fromArray([...$valid, 'delivery_name' => null]));
        $order = PendingOrder::fromArray([...$valid, 'checkout_session_id' => 123]);
        $this->assertNotNull($order);
        $this->assertNull($order->checkoutSessionId);
    }
}
