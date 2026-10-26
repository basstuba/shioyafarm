<?php

namespace Tests\Feature;

use App\Models\Delivery_address;
use App\Models\Purchase_history;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\MyPageTestHelpers;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * 退会（DELETE /mypage/delete）のテスト
 *
 * 退会は論理削除。購入履歴・予約・配送先住所は残し、ログインと同じメールアドレスでの会員登録はできなくなる。
 */
class MyPageWithdrawTest extends TestCase
{
    use MyPageTestHelpers;
    use PurchaseTestHelpers;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeAreas();
        $this->user = User::factory()->create([
            'email' => 'withdraw@example.com',
            'password' => Hash::make('password123'),
            'stripe_customer_id' => 'cus_withdraw',
        ]);
    }

    /**
     * 退会するとログアウトし、トップページへ移動して「退会手続きが完了しました」を表示する
     */
    #[Test]
    public function 退会するとログアウトしてトップページに完了メッセージが表示される(): void
    {
        $response = $this->actingAs($this->user)->delete(route('shop.mypage.delete'));

        $response->assertRedirect(route('top.index'));
        $response->assertSessionHas('flash_message', '退会手続きが完了しました');
        $this->assertGuest();
    }

    /**
     * トップページは、フラッシュされた完了メッセージを表示する
     */
    #[Test]
    public function トップページに退会の完了メッセージが表示される(): void
    {
        $this->get(route('top.index'))->assertDontSee('退会手続きが完了しました');

        $this->withSession(['flash_message' => '退会手続きが完了しました'])
            ->get(route('top.index'))
            ->assertSee('退会手続きが完了しました');
    }

    /**
     * 退会は論理削除で、会員の行（deleted_at）が残る
     */
    #[Test]
    public function 退会は論理削除になる(): void
    {
        $this->actingAs($this->user)->delete(route('shop.mypage.delete'));

        $this->assertSoftDeleted('users', ['id' => $this->user->id]);
        $this->assertNull(User::find($this->user->id));
        $row = User::withTrashed()->find($this->user->id);
        $this->assertNotNull($row);
        $this->assertNotNull($row->deleted_at);
    }

    /**
     * 退会しても、購入履歴・予約・お届け先住所のデータは残る。Stripe の顧客情報も消さない
     */
    #[Test]
    public function 退会しても購入履歴と予約と住所は残る(): void
    {
        $purchase = $this->makePurchase($this->user, [['じゃがいも', 2, 330, 2]]);
        $reservation = $this->makeReservation($this->user);
        $address = Delivery_address::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->delete(route('shop.mypage.delete'));

        $this->assertDatabaseHas('purchase_histories', ['id' => $purchase->id, 'user_id' => $this->user->id]);
        $this->assertDatabaseHas('purchase_history_items', ['purchase_history_id' => $purchase->id]);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'user_id' => $this->user->id]);
        $this->assertDatabaseHas('delivery_addresses', ['id' => $address->id, 'user_id' => $this->user->id]);
        $this->assertSame(1, Purchase_history::count());
        $this->assertSame(1, Reservation::count());
        $this->assertSame(1, Delivery_address::count());
        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'stripe_customer_id' => 'cus_withdraw']);
    }

    /**
     * 退会のメールは送らない
     */
    #[Test]
    public function 退会のメールは送らない(): void
    {
        Mail::fake();

        $this->actingAs($this->user)->delete(route('shop.mypage.delete'));

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    /**
     * 退会したほかの会員のデータは変わらない
     */
    #[Test]
    public function ほかの会員は退会しない(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->user)->delete(route('shop.mypage.delete'));

        $this->assertNotNull(User::find($other->id));
    }

    /**
     * 退会した会員はログインできない
     */
    #[Test]
    public function 退会した会員はログインできない(): void
    {
        $this->actingAs($this->user)->delete(route('shop.mypage.delete'));

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => 'withdraw@example.com', 'password' => 'password123'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /**
     * 退会した会員と同じメールアドレスでは、会員登録できない
     */
    #[Test]
    public function 退会した会員と同じメールアドレスでは会員登録できない(): void
    {
        $this->actingAs($this->user)->delete(route('shop.mypage.delete'));

        $this->from(route('register'))->post(route('register.store'), [
            'last_name' => '山田',
            'first_name' => '太郎',
            'prefecture' => '東京都',
            'city' => '千代田区千代田1-1',
            'tell' => '09011112222',
            'email' => 'withdraw@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['email' => 'このメールアドレスはすでに登録されています']);

        $this->assertGuest();
        $this->assertSame(1, User::withTrashed()->where('email', 'withdraw@example.com')->count());
    }

    /**
     * ほかの端末でログインしていたセッションも、退会後の次のリクエストでログアウト状態になる
     */
    #[Test]
    public function 退会するとほかの端末のセッションもログアウト状態になる(): void
    {
        // 別の端末：ログインしてセッションに会員IDを持つ
        $this->post(route('login.store'), ['email' => 'withdraw@example.com', 'password' => 'password123']);
        $this->assertAuthenticatedAs($this->user);

        // 退会（別の端末の操作を想定して、会員を論理削除する）
        $this->user->delete();

        // 次のリクエストでは、会員が認証から外れてログインページへ移動する
        Auth::forgetGuards();
        $this->get(route('shop.mypage.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * 退会したあとは、マイページを開けない
     */
    #[Test]
    public function 退会後はマイページを開けずログインページへ移動する(): void
    {
        $this->actingAs($this->user)->delete(route('shop.mypage.delete'));

        $this->get(route('shop.mypage.index'))->assertRedirect(route('login'));
    }
}
