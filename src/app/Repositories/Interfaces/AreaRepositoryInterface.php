<?php

namespace App\Repositories\Interfaces;

use Illuminate\Support\Collection;

/**
 * 地域（都道府県）リポジトリインターフェース
 */
interface AreaRepositoryInterface
{
    /**
     * 都道府県名の一覧をID順に取得
     *
     * @return Collection<int, string>
     */
    public function getPrefectureNames(): Collection;
}
