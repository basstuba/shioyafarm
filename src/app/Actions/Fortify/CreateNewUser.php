<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * 会員登録（Fortifyの新規ユーザー作成処理）
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * 入力を検証して会員を登録する
     *
     * 配列などの文字列でない入力でも500にならないよう、各項目は先に string で検証する。
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...self::profileRules(Rule::unique(User::class)),
            'password' => $this->passwordRules(),
        ], self::validationMessages())->validate();

        return User::create([
            'last_name' => $input['last_name'],
            'first_name' => $input['first_name'],
            'prefecture' => $input['prefecture'],
            'city' => $input['city'],
            'tell' => $input['tell'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }

    /**
     * 会員の登録情報（氏名・住所・電話番号・メールアドレス）のルール
     *
     * 会員登録と、マイページの登録情報変更（UserRequest）で共通。違いはメールアドレスの重複の判定だけ
     * （登録情報の変更では、自分を除く）なので、その規則を引数で受け取る。
     * 配列などの文字列でない入力でも500にならないよう、各項目は先に string で検証する。
     *
     * @return array<string, list<mixed>>
     */
    public static function profileRules(Unique $emailUnique): array
    {
        return [
            'last_name' => ['bail', 'required', 'string', 'max:255'],
            'first_name' => ['bail', 'required', 'string', 'max:255'],
            'prefecture' => ['bail', 'required', 'string', Rule::exists('areas', 'prefecture')],
            'city' => ['bail', 'required', 'string', 'max:255'],
            'tell' => ['bail', 'required', 'string', 'regex:/\A[0-9]{10,11}\z/'],
            'email' => ['bail', 'required', 'string', 'email', 'max:30', $emailUnique],
        ];
    }

    /**
     * エラーメッセージ（登録情報の変更 UserRequest でも同じ文言を使う）
     *
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'last_name.required' => '姓を入力してください',
            'last_name.string' => '姓は文字で入力してください',
            'last_name.max' => '姓は255文字以内で入力してください',
            'first_name.required' => '名を入力してください',
            'first_name.string' => '名は文字で入力してください',
            'first_name.max' => '名は255文字以内で入力してください',
            'prefecture.required' => 'お住まいの地域を選択してください',
            'prefecture.string' => 'お住まいの地域は文字で入力してください',
            'prefecture.exists' => 'お住まいの地域を一覧から選択してください',
            'city.required' => 'ご住所を入力してください',
            'city.string' => 'ご住所は文字で入力してください',
            'city.max' => 'ご住所は255文字以内で入力してください',
            'tell.required' => '電話番号を入力してください',
            'tell.string' => '電話番号は文字で入力してください',
            'tell.regex' => '電話番号はハイフンなしの半角数字10〜11桁で入力してください',
            'email.required' => 'メールアドレスを入力してください',
            'email.string' => 'メールアドレスは文字で入力してください',
            'email.email' => 'メールアドレスは有効なメールアドレス形式で入力してください',
            'email.max' => 'メールアドレスは30文字以内で入力してください',
            'email.unique' => 'このメールアドレスはすでに登録されています',
            'password.required' => 'パスワードを入力してください',
            'password.string' => 'パスワードは文字で入力してください',
            'password.min' => 'パスワードは8文字以上で入力してください',
            'password.confirmed' => 'パスワードと確認用パスワードが一致しません',
        ];
    }
}
