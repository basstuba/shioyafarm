<?php

namespace App\Enums;

/**
 * 退会済みの会員の物理削除の結果
 */
enum UserDeleteResult
{
    /** 削除できた（会員と、注文・予約・振込情報・配送先住所） */
    case Deleted;

    /** 未発送で未取消の注文または予約が残っているため、何も変えなかった */
    case HasUnshipped;

    /** 退会していない、または存在しない会員のため、何も変えなかった */
    case NotWithdrawn;

    /** データベースのエラー（ロック待ちのタイムアウト・デッドロックなど）で削除できなかった（何も消えていない） */
    case Failed;
}
