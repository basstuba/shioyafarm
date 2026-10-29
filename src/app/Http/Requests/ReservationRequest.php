<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
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
            ...PurchaseRequest::checkoutRules(PaymentMethod::reservationValues()),
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
            ...PurchaseRequest::checkoutMessages(),
        ];
    }
}
