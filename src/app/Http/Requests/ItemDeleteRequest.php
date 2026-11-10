<?php

namespace App\Http\Requests;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * 管理画面の掲載商品の削除の入力（商品のIDの形だけを検証する）
 *
 * 存在するか・削除済みかは、削除の処理（ItemRepository::softDelete）で判定する。
 * 入力の誤りは、削除済み・存在しない商品と同じ扱い（何も変えずに一覧へ戻し、「商品が見つかりません。」を出す）。
 */
class ItemDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return route('admin.items.index');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        $message = Item::NOT_FOUND_MESSAGE;

        return [
            'id.required' => $message,
            'id.integer' => $message,
            'id.min' => $message,
        ];
    }
}
