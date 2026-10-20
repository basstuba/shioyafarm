<?php

namespace App\Services;

use App\Repositories\Interfaces\AreaRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * 地域（都道府県）サービス
 */
class AreaService
{
    public function __construct(
        private readonly AreaRepositoryInterface $areaRepository,
    ) {}

    /**
     * 都道府県名の一覧を取得（セレクトボックスの選択肢用）
     *
     * @return Collection<int, string>
     */
    public function getPrefectures(): Collection
    {
        return $this->areaRepository->getPrefectureNames();
    }
}
