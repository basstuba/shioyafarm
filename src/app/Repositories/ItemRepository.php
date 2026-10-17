<?php

namespace App\Repositories;

use App\Models\Item;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Override;

/**
 * 商品リポジトリ
 */
class ItemRepository implements ItemRepositoryInterface
{
    /**
     * おすすめ商品を指定件数取得
     */
    #[Override]
    public function getRecommendItems(int $limit): Collection
    {
        return Item::where('recommend', true)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }
}
