<?php

namespace App\Services;

use App\Models\Delivery_address;
use App\Models\User;
use App\Repositories\Interfaces\DeliveryAddressRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 配送先住所サービス
 *
 * お届け先住所の選択肢の値は、会員登録の住所が `user_{会員ID}`、追加した配送先住所が `delivery_{住所ID}`。
 */
class DeliveryAddressService
{
    /** マイページの一覧の1ページの件数 */
    public const int PER_PAGE = 5;

    /** 会員登録の住所の値の接頭辞 */
    private const string USER_PREFIX = 'user';

    /** 追加した配送先住所の値の接頭辞 */
    private const string DELIVERY_PREFIX = 'delivery';

    public function __construct(
        private readonly DeliveryAddressRepositoryInterface $deliveryAddressRepository,
    ) {}

    /**
     * 会員登録の住所の選択肢の値
     */
    public function userAddressId(User $user): string
    {
        return self::USER_PREFIX.'_'.$user->id;
    }

    /**
     * 追加した配送先住所の選択肢の値
     */
    public function deliveryAddressId(Delivery_address $address): string
    {
        return self::DELIVERY_PREFIX.'_'.$address->id;
    }

    /**
     * お届け先住所の選択肢を取得する（先頭は会員登録の住所、続けて追加した配送先住所をID順）
     *
     * @return list<array{value: string, label: string}>
     */
    public function getOptions(User $user): array
    {
        $options = [[
            'value' => $this->userAddressId($user),
            'label' => $user->prefecture.$user->city,
        ]];

        foreach ($this->deliveryAddressRepository->getByUserId($user->id) as $address) {
            $options[] = [
                'value' => $this->deliveryAddressId($address),
                'label' => $address->delivery_prefecture.$address->delivery_city,
            ];
        }

        return $options;
    }

    /**
     * 選択肢の値から住所を求める
     *
     * 形式が正しくない値、他の会員の住所、存在しない住所は null を返す。
     *
     * @return array{prefecture: string, city: string}|null
     */
    public function resolve(User $user, string $addressId): ?array
    {
        if (preg_match('/\A(user|delivery)_([0-9]+)\z/', $addressId, $matches) !== 1) {
            return null;
        }

        if ($matches[1] === self::USER_PREFIX) {
            return $matches[2] === (string) $user->id
                ? ['prefecture' => $user->prefecture, 'city' => $user->city]
                : null;
        }

        $address = $this->deliveryAddressRepository->findByUserId($user->id, (int) $matches[2]);

        return $address === null
            ? null
            : ['prefecture' => $address->delivery_prefecture, 'city' => $address->delivery_city];
    }

    /**
     * 配送先住所を追加する
     */
    public function add(User $user, string $prefecture, string $city): Delivery_address
    {
        return $this->deliveryAddressRepository->create($user->id, $prefecture, $city);
    }

    /**
     * マイページのお届け先住所一覧を、新しい順にページ分けして取得する（会員登録の住所は含めない）
     *
     * @return LengthAwarePaginator<int, Delivery_address>
     */
    public function paginate(User $user, string $pageName): LengthAwarePaginator
    {
        return $this->deliveryAddressRepository->paginateByUserId($user->id, self::PER_PAGE, $pageName);
    }
}
