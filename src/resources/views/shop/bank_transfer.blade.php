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
    @endphp

    {{-- 見た目は仮置き。Figmaの上限リセット（11月1日）後にデザインの値で仕上げる --}}
    <div class="px-4 pt-header pb-15 text-ink md:pb-24">
        <div class="mx-auto mt-8 flex max-w-[1000px] flex-col gap-6 md:mt-16 md:gap-8">
            <div class="flex flex-col gap-6 rounded-[20px] bg-white px-4 py-6 shadow-card md:gap-8 md:px-10 md:py-10">
                <h1 class="font-maru text-2xl text-accent md:text-3xl">振込先のご案内</h1>

                <p class="text-base leading-[1.8] md:text-lg">{{ $shippingNotice }}</p>

                {{-- 振込先 --}}
                <dl class="grid gap-x-6 gap-y-3 rounded-[10px] bg-cream-50 px-4 py-4 text-base md:grid-cols-[auto_1fr] md:text-lg" data-testid="bank-transfer-instructions">
                    <dt class="font-maru">銀行名</dt>
                    <dd class="break-words">{{ $bankTransfer->bankName }}</dd>
                    <dt class="font-maru">支店名</dt>
                    <dd class="break-words">{{ $bankTransfer->branchName }}</dd>
                    <dt class="font-maru">口座種別</dt>
                    <dd>{{ $bankTransfer->accountTypeLabel() }}</dd>
                    <dt class="font-maru">口座番号</dt>
                    <dd>{{ $bankTransfer->accountNumber }}</dd>
                    <dt class="font-maru">口座名義</dt>
                    <dd class="break-words">{{ $bankTransfer->accountHolderName }}</dd>
                    <dt class="font-maru">振込金額</dt>
                    <dd class="font-maru text-xl text-primary md:text-2xl">{{ number_format($bankTransfer->amount) }}円</dd>
                </dl>

                {{-- 注文内容 --}}
                <div class="flex flex-col gap-4">
                    <h2 class="font-maru text-lg md:text-xl">{{ $orderHeading }}</h2>
                    <ul class="flex flex-col gap-2 text-base md:text-lg">
                        @foreach ($order->items as $item)
                            <li class="flex justify-between gap-4">
                                <span class="min-w-0 break-words">{{ $item['name'] }} × {{ $item['quantity'] }}個</span>
                                <span class="shrink-0">{{ number_format($item['subtotal']) }}円</span>
                            </li>
                        @endforeach
                    </ul>
                    <dl class="flex flex-col gap-2 rounded-[10px] bg-cream-50 px-4 py-4 text-base md:ml-auto md:w-[320px] md:text-lg">
                        <div class="flex justify-between"><dt>小計</dt><dd>{{ number_format($order->subtotal) }}円</dd></div>
                        <div class="flex justify-between"><dt>送料</dt><dd>{{ number_format($order->shippingFee) }}円</dd></div>
                        <div class="flex justify-between border-t border-line-strong pt-2 font-maru text-xl md:text-2xl">
                            <dt>合計</dt><dd class="text-primary">{{ number_format($order->totalPrice) }}円</dd>
                        </div>
                    </dl>
                    <p class="text-base break-words md:text-lg">お届け先：{{ $order->deliveryName }}（{{ $order->deliveryPrefecture }}{{ $order->deliveryCity }}）</p>
                </div>
            </div>

            @if ($errors->has('payment'))
                <p class="text-center text-base text-danger md:text-lg" role="alert">{{ $errors->first('payment') }}</p>
            @endif

            {{-- どちらかを押したら、両方のボタンを押せなくする（連打で何度も送らない。ブラウザの「戻る」で戻ったときは押せる状態に戻す） --}}
            <div class="flex flex-col items-center gap-4 md:flex-row md:justify-center md:gap-8" x-data="{ submitting: false }" x-on:submit="submitting = true" x-on:pageshow.window="submitting = false">
                {{-- 「戻る」は状態を変える（PaymentIntent をキャンセルする）ため POST --}}
                <form method="POST" action="{{ route($cancelRoute) }}">
                    @csrf
                    <x-outline-button size="lg" x-bind:disabled="submitting" class="disabled:cursor-not-allowed disabled:opacity-60">戻る</x-outline-button>
                </form>
                {{-- 「振込み」も状態を変える（購入データを保存する）ため POST。リンクにするとプリフェッチや外部ページから確定してしまう --}}
                <form method="POST" action="{{ route($confirmRoute) }}">
                    @csrf
                    <x-filled-button x-bind:disabled="submitting" class="disabled:cursor-not-allowed disabled:opacity-60">振込み</x-filled-button>
                </form>
            </div>
        </div>
    </div>
@endsection
