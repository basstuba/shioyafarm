<?php

namespace App\Services;

use App\Mail\RegistrationMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * 会員登録完了メールの送信
 */
class RegistrationMailService
{
    /**
     * 登録した会員のメールアドレスへ、会員登録完了メールを送る（送信はレスポンスを返した後）
     *
     * ここでは送信を defer に登録するだけで、実際に送るのはリクエストの終了時なので、登録後の画面遷移はメールサーバーの応答を待たない。
     * キューは使わず、同じプロセスの中で送る。送信に失敗しても例外は外に出さない（会員は登録済み）。失敗はログに残す。
     */
    public function send(User $user): void
    {
        defer(function () use ($user): void {
            try {
                Mail::send(new RegistrationMail($user));
            } catch (Throwable $e) {
                Log::error('会員登録完了メールの送信に失敗しました', [
                    'user_id' => $user->getKey(),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }, always: true);
    }
}
