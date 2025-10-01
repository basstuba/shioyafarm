<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NoticeRequest extends FormRequest
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
            'title' => 'required|string',
            'text' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'お知らせタイトルを入力してください',
            'title.string' => 'お知らせタイトルは文字で入力してください',
            'text.required' => 'お知らせ内容を入力してください',
            'text.string' => 'お知らせ内容は文章で入力してください',
        ];
    }
}
