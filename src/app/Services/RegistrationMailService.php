<?php

namespace App\Services;

use App\Mail\RegistrationMail;
use App\Models\User;
use App\Support\DeferredMail;

/**
 * 会員登録完了メールの送信
 */
class RegistrationMailService
{
    /**
     * 登録した会員のメールアドレスへ、会員登録完了メールを送る（送信はレスポンスを返した後）
     *
     * 送信の登録と、失敗のログは DeferredMail が行う（送信に失敗しても例外は外に出さない。会員は登録済み）。
     */
    public function send(User $user): void
    {
        DeferredMail::send(
            fn () => new RegistrationMail($user),
            '会員登録完了メールの送信に失敗しました',
            ['user_id' => $user->getKey()],
        );
    }
}
