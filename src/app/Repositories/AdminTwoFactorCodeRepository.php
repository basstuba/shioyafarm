<?php

namespace App\Repositories;

use App\Models\Admin;
use App\Models\Admin_two_factor_code;
use App\Repositories\Interfaces\AdminTwoFactorCodeRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * 管理者の二段階認証のコードのリポジトリ
 */
class AdminTwoFactorCodeRepository implements AdminTwoFactorCodeRepositoryInterface
{
    #[Override]
    public function findByAdmin(Admin $admin): ?Admin_two_factor_code
    {
        return Admin_two_factor_code::query()->where('admin_id', $admin->id)->first();
    }

    #[Override]
    public function replace(Admin $admin, string $hashedCode, CarbonInterface $expiresAt, CarbonInterface $sentAt): void
    {
        Admin_two_factor_code::query()->upsert(
            [['admin_id' => $admin->id, 'code' => $hashedCode, 'attempts' => 0, 'expires_at' => $expiresAt, 'sent_at' => $sentAt]],
            ['admin_id'],
            ['code', 'attempts', 'expires_at', 'sent_at'],
        );
    }

    #[Override]
    public function replaceIfSentBefore(Admin $admin, CarbonInterface $threshold, string $hashedCode, CarbonInterface $expiresAt, CarbonInterface $sentAt): bool
    {
        return Admin_two_factor_code::query()
            ->where('admin_id', $admin->id)
            ->where('sent_at', '<=', $threshold)
            ->update(['code' => $hashedCode, 'expires_at' => $expiresAt, 'sent_at' => $sentAt]) > 0;
    }

    #[Override]
    public function incrementAttempts(Admin $admin, int $max, CarbonInterface $now): ?int
    {
        return DB::transaction(function () use ($admin, $max, $now): ?int {
            $updated = Admin_two_factor_code::query()
                ->where('admin_id', $admin->id)
                ->where('attempts', '<', $max)
                ->where('expires_at', '>', $now)
                ->increment('attempts');

            if ($updated === 0) {
                return null;
            }

            // 自分の UPDATE の後の、DB の実際の値を読む
            $attempts = Admin_two_factor_code::query()->where('admin_id', $admin->id)->value('attempts');

            return is_numeric($attempts) ? (int) $attempts : null;
        });
    }

    #[Override]
    public function consumeIfMatches(Admin $admin, string $hashedCode, CarbonInterface $now): bool
    {
        return Admin_two_factor_code::query()
            ->where('admin_id', $admin->id)
            ->where('code', $hashedCode)
            ->where('expires_at', '>', $now)
            ->delete() > 0;
    }

    #[Override]
    public function deleteByAdmin(Admin $admin): void
    {
        Admin_two_factor_code::query()->where('admin_id', $admin->id)->delete();
    }
}
