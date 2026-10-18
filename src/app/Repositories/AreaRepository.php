<?php

namespace App\Repositories;

use App\Models\Area;
use App\Repositories\Interfaces\AreaRepositoryInterface;
use Illuminate\Support\Collection;
use Override;

/**
 * 地域（都道府県）リポジトリ
 */
class AreaRepository implements AreaRepositoryInterface
{
    /**
     * 都道府県名の一覧をID順に取得
     *
     * @return Collection<int, string>
     */
    #[Override]
    public function getPrefectureNames(): Collection
    {
        return Area::orderBy('id')->pluck('prefecture');
    }
}
