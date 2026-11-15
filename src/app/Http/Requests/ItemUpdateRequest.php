<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesRecordId;
use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Override;

/**
 * 管理画面の掲載商品の修正の入力（登録と同じ項目と、商品のID。商品画像は任意で、選ばなければ今の画像のまま）
 *
 * 商品のIDの形が正しくないときは、削除済み・存在しない商品と同じ扱い（何も変えずに一覧へ戻し、「商品が見つかりません。」を出す）。
 * 存在するか・削除済みかは、更新の処理（AdminItemService::update）で判定する。
 */
class ItemUpdateRequest extends ItemStoreRequest
{
    use ValidatesRecordId;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    #[Override]
    public function rules(): array
    {
        return ['id' => $this->idRules(), ...parent::rules()];
    }

    /**
     * 商品画像の規則（修正では任意）
     *
     * @return list<string>
     */
    #[Override]
    protected function imageRules(): array
    {
        return ['nullable', ...$this->imageFormatRules()];
    }

    /**
     * 商品のIDが正しくないときは、修正ページではなく一覧へ戻して案内する
     */
    #[Override]
    protected function failedValidation(Validator $validator): void
    {
        $this->redirectToIndexIfIdInvalid($validator, 'admin.items.index', 'item', Item::NOT_FOUND_MESSAGE);

        parent::failedValidation($validator);
    }
}
