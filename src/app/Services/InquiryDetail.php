<?php

namespace App\Services;

use App\Models\Inquiry;
use Carbon\CarbonInterface;

/**
 * 管理画面のお問い合わせ一覧の詳細（モーダルに表示する内容）
 */
final readonly class InquiryDetail
{
    public function __construct(
        public int $id,
        public ?CarbonInterface $createdAt,
        public string $fullName,
        public string $email,
        public string $detail,
    ) {}

    /**
     * お問い合わせから作る
     */
    public static function fromInquiry(Inquiry $inquiry): self
    {
        return new self(
            id: $inquiry->id,
            createdAt: $inquiry->created_at,
            fullName: $inquiry->full_name,
            email: $inquiry->email,
            detail: $inquiry->detail,
        );
    }
}
