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
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'prefecture' => 'required|exists:areas,prefecture',
            'city' => 'required|string|max:255',
            'tell' => 'required|digits_between:10,11|max:15',
            'email' => [
                'required',
                'email',
                'max:30',
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
            'last_name.max' => '姓は255文字以内で入力してください',
            'first_name.required' => '名を入力してください',
            'first_name.string' => '名は文字で入力してください',
            'first_name.max' => '名は255文字以内で入力してください',
            'prefecture.required' => 'お住まいの地域を選択してください',
            'city.required' => 'ご住所を入力してください',
            'city.string' => 'ご住所は文字で入力してください',
            'city.max' => 'ご住所は255文字以内で入力してください',
            'tell.required' => '電話番号を入力してください',
            'tell.digits_between' => '電話番号はハイフンなしの数字のみで10〜11桁で入力してください',
            'tell.max' => '電話番号は15文字以内で入力してください',
            'email.required' => 'メールアドレスを入力してください',
            'email.email' => 'メールアドレスは有効なメールアドレス形式で入力してください',
            'email.max' => 'メールアドレスは30文字以内で入力してください',
            'email.unique' => 'このメールアドレスは既に使用されています',
            'password.confirmed' => '確認用パスワードが一致しません',
        ];
    }
}
