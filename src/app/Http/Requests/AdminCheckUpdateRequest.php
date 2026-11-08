<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * 管理画面の一覧のチェック（注文・予約の発送済み、お問い合わせの対応済み）の即時保存の更新（Alpine から fetch で送る JSON のリクエスト）
 *
 * 入力の誤り（IDの形・チェックの値）は、Accept: application/json を付けた fetch への標準の応答（JSON の422）で返る。
 * 存在しないレコードは検証せず、更新の処理が 404 で返す（検証のあとに物理削除された場合と同じ応答にするため）。
 * 対象の語（注文・予約・お問い合わせ）と、チェックの項目名（shipped・addressed）・読み方（発送済み・対応済み）は、各クラスが決める。
 */
abstract class AdminCheckUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * エラーメッセージの対象の語（注文・予約・お問い合わせ）
     */
    abstract protected function subject(): string;

    /**
     * JSON で受け取るチェックの項目名（shipped・addressed）
     */
    abstract protected function field(): string;

    /**
     * エラーメッセージのチェックの読み方（発送済み・対応済み）
     */
    abstract protected function fieldLabel(): string;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 存在するかは検証しない：行をロックして判定する更新の処理が「存在しない」を返し、コントローラーが 404 にする
            'id' => ['required', 'integer', 'min:1'],
            $this->field() => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        $subject = $this->subject();
        $field = $this->field();
        $label = $this->fieldLabel();

        return [
            'id.required' => "{$subject}を指定してください。",
            'id.integer' => "{$subject}が正しくありません。",
            'id.min' => "{$subject}が正しくありません。",
            "{$field}.required" => "{$label}かどうかを指定してください。",
            "{$field}.boolean" => "{$label}かどうかが正しくありません。",
        ];
    }
}
