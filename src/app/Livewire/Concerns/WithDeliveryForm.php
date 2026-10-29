<?php

namespace App\Livewire\Concerns;

use App\Enums\PaymentMethod;
use App\Models\Delivery_address;
use App\Models\User;
use App\Services\DeliveryAddressService;
use App\Services\OrderSummary;
use Closure;
use InvalidArgumentException;

/**
 * 購入ページ・予約ページの Livewire コンポーネントで共通の、お届け先・支払方法・住所追加モーダル
 *
 * 入力項目（氏名・電話番号・住所・支払方法）、住所追加モーダル（WithAddressModal）、
 * 検証エラーで戻ったときの入力の復元を持つ。ビューは livewire/partials の部品を使う。
 */
trait WithDeliveryForm
{
    use WithAddressModal;

    /** お届け先氏名 */
    public string $deliveryName = '';

    /** お届け先電話番号 */
    public string $deliveryTell = '';

    /** お届け先住所の選択肢の値（`user_{会員ID}` / `delivery_{住所ID}`） */
    public string $addressId = '';

    /** 支払方法 */
    public string $paymentMethod = PaymentMethod::Card->value;

    /**
     * 追加した住所を、お届け先住所に選択する
     */
    protected function onAddressAdded(Delivery_address $address, DeliveryAddressService $addressService): void
    {
        $this->addressId = $addressService->deliveryAddressId($address);
    }

    /**
     * お届け先の初期値を会員登録の内容にする
     */
    private function fillDeliveryDefaults(User $user, DeliveryAddressService $addressService): void
    {
        $this->deliveryName = $user->last_name.$user->first_name;
        $this->deliveryTell = $user->tell;
        $this->addressId = $addressService->userAddressId($user);
    }

    /**
     * 選べる住所のときだけ、お届け先住所に設定する
     */
    private function applyAddressId(User $user, DeliveryAddressService $addressService, string $addressId): void
    {
        if ($addressService->resolve($user, $addressId) !== null) {
            $this->addressId = $addressId;
        }
    }

    /**
     * 選択中のお届け先住所を求める（改ざんされた値・他人の住所のときは、会員登録の住所に戻す）
     *
     * @return array{prefecture: string, city: string}
     */
    private function resolveAddress(User $user, DeliveryAddressService $addressService): array
    {
        $address = $addressService->resolve($user, $this->addressId);
        if ($address === null) {
            $this->addressId = $addressService->userAddressId($user);
            $address = ['prefecture' => $user->prefecture, 'city' => $user->city];
        }

        return $address;
    }

    /**
     * 選択中の住所の都道府県で金額の内訳を計算する
     *
     * 送料の地域が見つからない都道府県（設定にない住所）のときは、描画でエラーにせず、送料を0円にした内訳と、住所欄に出すエラーを返す。
     *
     * @param  Closure(?string): OrderSummary  $summarize  都道府県（送料を0円にするときは null）から金額の内訳を計算する
     * @return array{OrderSummary, ?string} 金額の内訳と、住所欄のエラー（無いときは null）
     */
    private function summarizeWithShippingError(string $prefecture, Closure $summarize): array
    {
        try {
            return [$summarize($prefecture), null];
        } catch (InvalidArgumentException) {
            return [$summarize(null), 'このお届け先住所は送料を計算できません。別の住所を選択してください'];
        }
    }

    /**
     * 検証エラー（または住所追加）で戻ったときの、お届け先と支払方法の入力を復元する
     *
     * 空欄で送信した項目は、ミドルウェアで null になって old() では値が取れないため、
     * 送信された項目かどうかはキーの有無で判定し、空欄のまま復元する（会員の初期値に戻さない）。
     *
     * @param  array<string, mixed>  $oldInput
     */
    private function restoreDeliveryOldInput(User $user, DeliveryAddressService $addressService, array $oldInput): void
    {
        if (array_key_exists('delivery_name', $oldInput)) {
            $this->deliveryName = is_string($oldInput['delivery_name']) ? $oldInput['delivery_name'] : '';
        }

        if (array_key_exists('delivery_tell', $oldInput)) {
            $this->deliveryTell = is_string($oldInput['delivery_tell']) ? $oldInput['delivery_tell'] : '';
        }

        $addressId = $oldInput['address_id'] ?? null;
        if (is_string($addressId)) {
            $this->applyAddressId($user, $addressService, $addressId);
        }

        $method = $oldInput['payment_method'] ?? null;
        if (is_string($method) && PaymentMethod::tryFrom($method) !== null) {
            $this->paymentMethod = $method;
        }
    }

    /**
     * 数量の入力値を復元用に整える（空欄はそのまま。整数の表記なら整数にする）
     */
    private function restoredQuantity(mixed $value): int|string
    {
        $quantity = is_scalar($value) ? trim((string) $value) : '';

        return is_numeric($quantity) && (string) (int) $quantity === $quantity ? (int) $quantity : $quantity;
    }
}
