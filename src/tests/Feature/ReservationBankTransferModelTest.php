<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Reservation_bank_transfer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 予約データ（reservations）の追加項目と、振込先データ（reservation_bank_transfers）のモデル・テーブルのテスト
 */
class ReservationBankTransferModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 予約に送料と合計金額の列があり、代引手数料の列はない
     */
    #[Test]
    public function 予約に送料と合計金額が保存される(): void
    {
        $this->assertTrue(Schema::hasColumns('reservations', ['shipping_fee', 'total_price']));
        $this->assertFalse(Schema::hasColumn('reservations', 'cod_fee'));

        $reservation = Reservation::factory()->create(['shipping_fee' => 1530, 'total_price' => 2980]);

        $this->assertSame(1530, $reservation->fresh()->shipping_fee);
        $this->assertSame(2980, $reservation->fresh()->total_price);
    }

    /**
     * 予約から振込先を、振込先から予約をたどれる
     */
    #[Test]
    public function 予約と振込先は一対一で関連づく(): void
    {
        $reservation = Reservation::factory()->create(['payment_method' => 'customer_balance']);
        $transfer = Reservation_bank_transfer::factory()->create(['reservation_id' => $reservation->id]);

        $this->assertTrue($reservation->reservationBankTransfer->is($transfer));
        $this->assertTrue($transfer->reservation->is($reservation));
        $this->assertNull(Reservation::factory()->create()->reservationBankTransfer);
    }

    /**
     * 予約1件につき振込先は1件まで、PaymentIntent ID も重複できない
     */
    #[Test]
    public function 予約と_payment_intentは重複できない(): void
    {
        $transfer = Reservation_bank_transfer::factory()->create();

        try {
            Reservation_bank_transfer::factory()->create(['reservation_id' => $transfer->reservation_id]);
            $this->fail('reservation_id の重複は保存できないはず');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(QueryException::class);
        Reservation_bank_transfer::factory()->create(['stripe_payment_intent_id' => $transfer->stripe_payment_intent_id]);
    }

    /**
     * 支払い済みで振込先の案内がない予約は、振込先の各項目を null にして保存できる
     */
    #[Test]
    public function 振込先の各項目はnullにできる(): void
    {
        $transfer = Reservation_bank_transfer::factory()->create([
            'bank_name' => null,
            'branch_name' => null,
            'account_type' => null,
            'account_number' => null,
            'account_holder_name' => null,
        ]);

        $this->assertNull($transfer->fresh()->bank_name);
        $this->assertSame(2980, $transfer->fresh()->amount);
    }

    /**
     * 予約を削除すると、振込先も一緒に削除される
     */
    #[Test]
    public function 予約を削除すると振込先も削除される(): void
    {
        $transfer = Reservation_bank_transfer::factory()->create();

        $transfer->reservation->delete();

        $this->assertSame(0, Reservation_bank_transfer::count());
    }
}
