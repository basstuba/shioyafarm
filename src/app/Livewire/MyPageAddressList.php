<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithAddressModal;
use App\Livewire\Concerns\WithClampedPagination;
use App\Models\Delivery_address;
use App\Services\AreaService;
use App\Services\DeliveryAddressService;
use App\Support\LoginUser;
use Illuminate\View\View;
use Livewire\Component;

/**
 * マイページのお届け先住所一覧（5件ずつのページ送り）と、住所追加モーダル
 *
 * 会員登録の住所は含めず、追加した配送先住所だけを新しい順に表示する。
 * 住所の追加は購入ページと同じルール・同じサービスで行い（WithAddressModal）、追加したら1ページ目に戻る。
 */
class MyPageAddressList extends Component
{
    use WithAddressModal;
    use WithClampedPagination;

    /** ページ番号の名前（購入履歴・予約履歴とは別のページ番号にする） */
    public const string PAGE_NAME = 'addressPage';

    /**
     * 追加した住所が先頭に見えるよう、1ページ目に戻る
     */
    protected function onAddressAdded(Delivery_address $address, DeliveryAddressService $addressService): void
    {
        $this->resetPage(self::PAGE_NAME);
    }

    public function render(DeliveryAddressService $addressService, AreaService $areaService): View
    {
        $user = LoginUser::require();

        return view('livewire.my-page-address-list', [
            'addresses' => $this->loadClamped(fn () => $addressService->paginate($user, self::PAGE_NAME), self::PAGE_NAME),
            // 都道府県の選択肢は、住所追加モーダルを開いているときだけ取得する
            'prefectures' => $this->showAddressModal ? $areaService->getPrefectures() : collect(),
        ]);
    }
}
