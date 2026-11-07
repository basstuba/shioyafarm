<?php

namespace App\Http\Requests;

use App\Enums\AdminListType;
use App\Support\SearchName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * 管理画面の一覧（注文・予約）の検索条件（発送状況・日付・名前）
 *
 * 入力の誤りは各一覧（全件の表示）に戻して、検索欄の近くにエラーを出す。戻り先とエラーメッセージの「注文」「予約」は、AdminListType（listType）から決まる。
 */
abstract class AdminListSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * どの一覧の検索か（エラーの戻り先とメッセージの「注文」「予約」は、ここから決まる）
     */
    abstract protected function listType(): AdminListType;

    #[Override]
    protected function getRedirectUrl(): string
    {
        return route($this->listType()->listRoute());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 「0」「1」「canceled」だけ（in:0,1 は緩い比較で「1.0」「01」も通ってしまい、サービスの判定とずれる）
            'shipped' => ['nullable', 'regex:/\A(0|1|canceled)\z/'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'name' => SearchName::RULES,
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
            'shipped.regex' => '発送状況は「未発送」「発送済」「取消済」のどれかを選んでください。',
            'date.date_format' => "{$subject}日は日付で入力してください。",
            'name.string' => "{$subject}者名は文字で入力してください。",
            'name.regex' => "{$subject}者名は文字で入力してください。",
            'name.max' => "{$subject}者名は100文字以内で入力してください。",
        ];
    }

    /**
     * 検索条件を、検索欄に戻す値の形（未入力は空文字）で返す
     *
     * @return array{shipped: string, date: string, name: string}
     */
    public function conditions(): array
    {
        $validated = $this->validated();

        return [
            'shipped' => (string) ($validated['shipped'] ?? ''),
            'date' => (string) ($validated['date'] ?? ''),
            'name' => (string) ($validated['name'] ?? ''),
        ];
    }
}
