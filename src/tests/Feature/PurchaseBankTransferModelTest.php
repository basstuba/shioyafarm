<?php

namespace Tests\Feature;

use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 振込先データ（purchase_bank_transfers）のモデル・テーブルのテスト
 */
class PurchaseBankTransferModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 購入履歴から振込先を、振込先から購入履歴をたどれる
     */
    #[Test]
    public function 購入履歴と振込先は一対一で関連づく(): void
    {
        $history = Purchase_history::factory()->create(['payment_method' => 'customer_balance']);
        $transfer = Purchase_bank_transfer::factory()->create(['purchase_history_id' => $history->id]);

        $this->assertTrue($history->purchaseBankTransfer->is($transfer));
        $this->assertTrue($transfer->purchaseHistory->is($history));
        $this->assertNull(Purchase_history::factory()->create()->purchaseBankTransfer);
    }

    /**
     * 購入履歴1件につき振込先は1件まで、PaymentIntent ID も重複できない
     */
    #[Test]
    public function 購入履歴と_payment_intentは重複できない(): void
    {
        $transfer = Purchase_bank_transfer::factory()->create();

        try {
            Purchase_bank_transfer::factory()->create(['purchase_history_id' => $transfer->purchase_history_id]);
            $this->fail('purchase_history_id の重複は保存できないはず');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(QueryException::class);
        Purchase_bank_transfer::factory()->create(['stripe_payment_intent_id' => $transfer->stripe_payment_intent_id]);
    }

    /**
     * 購入履歴を削除すると、振込先も一緒に削除される
     */
    #[Test]
    public function 購入履歴を削除すると振込先も削除される(): void
    {
        $transfer = Purchase_bank_transfer::factory()->create();

        $transfer->purchaseHistory->delete();

        $this->assertSame(0, Purchase_bank_transfer::count());
    }
}
