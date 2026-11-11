<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * 管理画面のお知らせ一覧の検索条件（作成日）
 *
 * 入力の誤りはお知らせ一覧（全件の表示）に戻して、検索欄の近くにエラーを出す。
 */
class NoticeSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return route('admin.notices.index');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'date.date_format' => '作成日は日付で入力してください。',
        ];
    }

    /**
     * 検索条件を、検索欄に戻す値の形（未入力は空文字）で返す
     *
     * @return array{date: string}
     */
    public function conditions(): array
    {
        $validated = $this->validated();

        return ['date' => (string) ($validated['date'] ?? '')];
    }
}
