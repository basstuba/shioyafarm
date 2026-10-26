<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Override;

/**
 * 会員リポジトリ
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * 会員の Stripe Customer ID を保存
     *
     * stripe_customer_id は fillable に含めないため、forceFill で保存する。
     */
    #[Override]
    public function updateStripeCustomerId(User $user, string $customerId): void
    {
        $user->forceFill(['stripe_customer_id' => $customerId])->save();
    }

    /**
     * 会員の登録情報を更新（パスワードは渡されたときだけ変更する）
     *
     * @param  array{last_name: string, first_name: string, prefecture: string, city: string, tell: string, email: string, password?: string}  $attributes
     */
    #[Override]
    public function update(User $user, array $attributes): void
    {
        $user->update($attributes);
    }

    /**
     * 会員を退会させる（論理削除。購入履歴・予約・配送先住所は消さない）
     */
    #[Override]
    public function withdraw(User $user): void
    {
        $user->delete();
    }
}
