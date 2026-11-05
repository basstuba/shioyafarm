<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * 管理画面の注文一覧の検索条件（発送状況・注文日・注文者名）
 *
 * 入力の誤りは注文一覧（全件の表示）に戻して、検索欄の近くにエラーを出す。
 */
class PurchaseSearchRequest extends FormRequest
{
    /** @var string 入力の誤りのときの戻り先のルート名 */
    protected $redirectRoute = 'admin.purchase.index';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 「0」「1」だけ（in:0,1 は緩い比較で「1.0」「01」も通ってしまい、サービスの判定とずれる）
            'shipped' => ['nullable', 'regex:/\A[01]\z/'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'shipped.regex' => '発送状況は「未発送」か「発送済」を選んでください。',
            'date.date_format' => '注文日は日付で入力してください。',
            'name.string' => '注文者名は文字で入力してください。',
            'name.max' => '注文者名は100文字以内で入力してください。',
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
