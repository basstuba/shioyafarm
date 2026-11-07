<?php

namespace App\Http\Requests;

use App\Enums\AdminListType;
use App\Support\AdminListUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * 管理画面の注文・予約の取消の入力（注文・予約のIDの形だけを検証する。注文と予約で共通）
 *
 * 存在するか・発送済みか・取消済みかは、行をロックして判定するリポジトリ（cancel）だけで行う。
 * 入力の誤りは、取消の前に見ていた一覧に戻してエラーを出す。
 */
abstract class AdminOrderCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * どの一覧の取消か（戻り先とエラーメッセージの「注文」「予約」は、ここから決まる）
     */
    abstract protected function listType(): AdminListType;

    #[Override]
    protected function getRedirectUrl(): string
    {
        return AdminListUrl::previousOrIndex($this->listType()->listRoute(), $this->listType()->searchRoute());
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
        $subject = $this->listType()->subject();

        return [
            'id.required' => "取り消す{$subject}を指定してください。",
            'id.integer' => "取り消す{$subject}が正しくありません。",
        ];
    }
}
