<?php

namespace App\Http\Requests;

use App\Models\Notice;
use App\Support\AdminListUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * 管理画面のお知らせの削除の入力（お知らせのIDの形だけを検証する）
 *
 * 存在するかは、削除の処理（NoticeRepository::delete）で判定する。
 * 入力の誤りは、存在しないお知らせと同じ扱い（何も変えずに削除の前に見ていた一覧（検索条件・ページ）へ戻し、「お知らせが見つかりません。」を出す）。
 */
class NoticeDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return AdminListUrl::previousOrIndex('admin.notices.index', 'admin.notices.search');
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
        $message = Notice::NOT_FOUND_MESSAGE;

        return [
            'id.required' => $message,
            'id.integer' => $message,
            'id.min' => $message,
        ];
    }
}
