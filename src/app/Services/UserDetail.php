<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * 管理画面の会員一覧の詳細（モーダルに表示する内容）
 *
 * 画面に出す項目だけを持つ（パスワードなどの認証情報は含めない）。
 */
final readonly class UserDetail
{
    /**
     * @param  CarbonInterface|null  $withdrawnAt  退会日時（退会していない会員は null）
     */
    public function __construct(
        public int $id,
        public string $lastName,
        public string $firstName,
        public string $address,
        public string $tell,
        public string $email,
        public ?CarbonInterface $withdrawnAt,
    ) {}

    /**
     * 会員から作る
     */
    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            lastName: $user->last_name,
            firstName: $user->first_name,
            address: $user->prefecture.$user->city,
            tell: $user->tell,
            email: $user->email,
            withdrawnAt: $user->deleted_at,
        );
    }
}
