<?php

namespace Tests\Feature;

use App\Mail\PurchaseThanksMail;
use App\Mail\ReservationThanksMail;
use App\Models\Delivery_address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\MyPageTestHelpers;
use Tests\TestCase;

/**
 * 退会済み（論理削除済み）の会員でも、注文・予約・配送先住所から会員を取得できることのテスト
 */
class WithdrawnUserRelationTest extends TestCase
{
    use MyPageTestHelpers;
    use RefreshDatabase;

    /**
     * 退会済みの会員の購入履歴・予約・配送先住所から、会員を取得できる
     */
    #[Test]
    public function 退会済みの会員の注文と予約と住所から会員を取得できる(): void
    {
        $user = User::factory()->create(['last_name' => '退会']);
        $purchase = $this->makePurchase($user);
        $reservation = $this->makeReservation($user);
        $address = Delivery_address::factory()->create(['user_id' => $user->id]);
        $user->delete();

        $this->assertSame($user->id, $purchase->fresh()->user?->id);
        $this->assertSame($user->id, $reservation->fresh()->user?->id);
        $this->assertSame($user->id, $address->fresh()->user?->id);
        $this->assertTrue($purchase->fresh()->user?->trashed());
    }

    /**
     * 退会済みの会員の注文・予約でも、サンクスメールを組み立てられる
     */
    #[Test]
    public function 退会済みの会員の注文でもサンクスメールを組み立てられる(): void
    {
        $user = User::factory()->create(['email' => 'gone@example.com']);
        $purchase = $this->makePurchase($user);
        $reservation = $this->makeReservation($user);
        $user->delete();

        $purchaseMail = new PurchaseThanksMail($purchase->fresh());
        $reservationMail = new ReservationThanksMail($reservation->fresh());

        $this->assertTrue($purchaseMail->hasTo('gone@example.com'));
        $this->assertTrue($reservationMail->hasTo('gone@example.com'));
    }
}
