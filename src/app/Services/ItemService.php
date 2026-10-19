<?php

namespace App\Services;

use App\Models\Item;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * 商品サービス
 */
class ItemService
{
    private ItemRepositoryInterface $itemRepository;

    public function __construct(ItemRepositoryInterface $itemRepository)
    {
        $this->itemRepository = $itemRepository;
    }

    /**
     * 全商品を品目（name）ごとにまとめて取得
     *
     * 品目の並びは各品目の最小IDの順、品目内は重さの昇順。
     *
     * @return Collection<string, Collection<int, Item>>
     */
    public function getItemsGroupedByName(): Collection
    {
        // ID順の一覧をgroupByすると、品目は最小IDの順に並ぶ
        return $this->itemRepository->getAllItems()
            ->groupBy('name')
            ->map(fn (Collection $items): Collection => $items->sortBy('weight')->values());
    }
}
