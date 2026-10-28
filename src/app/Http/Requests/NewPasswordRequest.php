<?php

namespace App\Http\Requests;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 新しいパスワードの設定のリクエスト
 *
 * ここで検証するのは、トークン・メールアドレス・パスワードが入力されていること（文字列であること）だけ。
 * パスワードの桁数・確認用との一致は、トークンの確認の後に ResetUserPassword で検証する。
 */
class NewPasswordRequest extends FormRequest
{
    /**
     * 誰でも送信できる（未ログインの会員向け。ログイン中は guest ミドルウェアで移動する）
     */
    public function authorize(): bool
    {
        return true;
    }

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
            'token' => ['bail', 'required', 'string'],
            'email' => ['bail', 'required', 'string', 'email'],
            'password' => ['bail', 'required', 'string'],
        ];
    }

    /**
     * エラーメッセージ（メールアドレス・パスワードは会員登録と同じ文言）
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CreateNewUser::validationMessages() + [
            'token.required' => 'このリンクは無効です',
            'token.string' => 'このリンクは無効です',
        ];
    }
}
