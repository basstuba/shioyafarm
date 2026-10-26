<?php

namespace App\Repositories\Interfaces;

use App\Models\Delivery_address;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 配送先住所リポジトリインターフェース
 */
interface DeliveryAddressRepositoryInterface
{
    /**
     * 会員の配送先住所をID順に取得
     *
     * @return Collection<int, Delivery_address>
     */
    public function getByUserId(int $userId): Collection;

    /**
     * 会員の配送先住所を1件取得（他の会員の住所・存在しない住所は null）
     */
    public function findByUserId(int $userId, int $id): ?Delivery_address;

    /**
     * 配送先住所を登録
     */
    public function create(int $userId, string $prefecture, string $city): Delivery_address;

    /**
     * 会員の配送先住所を新しい順にページ分けして取得
     *
     * @return LengthAwarePaginator<int, Delivery_address>
     */
    public function paginateByUserId(int $userId, int $perPage, string $pageName): LengthAwarePaginator;
}
