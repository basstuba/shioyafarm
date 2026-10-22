<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeliveryAddressRequest extends FormRequest
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
        return self::addressRules();
    }

    /**
     * バリデーションエラーメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::addressMessages();
    }

    /**
     * 配送先住所のルール（購入ページの住所追加モーダル＝Livewireでも使う）
     *
     * @return array<string, string>
     */
    public static function addressRules(): array
    {
        return [
            'delivery_prefecture' => 'required|exists:areas,prefecture',
            'delivery_city' => 'required|string|max:255',
        ];
    }

    /**
     * 配送先住所のエラーメッセージ
     *
     * @return array<string, string>
     */
    public static function addressMessages(): array
    {
        return [
            'delivery_prefecture.required' => '都道府県を選択してください',
            'delivery_prefecture.exists' => '都道府県を選択してください',
            'delivery_city.required' => '住所を入力してください',
            'delivery_city.string' => '住所は正しい形式で入力してください',
            'delivery_city.max' => '住所は255文字以内で入力してください',
        ];
    }
}
