<?php

namespace Tests\Concerns;

use App\Models\Admin;
use App\Models\Purchase_history;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * 管理画面の注文一覧・予約一覧のテストで使う、管理者のログインと注文・予約を作る補助
 */
trait AdminPurchaseTestHelpers
{
    use MyPageTestHelpers;

    /**
     * 管理者としてログインする
     */
    private function loginAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    /**
     * 会員としてだけログインする（管理者としては未ログイン）
     */
    private function loginAsMember(): User
    {
        $user = User::factory()->create();
        Auth::shouldUse('web');
        $this->actingAs($user);

        return $user;
    }

    /**
     * 姓名を指定して会員を作る
     */
    private function makeUser(string $lastName, string $firstName): User
    {
        return User::factory()->create(['last_name' => $lastName, 'first_name' => $firstName]);
    }

    /**
     * 注文を作る（会員を指定しないときは新しい会員）。$createdAt は日本時間
     *
     * @param  list<array{0: string, 1: int, 2: int, 3: int}>  $lines  [商品名, 重量, 単価, 数量] の配列
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(?User $user = null, ?string $createdAt = null, array $lines = [['じゃがいも', 2, 330, 1]], string $method = 'card', array $overrides = []): Purchase_history
    {
        return $this->makePurchase(
            $user ?? User::factory()->create(),
            $lines,
            $method,
            $createdAt === null ? $overrides : [...$overrides, 'created_at' => $createdAt],
        );
    }

    /**
     * 注文を n 件作る（作った順＝ID の昇順）
     *
     * @return list<Purchase_history>
     */
    private function makeOrders(int $count): array
    {
        $orders = [];
        for ($i = 0; $i < $count; $i++) {
            $orders[] = $this->makeOrder();
        }

        return $orders;
    }

    /**
     * 予約を作る（会員を指定しないときは新しい会員）。$createdAt は日本時間。商品は「北の恵みセット 10kg 1,450円」
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeAdminReservation(?User $user = null, ?string $createdAt = null, string $method = 'card', array $overrides = []): Reservation
    {
        return $this->makeReservation(
            $user ?? User::factory()->create(),
            $method,
            $createdAt === null ? $overrides : [...$overrides, 'created_at' => $createdAt],
        );
    }

    /**
     * 予約を n 件作る（作った順＝ID の昇順）
     *
     * @return list<Reservation>
     */
    private function makeAdminReservations(int $count): array
    {
        $reservations = [];
        for ($i = 0; $i < $count; $i++) {
            $reservations[] = $this->makeAdminReservation();
        }

        return $reservations;
    }
}
