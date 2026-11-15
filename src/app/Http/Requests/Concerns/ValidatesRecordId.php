<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 管理画面の削除・修正の入力で、対象のID（id）の形を検証する共通の処理
 *
 * ID の形が正しくないときは、存在しない対象と同じ扱いにする（何も変えずに一覧などへ戻し、「〜が見つかりません。」を出す）。
 * 存在するかどうかは、ここでは確かめず、削除・更新の処理で判定する。
 */
trait ValidatesRecordId
{
    /**
     * ID の規則（必須・1以上の整数）
     *
     * @return list<string>
     */
    protected function idRules(): array
    {
        return ['required', 'integer', 'min:1'];
    }

    /**
     * ID の規則に外れたときの文言（どの規則でも同じ「見つかりません」の案内にする）
     *
     * @return array<string, string>
     */
    protected function idMessages(string $notFoundMessage): array
    {
        return [
            'id.required' => $notFoundMessage,
            'id.integer' => $notFoundMessage,
            'id.min' => $notFoundMessage,
        ];
    }

    /**
     * ID のエラーがあるときは、入力ページに戻さず、一覧へ戻して「見つかりません」の案内を errors の $errorKey に入れる
     *
     * ID のエラーがなければ何もしない（呼び出し側が、ほかの項目の入力エラーを今までどおり処理する）。
     */
    protected function redirectToIndexIfIdInvalid(Validator $validator, string $indexRoute, string $errorKey, string $notFoundMessage): void
    {
        if ($validator->errors()->has('id')) {
            throw new HttpResponseException(
                redirect()->route($indexRoute)->withErrors([$errorKey => $notFoundMessage])
            );
        }
    }
}
