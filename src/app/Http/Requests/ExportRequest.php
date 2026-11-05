<?php

namespace App\Http\Requests;

use App\Enums\AdminListType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Override;

/**
 * 管理画面のエクスポート（出力の種類と、一覧と同じ検索条件）
 *
 * 入力の誤りは、種類に応じた一覧（注文一覧・予約一覧）に戻し、メッセージの「注文」「予約」も切り替える。
 * 種類が不正（または未指定）のときは、注文一覧に戻す。
 */
class ExportRequest extends AdminListSearchRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    #[Override]
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AdminListType::class)],
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
    public function type(): AdminListType
    {
        return AdminListType::from($this->validated()['type']);
    }

    /**
     * 送られてきた種類（検証の前に使うため、不正なときは注文として扱う）
     */
    #[Override]
    protected function listType(): AdminListType
    {
        $type = $this->input('type');

        return (is_string($type) ? AdminListType::tryFrom($type) : null) ?? AdminListType::Purchase;
    }
}
