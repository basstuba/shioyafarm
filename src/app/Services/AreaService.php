<?php

namespace App\Services;

use App\Repositories\Interfaces\AreaRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * 地域（都道府県）サービス
 */
class AreaService
{
    private AreaRepositoryInterface $areaRepository;

    public function __construct(AreaRepositoryInterface $areaRepository)
    {
        $this->areaRepository = $areaRepository;
    }

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
