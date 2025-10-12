<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ItemRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'weight' => 'required|in:2,5,10',
            'price' => 'required|integer',
            'img_url' => 'required|max:255',
            'detail' => 'required|string',
            'recommend' => 'required|boolean',
            'is_reserved_only' => 'required|boolean',
            'reservation_limit' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '商品名を入力してください',
            'name.string' => '商品名は文字で入力してください',
            'name.max' => '商品名は255文字以内で入力してください',
            'weight.required' => '商品重量を選択してください',
            'price.required' => '商品価格を入力してください',
            'price.integer' => '商品価格は整数で入力してください',
            'img_url.required' => '商品画像を選択してください',
            'detail.required' => '商品詳細を入力してください',
            'detail.string' => '商品詳細は文章で入力してください',
            'recommend.required' => 'おすすめ商品なのかを選択してください',
            'is_reserved_only.required' => '予約限定なのかを選択してください',
            'reservation_limit.integer' => '予約可能数は整数で入力してください',
        ];
    }
}
