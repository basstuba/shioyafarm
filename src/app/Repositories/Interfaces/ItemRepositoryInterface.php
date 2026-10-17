<?php

namespace App\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;

/**
 * 商品リポジトリインターフェース
 */
interface ItemRepositoryInterface
{
    /**
     * おすすめ商品を指定件数取得
     */
    public function getRecommendItems(int $limit): Collection;
}
