<?php

namespace App\Http\Requests;

use App\Services\AdminUserCriteria;
use App\Support\SearchName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

/**
 * 管理画面の会員一覧の検索条件（状態・氏名）
 *
 * 入力の誤りは会員一覧（全件の表示）に戻して、検索欄の近くにエラーを出す。
 */
class UserSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return route('admin.users.index');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 文字列の 'member'・'withdrawn' だけ（配列などは弾く）
            'status' => ['nullable', 'string', Rule::in([AdminUserCriteria::STATUS_MEMBER, AdminUserCriteria::STATUS_WITHDRAWN])],
            // 注文一覧・予約一覧の名前の欄と同じルール
            'name' => SearchName::RULES,
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'status.string' => '状態は「会員」か「退会済」を選んでください。',
            'status.in' => '状態は「会員」か「退会済」を選んでください。',
            'name.string' => '氏名は文字で入力してください。',
            'name.regex' => '氏名は文字で入力してください。',
            'name.max' => '氏名は100文字以内で入力してください。',
        ];
    }

    /**
     * 検索条件を、検索欄に戻す値の形（未入力は空文字）で返す
     *
     * @return array{status: string, name: string}
     */
    public function conditions(): array
    {
        $validated = $this->validated();

        return [
            'status' => (string) ($validated['status'] ?? ''),
            'name' => (string) ($validated['name'] ?? ''),
        ];
    }
}
