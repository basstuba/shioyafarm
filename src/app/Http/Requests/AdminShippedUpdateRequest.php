<?php

namespace App\Http\Requests;

use App\Enums\AdminListType;
use Override;

/**
 * 管理画面の発送済みチェックの更新（Alpine から fetch で送る JSON のリクエスト。注文・予約で共通）
 *
 * ルールとメッセージの構成は AdminCheckUpdateRequest。存在しない注文・予約は検証せず、更新の処理が 404 で返す。
 */
abstract class AdminShippedUpdateRequest extends AdminCheckUpdateRequest
{
    /**
     * どの一覧の更新か（エラーメッセージの「注文」「予約」は、ここから決まる）
     */
    abstract protected function listType(): AdminListType;

    #[Override]
    protected function subject(): string
    {
        return $this->listType()->subject();
    }

    #[Override]
    protected function field(): string
    {
        return 'shipped';
    }

    #[Override]
    protected function fieldLabel(): string
    {
        return '発送済み';
    }
}
