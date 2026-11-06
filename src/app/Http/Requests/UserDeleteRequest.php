<?php

namespace App\Http\Requests;

use App\Support\AdminUserListUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * 管理画面の会員の物理削除の入力（会員のIDの形だけを検証する）
 *
 * 退会済みか・存在するかは、行をロックして判定するリポジトリ（UserRepository::forceDeleteWithdrawn）だけで行う。
 * 入力の誤りは、削除の前に見ていた一覧に戻してエラーを出す。
 */
class UserDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return AdminUserListUrl::previousOrIndex();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'id.required' => '削除する会員を指定してください。',
            'id.integer' => '削除する会員が正しくありません。',
        ];
    }
}
