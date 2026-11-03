<?php

namespace App\Repositories;

use App\Models\Admin;
use App\Repositories\Interfaces\AdminRepositoryInterface;
use Override;

/**
 * 管理者リポジトリ
 */
class AdminRepository implements AdminRepositoryInterface
{
    /**
     * 管理者のパスワードを更新（ハッシュ化は Admin モデルのキャストが行う）
     */
    #[Override]
    public function updatePassword(Admin $admin, string $password): void
    {
        $admin->forceFill(['password' => $password])->save();
    }
}
