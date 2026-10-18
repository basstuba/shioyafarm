<?php

namespace App\Http\Requests;

use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

/**
 * ログインのリクエスト（FortifyのLoginRequestを差し替えて使う）
 */
class LoginRequest extends FortifyLoginRequest
{
    /**
     * バリデーションルール
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * エラーメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'メールアドレスを入力してください',
            'email.string' => 'メールアドレスは文字で入力してください',
            'email.email' => 'メールアドレスは有効なメールアドレス形式で入力してください',
            'password.required' => 'パスワードを入力してください',
            'password.string' => 'パスワードは文字で入力してください',
        ];
    }
}
