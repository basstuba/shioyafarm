<?php

namespace App\Services;

use App\Models\Notice;
use Carbon\CarbonInterface;

/**
 * 管理画面のお知らせ一覧の詳細（モーダルに表示する内容）
 */
final readonly class NoticeDetail
{
    public function __construct(
        public int $id,
        public ?CarbonInterface $createdAt,
        public string $title,
        public string $text,
    ) {}

    /**
     * お知らせから作る
     */
    public static function fromNotice(Notice $notice): self
    {
        return new self(
            id: $notice->id,
            createdAt: $notice->created_at,
            title: $notice->title,
            text: $notice->text,
        );
    }
}
