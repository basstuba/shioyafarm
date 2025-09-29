<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InquiryRequest extends FormRequest
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
            'email' => 'required|email',
            'detail' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'last_name.required' => '姓を入力してください',
            'last_name.string' => '姓は文字で入力してください',
            'first_name.required' => '名を入力してください',
            'first_name.string' => '名は文字で入力してください',
            'email.required' => 'メールアドレスを入力してください',
            'email.email' => 'メールアドレスは有効なメールアドレス形式で入力してください',
            'detail.required' => 'お問い合わせ内容を入力してください',
            'detail.string' => 'お問い合わせ内容は文章で入力してください',
        ];
    }
}
