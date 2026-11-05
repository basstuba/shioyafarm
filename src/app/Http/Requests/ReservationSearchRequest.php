<?php

namespace App\Http\Requests;

use App\Enums\AdminListType;
use Override;

/**
 * 管理画面の予約一覧の検索条件（発送状況・予約日・予約者名）
 *
 * 入力の誤りは予約一覧（全件の表示）に戻して、検索欄の近くにエラーを出す。
 */
class ReservationSearchRequest extends AdminListSearchRequest
{
    #[Override]
    protected function listType(): AdminListType
    {
        return AdminListType::Reservation;
    }
}
