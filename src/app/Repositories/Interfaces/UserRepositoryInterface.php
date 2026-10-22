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
}
