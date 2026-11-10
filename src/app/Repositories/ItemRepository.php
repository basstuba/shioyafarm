<?php

namespace App\Repositories;

use App\Models\Item;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * 商品リポジトリ
 */
class ItemRepository implements ItemRepositoryInterface
{
    /** 登録・修正のトランザクションを、デッドロックのときに試す回数 */
    private const int TRANSACTION_ATTEMPTS = 3;

    /**
     * おすすめ商品を、ランダムな順で最大で指定件数取得
     *
     * @return Collection<int, Item>
     */
    #[Override]
    public function getRecommendItems(int $limit): Collection
    {
        return Item::where('recommend', true)
            ->inRandomOrder()
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

    /**
     * 商品を論理削除する（削除済み・存在しない商品は何も変えず false）
     */
    #[Override]
    public function softDelete(int $id): bool
    {
        // 論理削除のスコープで、削除していない行だけが対象になる（deleted_at の更新が1行も起きなければ false）
        return Item::query()->whereKey($id)->delete() > 0;
    }

    /**
     * IDで商品を1件取得（存在しない・削除済みの商品は null）
     */
    #[Override]
    public function find(int $id): ?Item
    {
        return Item::find($id);
    }

    #[Override]
    public function create(array $attributes, Closure $beforeSave): Item
    {
        return DB::transaction(function () use ($attributes, $beforeSave): Item {
            $duplicated = $this->lockSameLabel($attributes['name'], $attributes['weight'])->isNotEmpty();

            return Item::create([...$attributes, ...$beforeSave($duplicated)]);
        }, self::TRANSACTION_ATTEMPTS);
    }

    #[Override]
    public function update(int $id, array $attributes, Closure $beforeSave): bool
    {
        return DB::transaction(function () use ($id, $attributes, $beforeSave): bool {
            // 論理削除のスコープで、削除していない商品だけが対象になる
            $item = Item::query()->whereKey($id)->lockForUpdate()->first();
            if ($item === null) {
                return false;
            }

            $duplicated = $this->lockSameLabel($attributes['name'], $attributes['weight'])->contains(fn (Item $other): bool => $other->id !== $id);
            $item->update([...$attributes, ...$beforeSave($item, $duplicated)]);

            return true;
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * 同じ商品名・重さの商品の行をロックして返す（同時の登録・修正で、同じ組み合わせが2件できるのを防ぐ。
     * 削除していない商品だけ。ロックの取り合いでデッドロックになったら、トランザクションごと再試行する）
     *
     * @return Collection<int, Item>
     */
    private function lockSameLabel(string $name, int $weight): Collection
    {
        return Item::query()->where('name', $name)->where('weight', $weight)->lockForUpdate()->get(['id']);
    }
}
