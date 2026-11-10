<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * 管理画面の掲載商品の登録・修正で入力された内容（検証済み）
 *
 * @see AdminItemService::create()
 * @see AdminItemService::update()
 */
final readonly class ItemInput
{
    /**
     * @param  int|null  $reservationLimit  予約可能数（予約限定で上限ありのときだけ数。通常の商品・上限なしは null）
     * @param  UploadedFile|null  $image  商品画像（修正で選ばなかったときは null。今の画像のまま）
     */
    public function __construct(
        public string $name,
        public int $weight,
        public int $price,
        public string $detail,
        public bool $recommend,
        public bool $isReservedOnly,
        public ?int $reservationLimit,
        public ?UploadedFile $image,
    ) {}
}
