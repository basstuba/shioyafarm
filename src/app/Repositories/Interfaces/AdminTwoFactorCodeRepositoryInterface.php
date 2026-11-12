<?php

namespace App\Repositories\Interfaces;

use App\Models\Admin;
use App\Models\Admin_two_factor_code;
use Carbon\CarbonInterface;

/**
 * 管理者の二段階認証のコードのリポジトリインターフェース
 */
interface AdminTwoFactorCodeRepositoryInterface
{
    /**
     * 管理者のコードを取得（無ければ null）
     */
    public function findByAdmin(Admin $admin): ?Admin_two_factor_code;

    /**
     * 管理者のコードを新しいものに置き換える（ログイン時。前のコードは使えなくなり、失敗回数は0に戻る。無ければ作る）
     *
     * 1回の upsert（INSERT ... ON DUPLICATE KEY UPDATE）で行うので、同時に2回呼ばれても（ログインのダブルクリック）、重複エラーにならない。
     */
    public function replace(Admin $admin, string $hashedCode, CarbonInterface $expiresAt, CarbonInterface $sentAt): void;

    /**
     * 前回の送信が基準の時刻以前のときだけ、コードを新しいものに置き換える（再送。失敗回数は引き継ぐ）。置き換えたら true
     *
     * 判定と更新を1回の UPDATE（sent_at が条件）で行うので、同時に2回呼ばれても置き換えられるのは1回だけ。
     */
    public function replaceIfSentBefore(Admin $admin, CarbonInterface $threshold, string $hashedCode, CarbonInterface $expiresAt, CarbonInterface $sentAt): bool;

    /**
     * 失敗回数が上限に達しておらず、有効期限が切れていなければ、1増やして、増やした後の回数を返す（上限に達している・期限切れ・コードが無いときは null）
     *
     * 1回の UPDATE（attempts < 上限 かつ expires_at > 基準の時刻 が条件）で行うので、同時に何回呼ばれても、増やせるのは上限までになる。
     */
    public function incrementAttempts(Admin $admin, int $max, CarbonInterface $now): ?int;

    /**
     * 保存してあるコードのハッシュと一致し、有効期限が切れていなければ、コードを削除して true を返す（コードの照合・期限の確認・削除を1回の DELETE で行う）
     *
     * 一致しない・期限切れ・すでに削除（使用済み・再送で置き換え済み）のときは、何も消さず false。
     */
    public function consumeIfMatches(Admin $admin, string $hashedCode, CarbonInterface $now): bool;

    /**
     * 管理者のコードを削除する
     */
    public function deleteByAdmin(Admin $admin): void;
}
