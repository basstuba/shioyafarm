<?php

namespace App\Http\Requests;

use App\Enums\ExportType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Override;

/**
 * 管理画面のエクスポート（出力の種類と、一覧と同じ検索条件）
 */
class ExportRequest extends PurchaseSearchRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    #[Override]
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ExportType::class)],
            ...parent::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'type.required' => 'エクスポートの種類を指定してください。',
            'type.enum' => 'エクスポートの種類が正しくありません。',
            ...parent::messages(),
        ];
    }

    /**
     * エクスポートの種類
     */
    public function type(): ExportType
    {
        return ExportType::from($this->validated()['type']);
    }
}
