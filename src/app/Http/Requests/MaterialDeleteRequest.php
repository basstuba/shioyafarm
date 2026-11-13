<?php

namespace App\Http\Requests;

use App\Models\Material;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Override;

/**
 * 管理画面の資材の削除の入力（資材のIDの形だけを検証する。設計書にない追加）
 *
 * 存在するかは、削除の処理（MaterialRepository::delete）で判定する。
 * IDの形が正しくないときは、存在しない資材と同じ扱い（何も変えずに一覧へ戻し、「資材が見つかりません。」を出す）。
 */
class MaterialDeleteRequest extends FormRequest
{
    /**
     * 管理者のログインはルートの認証（auth:admin）で確認するため、ここでは常に許可する
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルール（資材のIDの形だけ）
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * IDが正しくないときは、削除できなかった知らせ（errors の delete）として一覧へ戻す
     */
    #[Override]
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('admin.materials.index')->withErrors(['delete' => Material::NOT_FOUND_MESSAGE])
        );
    }
}
