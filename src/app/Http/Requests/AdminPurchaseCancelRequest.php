<?php

namespace App\Http\Requests;

use App\Enums\AdminListType;
use Override;

/**
 * 管理画面の注文の取消の入力（注文のIDの形だけを検証する）
 */
class AdminPurchaseCancelRequest extends AdminOrderCancelRequest
{
    #[Override]
    protected function listType(): AdminListType
    {
        return AdminListType::Purchase;
    }
}
