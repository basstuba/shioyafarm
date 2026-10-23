<?php

namespace App\Livewire\Concerns;

use App\Enums\PaymentMethod;
use App\Http\Requests\DeliveryAddressRequest;
use App\Models\User;
use App\Services\DeliveryAddressService;
use Illuminate\Support\Facades\Auth;

/**
 * 購入ページ・予約ページの Livewire コンポーネントで共通の、お届け先・支払方法・住所追加モーダル
 *
 * 入力項目（氏名・電話番号・住所・支払方法）、住所追加モーダルの開閉と追加、
 * 検証エラーで戻ったときの入力の復元を持つ。ビューは livewire/partials の部品を使う。
 */
trait WithDeliveryForm
{
    /** お届け先氏名 */
    public string $deliveryName = '';

    /** お届け先電話番号 */
    public string $deliveryTell = '';

    /** お届け先住所の選択肢の値（`user_{会員ID}` / `delivery_{住所ID}`） */
    public string $addressId = '';

    /** 支払方法 */
    public string $paymentMethod = PaymentMethod::Card->value;

    /** 住所追加モーダルを開いているか */
    public bool $showAddressModal = false;

    /** 住所追加モーダルの都道府県（DeliveryAddressRequest と同じ名前にして、同じルールで検証する） */
    public string $delivery_prefecture = '';

    /** 住所追加モーダルの住所（市区町村以降） */
    public string $delivery_city = '';

    /**
     * 住所追加モーダルを開く（前回の入力とエラーは消す）
     */
    public function openAddressModal(): void
    {
        $this->reset(['delivery_prefecture', 'delivery_city']);
        $this->resetErrorBag(['delivery_prefecture', 'delivery_city']);
        $this->showAddressModal = true;
    }

    /**
     * 配送先住所を追加する
     *
     * DeliveryAddressRequest と同じルール・メッセージで検証する。エラーはモーダル内に表示する。
     * 追加できたらモーダルを閉じ、追加した住所をお届け先住所に選択する。
     */
    public function addAddress(DeliveryAddressService $addressService): void
    {
        $validated = $this->validate(
            DeliveryAddressRequest::addressRules(),
            DeliveryAddressRequest::addressMessages(),
        );

        $address = $addressService->add($this->user(), $validated['delivery_prefecture'], $validated['delivery_city']);

        $this->addressId = $addressService->deliveryAddressId($address);
        $this->showAddressModal = false;
        $this->reset(['delivery_prefecture', 'delivery_city']);
    }

    /**
     * ログイン中の会員
     */
    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
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
