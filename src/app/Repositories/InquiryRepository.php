<?php

namespace App\Repositories;

use App\Models\Inquiry;
use App\Repositories\Interfaces\InquiryRepositoryInterface;
use Override;

/**
 * お問い合わせリポジトリ
 */
class InquiryRepository implements InquiryRepositoryInterface
{
    /**
     * お問い合わせを保存
     *
     * @param  array{last_name: string, first_name: string, email: string, detail: string, addressed: bool}  $data
     */
    #[Override]
    public function create(array $data): Inquiry
    {
        return Inquiry::create($data);
    }
}
