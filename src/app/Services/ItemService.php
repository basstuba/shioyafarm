<?php

namespace App\Services;

use App\Models\Item;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * 商品サービス
 */
class ItemService
{
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
    ) {}

    /**
     * 全商品を品目（name）ごとにまとめて取得
     *
     * 品目の並びは各品目の最小IDの順、品目内は重さの昇順。キーは品目名。
     *
     * @return Collection<array-key, EloquentCollection<int, Item>>
     */
    public function getItemsGroupedByName(): Collection
    {
        // ID順の一覧をgroupByすると、品目は最小IDの順に並ぶ
        return $this->itemRepository->getAllItems()
            ->groupBy('name')
            ->map(fn (Collection $items): Collection => $items->sortBy('weight')->values());
    }
}
