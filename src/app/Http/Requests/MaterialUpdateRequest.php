<?php

namespace App\Http\Requests;

use App\Models\Material;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Override;

/**
 * 管理画面の資材の修正の入力（追加と同じ項目と、資材のID。設計書にない追加）
 *
 * 資材のIDの形が正しくないとき・存在しない（削除済みを含む）ときは、ほかの項目の検証（資材名の重複など）より先に、何も変えずに一覧へ戻し、「資材が見つかりません。」を出す。
 * 検証のあとに削除された場合は、更新の処理（MaterialRepository::update）で判定する。資材名の重複は、自分自身を除いて確認する。
 */
class MaterialUpdateRequest extends MaterialRequest
{
    /**
     * 自分自身の資材名は重複とみなさない
     */
    #[Override]
    protected function nameUniqueRule(): Unique
    {
        return Rule::unique('materials', 'name')->ignore($this->integer('id'));
    }

    /**
     * 追加のルールに、資材のID（必須・1以上の整数・存在する資材）を加える
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    #[Override]
    public function rules(): array
    {
        return ['id' => ['bail', 'required', 'integer', 'min:1', Rule::exists('materials', 'id')], ...parent::rules()];
    }

    /**
     * 資材のIDが正しくない・存在しないときは、ほかの項目のエラーに関わらず、モーダルを開かずに一覧へ戻して案内する
     */
    #[Override]
    protected function failedValidation(Validator $validator): void
    {
        if ($validator->errors()->has('id')) {
            throw new HttpResponseException(
                redirect()->route('admin.materials.index')->withErrors(['material' => Material::NOT_FOUND_MESSAGE])
            );
        }

        parent::failedValidation($validator);
    }
}
