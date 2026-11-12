<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;

/**
 * 「パスワードを通ったが、確認コードの入力がまだの管理者」をセッションに出し入れする
 *
 * 管理者のIDと、そのときのパスワードの派生値（入力待ちの途中でパスワードが変わったことに気づくため）を保存する。
 * この状態では、管理者としてはログインしていない（admin ガードは認証されない）。
 */
class AdminTwoFactorStore
{
    /** セッションのキー */
    public const string SESSION_KEY = 'admin_two_factor_pending';

    public function __construct(
        private readonly Session $session,
    ) {}

    /**
     * 確認コードの入力待ちの管理者を取得する（無い・形式が正しくないときは null）
     *
     * @return array{id: int, fingerprint: string}|null
     */
    public function get(): ?array
    {
        $stored = $this->session->get(self::SESSION_KEY);

        if (! is_array($stored) || ! is_int($stored['id'] ?? null) || ! is_string($stored['fingerprint'] ?? null)) {
            return null;
        }

        return ['id' => $stored['id'], 'fingerprint' => $stored['fingerprint']];
    }

    /**
     * 確認コードの入力待ちの管理者を保存する（前の管理者は上書きする）
     */
    public function put(int $adminId, string $fingerprint): void
    {
        $this->session->put(self::SESSION_KEY, ['id' => $adminId, 'fingerprint' => $fingerprint]);
    }

    /**
     * 確認コードの入力待ちの状態を消す
     */
    public function forget(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
