<?php

namespace App\Http\Requests;

use App\Services\AdminInquiryCriteria;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * 管理画面のお問い合わせ一覧の検索条件（対応状況・問い合わせ日）
 *
 * 入力の誤りはお問い合わせ一覧（全件の表示）に戻して、検索欄の近くにエラーを出す。
 */
class InquirySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return route('admin.inquiries.index');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 決められた値（非対応「0」・対応済「1」）だけ（in:0,1 は緩い比較で「1.0」「01」も通ってしまい、検索条件の判定とずれる）
            'addressed' => ['nullable', 'regex:/\A('.preg_quote(AdminInquiryCriteria::ADDRESSED_NO, '/').'|'.preg_quote(AdminInquiryCriteria::ADDRESSED_YES, '/').')\z/'],
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
            'addressed.regex' => '対応状況は「対応済」「非対応」のどちらかを選んでください。',
            'date.date_format' => '問い合わせ日は日付で入力してください。',
        ];
    }

    /**
     * 検索条件を、検索欄に戻す値の形（未入力は空文字）で返す
     *
     * @return array{addressed: string, date: string}
     */
    public function conditions(): array
    {
        $validated = $this->validated();

        return [
            'addressed' => (string) ($validated['addressed'] ?? ''),
            'date' => (string) ($validated['date'] ?? ''),
        ];
    }
}
