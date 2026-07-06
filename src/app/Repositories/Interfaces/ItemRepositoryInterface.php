<?php

namespace App\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;

/**
 * 商品リポジトリインターフェース
 */
interface ItemRepositoryInterface
{
    /**
     * おすすめ商品取得
     */
    public function getRecommendItems(): Collection;
}
