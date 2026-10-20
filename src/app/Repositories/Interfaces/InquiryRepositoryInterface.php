<?php

namespace App\Repositories\Interfaces;

use App\Models\Inquiry;

/**
 * お問い合わせリポジトリインターフェース
 */
interface InquiryRepositoryInterface
{
    /**
     * お問い合わせを保存
     *
     * @param  array{last_name: string, first_name: string, email: string, detail: string, addressed: bool}  $data
     */
    public function create(array $data): Inquiry;
}
