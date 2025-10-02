<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'last_name' => 'required|string',
            'first_name' => 'required|string',
            'prefecture' => 'required',
            'city' => 'required|string',
            'tell' => 'required|digits_between:10,11',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'password' => [
                'nullable',
                'string',
                Password::defaults(),
                'confirmed',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'last_name.required' => '姓を入力してください',
            'last_name.string' => '姓は文字で入力してください',
            'first_name.required' => '名を入力してください',
            'first_name.string' => '名は文字で入力してください',
            'prefecture.required' => 'お住まいの地域を選択してください',
            'city.required' => 'ご住所を入力してください',
            'city.string' => 'ご住所は文字で入力してください',
            'tell.required' => '電話番号を入力してください',
            'tell.digits_between' => '電話番号はハイフンなしの数字のみで10〜11桁で入力してください',
            'email.required' => 'メールアドレスを入力してください',
            'email.email' => 'メールアドレスは有効なメールアドレス形式で入力してください',
            'email.unique' => 'このメールアドレスは既に使用されています',
            'password.confirmed' => '確認用パスワードが一致しません',
        ];
    }
}
