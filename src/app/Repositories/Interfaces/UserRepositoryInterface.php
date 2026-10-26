<?php

namespace App\Repositories\Interfaces;

use App\Models\User;

/**
 * 会員リポジトリインターフェース
 */
interface UserRepositoryInterface
{
    /**
     * 会員の Stripe Customer ID を保存
     */
    public function updateStripeCustomerId(User $user, string $customerId): void;

    /**
     * 会員の登録情報を更新（パスワードは渡されたときだけ変更する）
     *
     * @param  array{last_name: string, first_name: string, prefecture: string, city: string, tell: string, email: string, password?: string}  $attributes
     */
    public function update(User $user, array $attributes): void;

    /**
     * 会員を退会させる（論理削除。購入履歴・予約・配送先住所は消さない）
     */
    public function withdraw(User $user): void;
}
