<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Services\CheckoutService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 予約ページの「予約を確定する」（POST /reservation/payment）のリクエスト
 *
 * 金額（単価・送料・合計）はフォームの値を受け取らず、サーバー側でDBと config/shipping.php から再計算する。
 * 支払方法はクレジットカードと銀行振込だけ（代金引換は選べない）。
 * 注文トークン（order_token）は、予約ページが発行した1回限りのトークンで、二重送信による予約の重複を防ぐ。
 * 予約可能数は、サーバー側（ReservationService）で確認する。
 */
class ReservationRequest extends FormRequest
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
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('is_reserved_only', 1)],
            'quantity' => CartRequest::quantityRules(),
            'delivery_name' => 'required|string|max:255',
            // 正規表現に | を含むため、パイプ区切りの文字列ではなく配列で書く
            'address_id' => ['required', 'string', 'regex:/^(user|delivery)_[0-9]+$/'],
            'delivery_tell' => 'required|digits_between:10,11|max:15',
            'payment_method' => ['required', 'string', 'max:25', Rule::in(PaymentMethod::reservationValues())],
            // 予約ページが発行した1回限りの注文トークン（使用済みかどうかは、排他ロックの中で確認する）
            'order_token' => 'required|string|max:100',
        ];
    }

    /**
     * バリデーションエラーメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...CartRequest::quantityMessages(),
            'item_id.required' => '予約する商品が正しくありません',
            'item_id.integer' => '予約する商品が正しくありません',
            'item_id.exists' => '予約できない商品です',
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
            'order_token.required' => CheckoutService::STALE_MESSAGE,
            'order_token.string' => CheckoutService::STALE_MESSAGE,
            'order_token.max' => CheckoutService::STALE_MESSAGE,
        ];
    }
}
