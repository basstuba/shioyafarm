<?php

namespace App\Enums;

/**
 * 管理者の確認コードの照合の結果（コードの誤り・期限切れは ValidationException で返す）
 */
enum AdminTwoFactorVerifyResult
{
    /** コードが正しかった（コードは削除済み） */
    case Verified;

    /** 間違いが上限に達したため、コードを無効にした（コードは削除済み） */
    case Locked;

    /** 1時間あたりの間違いの上限に達したため、コードを無効にした（コードは削除済み。しばらくはログインしてもコードを送らない） */
    case Suspended;
}
