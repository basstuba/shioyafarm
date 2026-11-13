<?php

namespace App\Http\Requests;

use App\Models\Material;
use App\Support\FormText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Override;

/**
 * 管理画面の資材の追加の入力（修正の入力 MaterialUpdateRequest の元。設計書にない追加）
 *
 * 前後の空白（全角を含む）は取り除き、在庫数・基準値の全角の数字は半角にしてから検証する。
 * 入力の誤りは、モーダルを開いたままの一覧（資材一覧）に戻し、入力を残す。
 */
class MaterialRequest extends FormRequest
{
    /**
     * 管理者のログインはルートの認証（auth:admin）で確認するため、ここでは常に許可する
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 入力の誤りの戻り先は資材一覧（モーダルは、戻ったあとの一覧が入力の残りから開く）
     */
    #[Override]
    protected function getRedirectUrl(): string
    {
        return route('admin.materials.index');
    }

    /**
     * 前後の空白を取り除き、在庫数・基準値の全角の数字を半角にする
     */
    #[Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => FormText::trim($this->input('name')),
            'location' => FormText::trim($this->input('location')),
            'stock' => FormText::number($this->input('stock')),
            'threshold' => FormText::number($this->input('threshold')),
        ]);
    }

    /**
     * 資材名の重複の確認（追加は全件が対象。修正は自分自身を除く）
     */
    protected function nameUniqueRule(): Unique
    {
        return Rule::unique('materials', 'name');
    }

    /**
     * バリデーションルール
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $number = ['required', 'integer', 'min:0', 'max:'.Material::NUMBER_MAX];

        return [
            'name' => ['required', 'string', 'max:'.Material::NAME_MAX, $this->nameUniqueRule()],
            'stock' => $number,
            'location' => ['required', 'string', 'max:'.Material::LOCATION_MAX],
            'threshold' => $number,
        ];
    }

    /**
     * バリデーションエラーメッセージ
     *
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        $messages = [
            'name.required' => '資材名を入力してください',
            'name.string' => '資材名は文字で入力してください',
            'name.max' => '資材名は'.Material::NAME_MAX.'文字以内で入力してください',
            'name.unique' => 'その資材名はすでに登録されています',
            'location.required' => '置き場所を入力してください',
            'location.string' => '置き場所は文字で入力してください',
            'location.max' => '置き場所は'.Material::LOCATION_MAX.'文字以内で入力してください',
        ];

        foreach (['stock' => '在庫数', 'threshold' => '基準値'] as $field => $label) {
            $messages["{$field}.required"] = "{$label}を入力してください";
            $messages["{$field}.integer"] = "{$label}は0以上の整数で入力してください";
            $messages["{$field}.min"] = "{$label}は0以上の整数で入力してください";
            $messages["{$field}.max"] = "{$label}は".Material::NUMBER_MAX.'以下で入力してください';
        }

        return $messages;
    }

    /**
     * 保存する項目（検証済みの値）
     *
     * @return array{name: string, stock: int, location: string, threshold: int}
     */
    public function toInput(): array
    {
        $validated = $this->validated();

        return [
            'name' => (string) $validated['name'],
            'stock' => (int) $validated['stock'],
            'location' => (string) $validated['location'],
            'threshold' => (int) $validated['threshold'],
        ];
    }
}
