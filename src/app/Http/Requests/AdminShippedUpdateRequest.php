<?php

namespace App\Http\Requests;

use App\Enums\AdminListType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * 管理画面の発送済みチェックの更新（Alpine から fetch で送る JSON のリクエスト。注文・予約で共通）
 *
 * 入力の誤りは、Accept: application/json を付けた fetch への標準の応答（JSON の422）で返る。
 */
abstract class AdminShippedUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * どの一覧の更新か（IDの存在を確かめるテーブルと、エラーメッセージの「注文」「予約」は、ここから決まる）
     */
    abstract protected function listType(): AdminListType;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:'.$this->listType()->table().',id'],
            'shipped' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        $subject = $this->listType()->subject();

        return [
            'id.required' => "{$subject}を指定してください。",
            'id.integer' => "{$subject}が正しくありません。",
            'id.exists' => "{$subject}が見つかりません。",
            'shipped.required' => '発送済みかどうかを指定してください。',
            'shipped.boolean' => '発送済みかどうかが正しくありません。',
        ];
    }
}
