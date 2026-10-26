<?php

namespace App\Http\Requests;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 会員の登録情報変更（マイページ）
 *
 * ルールとエラーメッセージは会員登録（CreateNewUser）と同じ。違いは次の2点。
 * - メールアドレスの重複は自分を除いて調べる。退会済み（論理削除）の会員のメールアドレスも重複として扱う
 * - パスワードは入力されたときだけ検証する（空のときは変更しない）
 *
 * メールアドレスかパスワードを変えるときは、現在のパスワードの入力が必要（姓名・住所・電話番号だけの変更では不要）。
 */
class UserRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * 配列などの文字列でない入力でも500にならないよう、各項目は先に string で検証する。
     * users テーブル全体を調べるため、退会済みの会員のメールアドレスも重複になる。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...CreateNewUser::profileRules(Rule::unique('users', 'email')->ignore($this->user()?->id)),
            'password' => $this->passwordRules(required: false),
            // メールアドレスかパスワードを変えるときだけ検証する（変えないときは検証せず、validated() にも含まれない）
            ...($this->requiresCurrentPassword() ? ['current_password' => ['bail', 'required', 'string', 'current_password']] : []),
        ];
    }

    /**
     * 現在のパスワードの入力が必要か（メールアドレスかパスワードを変えるとき）
     *
     * メールアドレスは、大文字小文字の違いだけなら変更とみなさない（重複の判定が大文字小文字を区別しないのに合わせる）。
     */
    private function requiresCurrentPassword(): bool
    {
        $email = $this->input('email');
        $password = $this->input('password');

        $emailChanged = is_string($email) && strcasecmp($email, (string) $this->user()?->email) !== 0;
        $passwordChanged = $password !== null && $password !== '';

        return $emailChanged || $passwordChanged;
    }

    /**
     * バリデーションエラーメッセージ（会員登録と同じ文言。現在のパスワードのものを足す）
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CreateNewUser::validationMessages() + [
            'current_password.required' => 'メールアドレス・パスワードを変更する場合は、現在のパスワードを入力してください',
            'current_password.string' => '現在のパスワードは文字で入力してください',
            'current_password.current_password' => '現在のパスワードが正しくありません',
        ];
    }
}
