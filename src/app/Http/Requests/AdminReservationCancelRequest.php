<?php

namespace App\Http\Requests;

use App\Enums\AdminListType;
use Override;

/**
 * 管理画面の予約の取消の入力（予約のIDの形だけを検証する）
 */
class AdminReservationCancelRequest extends AdminOrderCancelRequest
{
    #[Override]
    protected function listType(): AdminListType
    {
        return AdminListType::Reservation;
    }
}
