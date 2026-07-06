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
     * おすすめ商品を取得
     */
    #[Override]
    public function getRecommendItems(): Collection
    {
        return Item::whereTrue('recommend')
            ->orderBy('id')
            ->get();
    }
}
