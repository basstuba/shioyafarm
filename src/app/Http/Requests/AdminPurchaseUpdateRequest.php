<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * 管理画面の注文の発送済みチェックの更新（Alpine から fetch で送る JSON のリクエスト）
 *
 * 入力の誤りは、Accept: application/json を付けた fetch への標準の応答（JSON の422）で返る。
 */
class AdminPurchaseUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:purchase_histories,id'],
            'shipped' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'id.required' => '注文を指定してください。',
            'id.integer' => '注文が正しくありません。',
            'id.exists' => '注文が見つかりません。',
            'shipped.required' => '発送済みかどうかを指定してください。',
            'shipped.boolean' => '発送済みかどうかが正しくありません。',
        ];
    }
}
