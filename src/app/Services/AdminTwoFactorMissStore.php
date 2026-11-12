<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\RateLimiter;

/**
 * 管理者ごとの、確認コードの間違いの回数をキャッシュ（RateLimiter）に出し入れする
 *
 * キーは `admin-two-factor-misses|{管理者のID}`。ログインし直しても別のIPからでも、同じ管理者の間違いは合算する。
 * 数える期間は config の miss_window_seconds、上限は miss_limit。
 */
class AdminTwoFactorMissStore
{
    /**
     * 間違いを1回数える（今回の間違いで上限の回数に達したら true）
     */
    public function hit(Admin $admin): bool
    {
        return RateLimiter::hit($this->key($admin), $this->windowSeconds()) >= $this->limit();
    }

    /**
     * 上限の回数に達しているか
     */
    public function reachedLimit(Admin $admin): bool
    {
        return RateLimiter::tooManyAttempts($this->key($admin), $this->limit());
    }

    /**
     * 上限に達しているとき、あと何秒で試せるか
     */
    public function availableIn(Admin $admin): int
    {
        return RateLimiter::availableIn($this->key($admin));
    }

    /**
     * 回数を0に戻す
     */
    public function clear(Admin $admin): void
    {
        RateLimiter::clear($this->key($admin));
    }

    /**
     * 間違いの回数のキー
     */
    private function key(Admin $admin): string
    {
        return 'admin-two-factor-misses|'.$admin->id;
    }

    /**
     * 上限の回数
     */
    private function limit(): int
    {
        return (int) config('auth.admin_two_factor.miss_limit');
    }

    /**
     * 間違いを数える期間（秒）
     */
    private function windowSeconds(): int
    {
        return (int) config('auth.admin_two_factor.miss_window_seconds');
    }
}
