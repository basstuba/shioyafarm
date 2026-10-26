<?php

namespace App\Livewire\Concerns;

use App\Http\Requests\DeliveryAddressRequest;
use App\Models\Delivery_address;
use App\Services\DeliveryAddressService;
use App\Support\LoginUser;

/**
 * 住所追加モーダル（購入ページ・予約ページ・マイページで共通）
 *
 * モーダルの開閉、入力項目、DeliveryAddressRequest と同じルールでの検証と追加を持つ。
 * 追加できたあとの処理（住所の選択、1ページ目に戻るなど）は、使う側の onAddressAdded に書く。
 * ビューは livewire/partials/address-modal を使う。
 */
trait WithAddressModal
{
    /** 住所追加モーダルを開いているか */
    public bool $showAddressModal = false;

    /** 住所追加モーダルの都道府県（DeliveryAddressRequest と同じ名前にして、同じルールで検証する） */
    public string $delivery_prefecture = '';

    /** 住所追加モーダルの住所（市区町村以降） */
    public string $delivery_city = '';

    /**
     * 住所を追加したあとの処理（モーダルを閉じて入力を消したあとに呼ばれる）
     */
    abstract protected function onAddressAdded(Delivery_address $address, DeliveryAddressService $addressService): void;

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
     * 追加できたらモーダルを閉じる。
     */
    public function addAddress(DeliveryAddressService $addressService): void
    {
        $validated = $this->validate(
            DeliveryAddressRequest::addressRules(),
            DeliveryAddressRequest::addressMessages(),
        );

        $address = $addressService->add(LoginUser::require(), $validated['delivery_prefecture'], $validated['delivery_city']);

        $this->showAddressModal = false;
        $this->reset(['delivery_prefecture', 'delivery_city']);
        $this->onAddressAdded($address, $addressService);
    }
}
