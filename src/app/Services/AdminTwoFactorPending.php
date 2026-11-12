<?php

namespace App\Services;

use App\Models\Admin;

/**
 * 確認コードの入力待ちの管理者を探した結果
 *
 * 入力待ちの管理者がいれば admin に入る。いないときは、入力待ちを解いた理由が、パスワードが変わったことかどうかを持つ。
 */
final readonly class AdminTwoFactorPending
{
    private function __construct(
        public ?Admin $admin,
        /** 入力待ちの途中でパスワードが変わったため、入力待ちを解いた */
        public bool $passwordChanged,
    ) {}

    /**
     * 入力待ちの管理者がいる
     */
    public static function found(Admin $admin): self
    {
        return new self($admin, false);
    }

    /**
     * 入力待ちではない（もともとない・オフ・管理者がいない）
     */
    public static function none(): self
    {
        return new self(null, false);
    }

    /**
     * 入力待ちの途中でパスワードが変わったため、入力待ちを解いた
     */
    public static function passwordChanged(): self
    {
        return new self(null, true);
    }
}
