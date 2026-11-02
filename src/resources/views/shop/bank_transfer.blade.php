@extends('layouts.app')

@section('page-title', '振込先のご案内 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    @php
        // 購入と予約で共通のページ。予約のときは、コントローラーが「戻る」「振込み」の送信先と文言を渡す
        $cancelRoute ??= 'shop.checkout.bank-transfer.cancel';
        $confirmRoute ??= 'shop.checkout.bank-transfer.confirm';
        $orderHeading ??= 'ご注文内容';
        $shippingNotice ??= '下記の口座へお振込みください。お振込みの確認後、3~5営業日以内に発送いたします。';

        // 金額のカード（livewire/partials/order-summary）には購入ページと同じ OrderSummary を渡す。銀行振込なので代引手数料は 0 円で行は出ない
        $summary = new \App\Services\OrderSummary($order->subtotal, 0, $order->shippingFee, $order->codFee);

        // 振込先の項目（項目名 => [値, dd に付けるクラス]）
        $bankRows = [
            '銀行名' => [$bankTransfer->bankName, 'text-xl break-words'],
            '支店名' => [$bankTransfer->branchName, 'text-xl break-words'],
            '口座種別' => [$bankTransfer->accountTypeLabel(), 'text-xl'],
            '口座番号' => [$bankTransfer->accountNumber, 'text-xl'],
            '口座名義' => [$bankTransfer->accountHolderName, 'text-xl break-words'],
            '振込金額' => [number_format($bankTransfer->amount).'円', 'font-maru text-2xl text-primary-text'],
        ];
    @endphp

    {{-- 購入ページ（purchase.blade.php・livewire/purchase-form.blade.php）と同じ作り：白い大きなカードの中に、白い小さなカードを並べる --}}
    <div class="px-4 pt-header pb-12 text-ink md:pb-[67px]">
        <div class="mx-auto mt-6 max-w-325 md:mt-[69px]">
            <div class="rounded-[20px] bg-white px-4 py-8 shadow-panel md:rounded-[30px] md:px-6 md:pt-[49px] md:pb-[60px]">
                <h1 class="text-center font-maru text-2xl text-accent md:text-[32px]">振込先のご案内</h1>

                <div class="mx-auto mt-6 w-full max-w-[860px] md:mt-[31px]">
                    <p class="text-base leading-[1.8]">{{ $shippingNotice }}</p>

                    {{-- 振込先。PCは「項目名｜値」の2列、SPは項目名の下に値 --}}
                    <dl class="mt-6 rounded-[10px] bg-white px-4 py-2 shadow-button md:mt-8 md:px-6" data-testid="bank-transfer-instructions">
                        @foreach ($bankRows as $label => [$value, $valueClass])
                            <div @class(['flex flex-col gap-1 py-3 md:grid md:grid-cols-[160px_1fr] md:items-baseline md:gap-6', 'border-b border-line' => ! $loop->last])>
                                <dt class="text-base text-muted">{{ $label }}</dt>
                                <dd class="{{ $valueClass }}">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    {{-- 注文内容（購入ページの商品の行と同じカード。画像と数量の入力欄はない） --}}
                    <h2 class="mt-12 text-center font-maru text-xl md:mt-[67px]">{{ $orderHeading }}</h2>
                    <ul class="mt-6 flex flex-col gap-4 md:gap-[17px]">
                        @foreach ($order->items as $item)
                            <li class="flex items-center justify-between gap-4 rounded-[10px] bg-white p-4 shadow-button md:px-6">
                                <span class="min-w-0 text-xl break-words">{{ $item['name'] }} × {{ $item['quantity'] }}個</span>
                                <span class="shrink-0 text-base">{{ number_format($item['subtotal']) }}円</span>
                            </li>
                        @endforeach
                    </ul>

                    @include('livewire.partials.order-summary', ['summary' => $summary, 'topClass' => 'md:mt-[17px]'])

                    <div class="mt-8 md:mt-[14px] md:max-w-[480px]">
                        <h3 class="font-maru text-xl">お届け先</h3>
                        <div class="mt-2 flex flex-col gap-1 text-base break-words">
                            <p>{{ $order->deliveryName }}</p>
                            <p>{{ $order->deliveryTell }}</p>
                            <p>{{ $order->deliveryPrefecture }}{{ $order->deliveryCity }}</p>
                        </div>
                    </div>
                </div>
            </div>

            @if ($errors->has('payment'))
                <p class="mt-6 text-center text-base text-danger" role="alert">{{ $errors->first('payment') }}</p>
            @endif

            {{-- どちらかを押したら、両方のボタンを押せなくする（連打で何度も送らない。ブラウザの「戻る」で戻ったときは押せる状態に戻す） --}}
            <div class="mt-8 flex flex-col items-center gap-4 md:mt-[54px] md:flex-row md:justify-center md:gap-8" x-data="{ submitting: false }" x-on:submit="submitting = true" x-on:pageshow.window="submitting = false">
                {{-- 「戻る」は状態を変える（PaymentIntent をキャンセルする）ため POST --}}
                <form method="POST" action="{{ route($cancelRoute) }}">
                    @csrf
                    <x-outline-button size="lg" x-bind:disabled="submitting">戻る</x-outline-button>
                </form>
                {{-- 「振込み」も状態を変える（購入データを保存する）ため POST。リンクにするとプリフェッチや外部ページから確定してしまう --}}
                <form method="POST" action="{{ route($confirmRoute) }}">
                    @csrf
                    <x-filled-button x-bind:disabled="submitting">振込み</x-filled-button>
                </form>
            </div>
        </div>
    </div>
@endsection
