<?php

namespace Tests\Feature;

use App\Livewire\MyPageReservationHistory;
use App\Models\Item;
use App\Models\Purchase_history;
use App\Models\Reservation;
use App\Repositories\Interfaces\ReservationRepositoryInterface;
use App\Services\ReservationLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の予約の発送済みチェック（admin.reservations.update）のテスト
 */
class AdminReservationUpdateTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 発送済みを渡さずに作った予約は、DB の既定値で未発送になる（Factory を通さず、予約の保存の経路で作る）
     */
    #[Test]
    public function 予約の保存の経路で作った予約は未発送で始まる(): void
    {
        $user = $this->makeUser('山田', '太郎');
        $item = Item::factory()->create(['is_reserved_only' => true, 'reservation_limit' => 200]);

        $created = app(ReservationRepositoryInterface::class)->createWithBankTransfer([
            'user_id' => $user->id,
            'item_id' => $item->id,
            'item_name' => $item->name,
            'item_weight' => $item->weight,
            'delivery_name' => '山田 太郎',
            'delivery_prefecture' => '北海道',
            'delivery_city' => '札幌市',
            'delivery_tell' => '09012345678',
            'quantity' => 1,
            'price' => 1450,
            'subtotal' => 1450,
            'payment_method' => 'card',
            'shipping_fee' => 1530,
            'total_price' => 2980,
        ]);

        $this->assertDatabaseHas('reservations', ['id' => $created->id, 'shipped' => 0]);
        $this->assertFalse($created->fresh()->shipped);
    }

    /**
     * 発送済みは一括代入できない（更新は発送済みの更新の経路だけ）
     */
    #[Test]
    public function 発送済みは一括代入の対象ではない(): void
    {
        $this->assertNotContains('shipped', (new Reservation)->getFillable());
        $this->assertNotContains('shipped', (new Purchase_history)->getFillable());
    }

    /**
     * チェックを入れると発送済みになり、外すと未発送に戻る。JSON を返す
     */
    #[Test]
    public function チェックを入れると発送済みになり外すと未発送に戻る(): void
    {
        $this->loginAsAdmin();
        $reservation = $this->makeAdminReservation();

        $this->postJson(route('admin.reservations.update'), ['id' => $reservation->id, 'shipped' => true])
            ->assertOk()
            ->assertExactJson(['id' => $reservation->id, 'shipped' => true]);
        $this->assertTrue($reservation->fresh()->shipped);

        $this->postJson(route('admin.reservations.update'), ['id' => $reservation->id, 'shipped' => false])
            ->assertOk()
            ->assertExactJson(['id' => $reservation->id, 'shipped' => false]);
        $this->assertFalse($reservation->fresh()->shipped);
    }

    /**
     * 更新するのは指定した予約だけで、他の予約は変わらない
     */
    #[Test]
    public function 他の予約は変わらない(): void
    {
        $this->loginAsAdmin();
        [$target, $other] = $this->makeAdminReservations(2);

        $this->postJson(route('admin.reservations.update'), ['id' => $target->id, 'shipped' => true])->assertOk();

        $this->assertTrue($target->fresh()->shipped);
        $this->assertFalse($other->fresh()->shipped);
    }

    /**
     * 存在しない予約は、リクエストの検証ではなく更新の処理が判定し、404 と「予約が見つかりません。」を返す。他の予約は変わらない
     */
    #[Test]
    public function 存在しない予約は404で他の予約は変わらない(): void
    {
        $this->loginAsAdmin();
        $reservation = $this->makeAdminReservation();

        foreach ([$reservation->id + 100, 999999] as $id) {
            $this->postJson(route('admin.reservations.update'), ['id' => $id, 'shipped' => true])
                ->assertNotFound()
                ->assertExactJson(['message' => '予約が見つかりません。']);
        }

        $this->assertFalse($reservation->fresh()->shipped);
    }

    /**
     * 画面を開いたあとに物理削除された予約（会員ごと削除）は、同じ 404 になる
     */
    #[Test]
    public function 削除された予約は404になる(): void
    {
        $this->loginAsAdmin();
        $user = $this->makeWithdrawnUser();
        $reservation = $this->makeAdminReservation($user, overrides: ['shipped' => true]);
        $this->delete(route('admin.users.delete'), ['id' => $user->id]);
        $this->assertDatabaseMissing('reservations', ['id' => $reservation->id]);

        $this->postJson(route('admin.reservations.update'), ['id' => $reservation->id, 'shipped' => false])
            ->assertNotFound()
            ->assertExactJson(['message' => '予約が見つかりません。']);
    }

    /**
     * 入力の誤りは、リダイレクトではなく JSON（422）で返る。注文の同じ番号は予約として扱わない
     */
    #[Test]
    public function 入力の誤りはjsonの422で返る(): void
    {
        $this->loginAsAdmin();
        $reservation = $this->makeAdminReservation();

        $this->postJson(route('admin.reservations.update'), ['shipped' => true])->assertStatus(422)->assertJsonValidationErrors('id');
        $this->postJson(route('admin.reservations.update'), ['id' => $reservation->id])->assertStatus(422)->assertJsonValidationErrors('shipped');
        $this->postJson(route('admin.reservations.update'), ['id' => $reservation->id, 'shipped' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('shipped');
        $this->postJson(route('admin.reservations.update'), ['id' => 'abc', 'shipped' => true])->assertStatus(422)->assertJsonValidationErrors('id');
        // IDの形が不正（0・負の数・小数・指数表記・空）は 422
        foreach ([0, -1, 1.5, '1e3', ''] as $badId) {
            $this->postJson(route('admin.reservations.update'), ['id' => $badId, 'shipped' => true])->assertStatus(422)->assertJsonValidationErrors('id');
        }

        $this->assertFalse($reservation->fresh()->shipped);
    }

    /**
     * 注文の更新と予約の更新は別で、注文のIDで予約は変わらない
     */
    #[Test]
    public function 注文の発送済みと予約の発送済みは別である(): void
    {
        $this->loginAsAdmin();
        $reservation = $this->makeAdminReservation();
        $order = $this->makeOrder();

        $this->postJson(route('admin.reservations.update'), ['id' => $reservation->id, 'shipped' => true])->assertOk();

        $this->assertTrue($reservation->fresh()->shipped);
        $this->assertFalse($order->fresh()->shipped);
    }

    /**
     * 未ログイン・会員としてのログインでは変更できない
     */
    #[Test]
    public function 未ログインと会員は発送済みを変更できない(): void
    {
        $reservation = $this->makeAdminReservation();

        $this->post(route('admin.reservations.update'), ['id' => $reservation->id, 'shipped' => true])->assertRedirect(route('admin.login'));
        $this->postJson(route('admin.reservations.update'), ['id' => $reservation->id, 'shipped' => true])->assertUnauthorized();
        $this->assertFalse($reservation->fresh()->shipped);

        $this->loginAsMember();
        $this->post(route('admin.reservations.update'), ['id' => $reservation->id, 'shipped' => true])->assertRedirect(route('admin.login'));
        $this->assertFalse($reservation->fresh()->shipped);
    }

    /**
     * CSRFトークンがないとき（419）は変更されない
     */
    #[Test]
    public function csrfトークンがないと変更できない(): void
    {
        $this->app['env'] = 'local';
        $this->loginAsAdmin();
        $reservation = $this->makeAdminReservation();

        $this->postJson(route('admin.reservations.update'), ['id' => $reservation->id, 'shipped' => true])->assertStatus(419);

        $this->assertFalse($reservation->fresh()->shipped);
    }

    /**
     * 発送済みにしても、メールは送られず、マイページの予約履歴に発送の表示は出ず、予約可能数の残りは変わらない
     */
    #[Test]
    public function 発送済みにしてもメールは送られずマイページと予約可能数は変わらない(): void
    {
        Mail::fake();
        $user = $this->makeUser('山田', '太郎');
        $reservation = $this->makeAdminReservation($user, overrides: ['quantity' => 3, 'subtotal' => 4350, 'total_price' => 5880]);
        $item = $reservation->item;
        $remaining = app(ReservationLimitService::class)->remaining($item);

        $this->loginAsAdmin();
        $this->postJson(route('admin.reservations.update'), ['id' => $reservation->id, 'shipped' => true])->assertOk();

        $this->assertSame($remaining, app(ReservationLimitService::class)->remaining($item->fresh()));
        $this->assertSame(197, $remaining);

        Auth::guard('admin')->logout();
        Auth::shouldUse('web');
        $this->actingAs($user)
            ->get(route('shop.mypage.index'))
            ->assertOk()
            ->assertDontSee('発送');
        Livewire::test(MyPageReservationHistory::class)->assertDontSee('発送');
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }
}
