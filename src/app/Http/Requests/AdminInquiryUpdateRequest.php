<?php

namespace App\Http\Requests;

use Override;

/**
 * 管理画面のお問い合わせの対応済みチェックの更新（Alpine から fetch で送る JSON のリクエスト）
 *
 * ルールとメッセージの構成は AdminCheckUpdateRequest。存在しないお問い合わせは検証せず、更新の処理が 404 で返す。
 */
class AdminInquiryUpdateRequest extends AdminCheckUpdateRequest
{
    #[Override]
    protected function subject(): string
    {
        return 'お問い合わせ';
    }

    #[Override]
    protected function field(): string
    {
        return 'addressed';
    }

    #[Override]
    protected function fieldLabel(): string
    {
        return '対応済み';
    }
}
