<?php

namespace Tests\Unit;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Services\Payment\BankTransferInstructions;
use App\Services\PendingOrder;
use App\Support\BankTransferPage;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 「振込先のご案内」ページの表示用の値（注文・送信先・文言・振込先の項目・金額の内訳）のテスト
 */
class BankTransferPageTest extends TestCase
{
    /**
     * 振込先のある保存待ちの注文を作る（小計418・送料940・代引手数料0・合計1358、振込金額12,345円）
     */
    private function order(bool $withBankTransfer = true): PendingOrder
    {
        return new PendingOrder(
            1,
            PurchaseSource::Item,
            'user_1',
            '塩谷太郎',
            '09012345678',
            '北海道',
            '札幌市1-1',
            PaymentMethod::CustomerBalance,
            [['item_id' => 7, 'name' => '人参', 'weight' => 2, 'quantity' => 1, 'price' => 418, 'subtotal' => 418]],
            418,
            940,
            0,
            1358,
            bankTransfer: $withBankTransfer ? new BankTransferInstructions('テスト銀行', '本店', 'futsu', '1234567', 'シオヤ タロウ', 12345) : null,
        );
    }

    /**
     * 購入のときは、購入の送信先と、発送の日数の文言になる
     */
    #[Test]
    public function 購入の送信先と文言(): void
    {
        config(['shipping.ship_days_text' => '3〜5営業日']);
        $order = $this->order();

        $page = BankTransferPage::purchase($order);

        $this->assertSame($order, $page['order']);
        $this->assertSame('shop.checkout.bank-transfer.cancel', $page['cancelRoute']);
        $this->assertSame('shop.checkout.bank-transfer.confirm', $page['confirmRoute']);
        $this->assertSame('ご注文内容', $page['orderHeading']);
        $this->assertSame('下記の口座へお振込みください。お振込みの確認後、3〜5営業日以内に発送いたします。', $page['shippingNotice']);
    }

    /**
     * 予約のときは、予約の送信先と、発送の時期の文言になる
     */
    #[Test]
    public function 予約の送信先と文言(): void
    {
        config(['shipping.reservation_ship_period_text' => '9月中']);
        $order = $this->order();

        $page = BankTransferPage::reservation($order);

        $this->assertSame($order, $page['order']);
        $this->assertSame('shop.reservation.bank-transfer.cancel', $page['cancelRoute']);
        $this->assertSame('shop.reservation.bank-transfer.confirm', $page['confirmRoute']);
        $this->assertSame('ご予約内容', $page['orderHeading']);
        $this->assertSame('下記の口座へお振込みください。お振込みの確認後、商品は9月中に発送いたします。', $page['shippingNotice']);
    }

    /**
     * ルート名はどちらも定義されている
     */
    #[Test]
    public function ルート名は実在する(): void
    {
        foreach ([BankTransferPage::purchase($this->order()), BankTransferPage::reservation($this->order())] as $page) {
            $this->assertNotEmpty(route($page['cancelRoute']));
            $this->assertNotEmpty(route($page['confirmRoute']));
        }
    }

    /**
     * 振込先の項目は、項目名・値・クラスの順で6つ。金額は3桁区切りに「円」を付ける
     */
    #[Test]
    public function 振込先の項目(): void
    {
        $rows = BankTransferPage::purchase($this->order())['rows'];

        $this->assertSame(['銀行名', '支店名', '口座種別', '口座番号', '口座名義', '振込金額'], array_keys($rows));
        $this->assertSame(['テスト銀行', 'text-xl break-words'], $rows['銀行名']);
        $this->assertSame('普通', $rows['口座種別'][0]);
        $this->assertSame(['12,345円', 'font-maru text-2xl text-primary-text'], $rows['振込金額']);
    }

    /**
     * 金額の内訳は、注文の小計・送料・代引手数料から作る（銀行振込なので重さは使わない）
     */
    #[Test]
    public function 金額の内訳(): void
    {
        $summary = BankTransferPage::reservation($this->order())['summary'];

        $this->assertSame(418, $summary->subtotal);
        $this->assertSame(940, $summary->shippingFee);
        $this->assertSame(0, $summary->codFee);
        $this->assertSame(1358, $summary->total);
    }

    /**
     * 振込先のない注文では、案内ページの値を作れない（コントローラーが、振込先のある注文だけを渡す）
     */
    #[Test]
    public function 振込先のない注文は例外になる(): void
    {
        $this->expectException(LogicException::class);

        BankTransferPage::purchase($this->order(false));
    }
}
