<?php

namespace App\Http\Requests;

use App\Enums\AdminListType;
use Override;

/**
 * 管理画面の注文一覧の検索条件（発送状況・注文日・注文者名）
 *
 * 入力の誤りは注文一覧（全件の表示）に戻して、検索欄の近くにエラーを出す。
 */
class PurchaseSearchRequest extends AdminListSearchRequest
{
    #[Override]
    protected function listType(): AdminListType
    {
        return AdminListType::Purchase;
    }
}
