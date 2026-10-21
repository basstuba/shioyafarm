<?php

namespace App\Http\Requests;

use App\Services\CartService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CartRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'item_id' => 'required|exists:items,id',
            'quantity' => self::quantityRules(),
        ];
    }

    /**
     * バリデーションエラーメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::quantityMessages();
    }

    /**
     * 商品数量のルール（カートページの数量変更でも使う）
     */
    public static function quantityRules(): string
    {
        return 'required|integer|min:'.CartService::MIN_QUANTITY.'|max:'.CartService::MAX_QUANTITY;
    }

    /**
     * 商品数量のエラーメッセージ（キーは `quantity.ルール名`）
     *
     * @return array<string, string>
     */
    public static function quantityMessages(): array
    {
        return [
            'quantity.required' => '商品の数を入力してください',
            'quantity.integer' => '商品の数は整数で入力してください',
            'quantity.min' => '商品の数は'.CartService::MIN_QUANTITY.'個以上にしてください',
            'quantity.max' => '商品の数は'.CartService::MAX_QUANTITY.'個以下にしてください',
        ];
    }
}
