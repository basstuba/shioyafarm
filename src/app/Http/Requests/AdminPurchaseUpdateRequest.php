<?php

namespace App\Http\Requests;

use App\Enums\AdminListType;
use Override;

/**
 * 管理画面の注文の発送済みチェックの更新（Alpine から fetch で送る JSON のリクエスト）
 */
class AdminPurchaseUpdateRequest extends AdminShippedUpdateRequest
{
    #[Override]
    protected function listType(): AdminListType
    {
        return AdminListType::Purchase;
    }
}
