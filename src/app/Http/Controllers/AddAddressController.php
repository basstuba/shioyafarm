<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryAddressRequest;
use App\Services\DeliveryAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * 配送先住所追加コントローラ
 */
class AddAddressController extends Controller
{
    /**
     * 配送先住所を追加して、元のページへ戻る
     *
     * 購入ページの住所追加モーダルは Livewire が同じルール・同じサービスで処理する。
     * このルートは、JavaScript を使わずにフォームを送信した場合の受け口。
     * 追加した住所は、戻った購入ページのお届け先住所で選択された状態にする。
     */
    public function store(DeliveryAddressRequest $request, DeliveryAddressService $deliveryAddressService): RedirectResponse
    {
        Log::debug('AddAddressController store start');

        $user = $request->user();
        abort_if($user === null, 403);

        $address = $deliveryAddressService->add(
            $user,
            $request->string('delivery_prefecture')->toString(),
            $request->string('delivery_city')->toString(),
        );

        Log::debug('AddAddressController store end');

        return back()->withInput(['address_id' => $deliveryAddressService->deliveryAddressId($address)]);
    }
}
