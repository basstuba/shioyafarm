<?php

namespace App\Http\Requests;

use App\Actions\Fortify\CreateNewUser;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest as FortifySendPasswordResetLinkRequest;

/**
 * パスワード再設定メールの送信のリクエスト（FortifyのSendPasswordResetLinkRequestを差し替えて使う）
 */
class PasswordResetLinkRequest extends FortifySendPasswordResetLinkRequest
{
    /**
     * バリデーションルール
     *
     * 配列などの文字列でない入力でも500にならないよう、先に string で検証する。
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['bail', 'required', 'string', 'email'],
        ];
    }

    /**
     * エラーメッセージ（会員登録と同じ文言。メールアドレスの項目だけが使われる）
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CreateNewUser::validationMessages();
    }
}
