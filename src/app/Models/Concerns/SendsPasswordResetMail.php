<?php

namespace App\Models\Concerns;

use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * パスワード再設定のメールを同期送信し、失敗したらトークンを消す（会員 User と管理者 Admin で共通）
 *
 * 使うクラスは CanResetPassword を実装していること（Authenticatable を継承すれば満たす）。
 */
trait SendsPasswordResetMail
{
    /**
     * 再設定メールを送る
     *
     * 送信に失敗したときは、保存済みのトークンを削除してから例外を投げ直す。
     * 残したままだと、60秒以内の再送信が「送信済み」の案内になり、メールが届かないまま再送信もできなくなる。
     *
     * @param  string  $broker  トークンを保存したパスワードブローカーの名前（config/auth.php の passwords）
     *
     * @throws Throwable メールの送信に失敗したとき
     */
    protected function sendPasswordResetMail(string $broker, Mailable $mail): void
    {
        try {
            Mail::send($mail);
        } catch (Throwable $e) {
            /** @var PasswordBroker $passwordBroker deleteToken は契約（Contracts\Auth\PasswordBroker）にはなく、実装クラスにある */
            $passwordBroker = Password::broker($broker);
            $passwordBroker->deleteToken($this);

            throw $e;
        }
    }
}
