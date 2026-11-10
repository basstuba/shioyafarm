<?php

namespace App\Repositories\Interfaces;

use App\Models\Item;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * 商品リポジトリインターフェース
 */
interface ItemRepositoryInterface
{
    /**
     * おすすめ商品を、ランダムな順で最大で指定件数取得
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

    /**
     * IDの配列で商品をまとめて取得（存在しないIDは含まれない）
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Item>
     */
    public function findByIds(array $ids): Collection;

    /**
     * 商品を論理削除する（削除済み・存在しない商品は何も変えず false）
     */
    public function softDelete(int $id): bool;

    /**
     * IDで商品を1件取得（存在しない・削除済みの商品は null）
     */
    public function find(int $id): ?Item;

    /**
     * 商品を1件登録する
     *
     * 同じ商品名・重さの商品の行をロックしてから、同じ組み合わせの商品がすでにあるかどうかを $beforeSave に渡して呼び、
     * $beforeSave が返した項目（画像のパスなど）を加えて保存する（トランザクション）。
     * $beforeSave は、保存してはいけないときに ValidationException などを投げる（保存せずに巻き戻る）。
     * デッドロックのときはトランザクションごと再試行するため、$beforeSave は何度呼ばれても同じ結果にする。
     *
     * @param  array{name: string, weight: int, price: int, detail: string, recommend: bool, is_reserved_only: bool, reservation_limit: int|null}  $attributes
     * @param  Closure(bool): array{img_url: string}  $beforeSave  引数は、削除していない商品に同じ商品名・重さがあるか
     */
    public function create(array $attributes, Closure $beforeSave): Item;

    /**
     * 商品を1件更新する（存在しない・削除済みの商品は何も変えず false。そのときは $beforeSave を呼ばない）
     *
     * 商品の行と、同じ商品名・重さの商品の行をロックしてから、ロックした商品と、自分以外に同じ組み合わせの商品があるかどうかを $beforeSave に渡して呼び、
     * $beforeSave が返した項目（画像のパスなど）を加えて保存する（トランザクション）。
     * $beforeSave は、保存してはいけないときに ValidationException などを投げる（保存せずに巻き戻る）。
     * デッドロックのときはトランザクションごと再試行するため、$beforeSave は何度呼ばれても同じ結果にする。
     *
     * @param  array{name: string, weight: int, price: int, detail: string, recommend: bool, is_reserved_only: bool, reservation_limit: int|null}  $attributes
     * @param  Closure(Item, bool): array{img_url?: string}  $beforeSave  引数は、ロックした商品と、自分以外に同じ商品名・重さがあるか
     */
    public function update(int $id, array $attributes, Closure $beforeSave): bool;
}
