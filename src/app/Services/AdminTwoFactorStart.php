<?php

namespace App\Services;

use App\Enums\AdminTwoFactorStartResult;

/**
 * 管理者のログイン（確認コードの送信まで）の結果と、ログインできない状態のときの残り時間
 */
final readonly class AdminTwoFactorStart
{
    public function __construct(
        public AdminTwoFactorStartResult $result,
        /** あと何分で試せるか（結果が Suspended のときだけ） */
        public ?int $suspendedMinutes = null,
    ) {}
}
