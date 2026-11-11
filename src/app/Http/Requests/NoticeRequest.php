<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * 管理画面のお知らせ作成の入力（修正の入力 NoticeUpdateRequest の元）
 *
 * お知らせ内容の2000文字以内は設計書にない追加の決まり。改行は、ブラウザが送る CRLF を LF にそろえてから数える（見た目の文字数と合わせる）。
 */
class NoticeRequest extends FormRequest
{
    /** お知らせ内容の文字数の上限 */
    public const int TEXT_MAX = 2000;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * お知らせ内容の改行（CRLF・CR）を LF にそろえる（ブラウザの textarea は改行を CRLF で送るため、そのままだと1回の改行が2文字に数えられる）
     */
    #[Override]
    protected function prepareForValidation(): void
    {
        $text = $this->input('text');
        if (is_string($text)) {
            $this->merge(['text' => str_replace(["\r\n", "\r"], "\n", $text)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'text' => 'required|string|max:'.self::TEXT_MAX,
        ];
    }

    /**
     * バリデーションエラーメッセージ
     *
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'title.required' => 'お知らせタイトルを入力してください',
            'title.string' => 'お知らせタイトルは文字で入力してください',
            'title.max' => 'お知らせタイトルは255文字以内で入力してください',
            'text.required' => 'お知らせ内容を入力してください',
            'text.string' => 'お知らせ内容は文章で入力してください',
            'text.max' => 'お知らせ内容は'.self::TEXT_MAX.'文字以内で入力してください',
        ];
    }

    /**
     * 保存する項目（検証済みの値）
     *
     * @return array{title: string, text: string}
     */
    public function toInput(): array
    {
        $validated = $this->validated();

        return ['title' => (string) $validated['title'], 'text' => (string) $validated['text']];
    }
}
