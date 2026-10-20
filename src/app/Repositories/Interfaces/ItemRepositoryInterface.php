<?php

namespace App\Repositories\Interfaces;

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;

/**
 * 商品リポジトリインターフェース
 */
interface ItemRepositoryInterface
{
    /**
     * おすすめ商品を指定件数取得
     *
     * @return Collection<int, Item>
     */
    public function getRecommendItems(int $limit): Collection;

    /**
     * 全商品をID順に取得
     *
     * @return Collection<int, Item>
     */
    public function getAllItems(): Collection;
}
