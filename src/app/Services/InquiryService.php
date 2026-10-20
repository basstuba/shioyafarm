<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Repositories\Interfaces\InquiryRepositoryInterface;

/**
 * お問い合わせサービス
 */
class InquiryService
{
    public function __construct(
        private readonly InquiryRepositoryInterface $inquiryRepository,
    ) {}

    /**
     * お問い合わせを未対応の状態で保存
     *
     * 対応済みかどうかは入力から受け取らず、常に未対応で保存する。
     *
     * @param  array{last_name: string, first_name: string, email: string, detail: string}  $data
     */
    public function createInquiry(array $data): Inquiry
    {
        return $this->inquiryRepository->create([...$data, 'addressed' => false]);
    }
}
