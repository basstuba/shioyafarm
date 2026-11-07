<?php

namespace App\Enums;

/**
 * 管理画面の注文・予約の発送済みの更新の結果
 */
enum ShippedUpdateResult
{
    /** 更新できた */
    case Updated;

    /** 存在しない注文・予約のため、何も変えなかった（検証のあとに物理削除されたときなど） */
    case NotFound;

    /** 取消済みのため、何も変えなかった */
    case Canceled;
}
