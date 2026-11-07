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

    /**
     * JSON の本文の文言（更新できたときは null）
     */
    public function message(AdminListType $type): ?string
    {
        return match ($this) {
            self::Updated => null,
            self::NotFound => $type->subject().'が見つかりません。',
            self::Canceled => '取り消されているため、発送状況を変更できません。ページを再読み込みしてください。',
        };
    }

    /**
     * 一覧の発送済みのチェックの案内の文言（更新できたときは null）
     *
     * 存在しない行は、再試行しても直らないため、再読み込みを促す。
     */
    public function listMessage(AdminListType $type): ?string
    {
        return match ($this) {
            self::Updated => null,
            self::NotFound => $type->subject().'が見つかりません。ページを再読み込みしてください。',
            self::Canceled => $this->message($type),
        };
    }
}
