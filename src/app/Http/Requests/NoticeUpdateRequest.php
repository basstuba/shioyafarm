<?php

namespace App\Http\Requests;

use App\Models\Notice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Override;

/**
 * 管理画面のお知らせの修正の入力（作成と同じ項目と、お知らせのID）
 *
 * お知らせのIDの形が正しくないときは、削除済み・存在しないお知らせと同じ扱い（何も変えずに一覧へ戻し、「お知らせが見つかりません。」を出す）。
 * 存在するかは、更新の処理（NoticeRepository::update）で判定する。
 */
class NoticeUpdateRequest extends NoticeRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    #[Override]
    public function rules(): array
    {
        return ['id' => ['required', 'integer', 'min:1'], ...parent::rules()];
    }

    /**
     * お知らせのIDが正しくないときは、修正ページではなく一覧へ戻して案内する
     */
    #[Override]
    protected function failedValidation(Validator $validator): void
    {
        if ($validator->errors()->has('id')) {
            throw new HttpResponseException(
                redirect()->route('admin.notices.index')->withErrors(['notice' => Notice::NOT_FOUND_MESSAGE])
            );
        }

        parent::failedValidation($validator);
    }
}
