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
}
