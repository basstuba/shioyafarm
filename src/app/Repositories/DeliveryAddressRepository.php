<?php

namespace App\Repositories;

use App\Models\Delivery_address;
use App\Repositories\Interfaces\DeliveryAddressRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Override;

/**
 * 配送先住所リポジトリ
 */
class DeliveryAddressRepository implements DeliveryAddressRepositoryInterface
{
    /**
     * 会員の配送先住所をID順に取得
     *
     * @return Collection<int, Delivery_address>
     */
    #[Override]
    public function getByUserId(int $userId): Collection
    {
        return Delivery_address::where('user_id', $userId)->orderBy('id')->get();
    }

    /**
     * 会員の配送先住所を1件取得（他の会員の住所・存在しない住所は null）
     */
    #[Override]
    public function findByUserId(int $userId, int $id): ?Delivery_address
    {
        return Delivery_address::where('user_id', $userId)->where('id', $id)->first();
    }

    /**
     * 配送先住所を登録
     */
    #[Override]
    public function create(int $userId, string $prefecture, string $city): Delivery_address
    {
        return Delivery_address::create([
            'user_id' => $userId,
            'delivery_prefecture' => $prefecture,
            'delivery_city' => $city,
        ]);
    }
}
