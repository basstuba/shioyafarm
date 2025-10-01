<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
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
            'item_id' => 'required',
            'quantity' => 'required|integer|min:1',
            'delivery_name' => 'required|string',
            'address_id' => 'required|string|regex:/^(user|delivery)_[0-9]+$/',
            'delivery_tell' => 'required|digits_between:10,11',
            'payment_method' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => '商品の数を入力してください',
            'quantity.min' => '商品の数は1個以上にしてください',
            'delivery_name.required' => 'お届け先氏名を入力してください',
            'delivery_name.string' => 'お届け先氏名は文字で入力してください',
            'address_id.required' => 'お届け先住所を選択してください',
            'delivery_tell.required' => 'お届け先電話番号を入力してください',
            'delivery_tell.digits_between' => 'お届け先電話番号はハイフンなしの数字のみで10〜11桁で入力してください',
            'payment_method.required' => 'お支払い方法を選択してください',
        ];
    }
}
