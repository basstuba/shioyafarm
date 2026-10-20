<?php

namespace App\Repositories\Interfaces;

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

    /**
     * IDで商品を1件取得（存在しなければ ModelNotFoundException を投げる）
     *
     * @throws ModelNotFoundException<Item>
     */
    public function findById(int $id): Item;
}
