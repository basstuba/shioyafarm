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
     *
     * @return Collection<int, Item>
     */
    #[Override]
    public function getRecommendItems(int $limit): Collection
    {
        return Item::where('recommend', true)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * 全商品をID順に取得
     *
     * @return Collection<int, Item>
     */
    #[Override]
    public function getAllItems(): Collection
    {
        return Item::orderBy('id')->get();
    }

    /**
     * IDで商品を1件取得（存在しなければ ModelNotFoundException を投げる）
     */
    #[Override]
    public function findById(int $id): Item
    {
        return Item::findOrFail($id);
    }

    /**
     * IDの配列で商品をまとめて取得（存在しないIDは含まれない）
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Item>
     */
    #[Override]
    public function findByIds(array $ids): Collection
    {
        return Item::whereIn('id', $ids)->orderBy('id')->get();
    }
}
