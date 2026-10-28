<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * パスワード再設定（Fortifyの忘れたパスワードの更新処理）
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * 入力を検証して、会員のパスワードを更新する
     *
     * パスワードのルールと日本語のメッセージは、会員登録（CreateNewUser）と同じ。
     *
     * @param  array<string, mixed>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ], CreateNewUser::validationMessages())->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
