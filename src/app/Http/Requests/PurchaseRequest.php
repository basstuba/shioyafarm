<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 購入ページの「注文を確定する」（POST /payment）のリクエスト
 *
 * 設計書の「商品ID・商品数量」は、カートの複数商品を購入するため `items.*.item_id` / `items.*.quantity` の配列にしている。
 * 金額（単価・送料・合計）はフォームの値を受け取らず、サーバー側でDBと config/shipping.php から再計算する。
 */
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', Rule::in(PurchaseSource::values())],
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('items', 'id')->where('is_reserved_only', 0)],
            'items.*.quantity' => CartRequest::quantityRules(),
            'delivery_name' => 'required|string|max:255',
            // 正規表現に | を含むため、パイプ区切りの文字列ではなく配列で書く
            'address_id' => ['required', 'string', 'regex:/^(user|delivery)_[0-9]+$/'],
            'delivery_tell' => 'required|digits_between:10,11|max:15',
            'payment_method' => ['required', 'string', 'max:25', Rule::in(PaymentMethod::values())],
        ];
    }

    /**
     * バリデーションエラーメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $quantityMessages = [];
        foreach (CartRequest::quantityMessages() as $key => $message) {
            $quantityMessages['items.*.'.$key] = $message;
        }

        return [
            ...$quantityMessages,
            'source.required' => '購入元が正しくありません',
            'source.in' => '購入元が正しくありません',
            'items.required' => '購入する商品がありません',
            'items.array' => '購入する商品が正しくありません',
            'items.min' => '購入する商品がありません',
            'items.*.item_id.required' => '購入する商品が正しくありません',
            'items.*.item_id.integer' => '購入する商品が正しくありません',
            'items.*.item_id.distinct' => '同じ商品が重複しています',
            'items.*.item_id.exists' => '購入できない商品が含まれています',
            'delivery_name.required' => 'お届け先氏名を入力してください',
            'delivery_name.string' => 'お届け先氏名は文字で入力してください',
            'delivery_name.max' => 'お届け先氏名は255文字以内で入力してください',
            'address_id.required' => 'お届け先住所を選択してください',
            'address_id.regex' => 'お届け先住所が正しくありません',
            'delivery_tell.required' => 'お届け先電話番号を入力してください',
            'delivery_tell.digits_between' => 'お届け先電話番号はハイフンなしの数字のみで10〜11桁で入力してください',
            'delivery_tell.max' => 'お届け先電話番号は15文字以内で入力してください',
            'payment_method.required' => 'お支払い方法を選択してください',
            'payment_method.in' => 'お支払い方法を選択してください',
        ];
    }
}
