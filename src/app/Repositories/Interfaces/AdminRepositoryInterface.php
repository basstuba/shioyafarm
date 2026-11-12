<?php

namespace App\Repositories\Interfaces;

use App\Models\Admin;

/**
 * 管理者リポジトリインターフェース
 */
interface AdminRepositoryInterface
{
    /**
     * 管理者のパスワードを更新（ハッシュ化は Admin モデルのキャストが行う）
     */
    public function updatePassword(Admin $admin, string $password): void;

    /**
     * IDから管理者を取得（無ければ null）
     */
    public function find(int $id): ?Admin;
}
