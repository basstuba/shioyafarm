@extends('layouts.app')

@use('App\Services\ReservationService')

@section('page-title', '商品詳細 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    <div class="px-4 pt-header pb-15 text-ink md:pb-24">
        {{-- 見た目は仮置き。Figmaの上限リセット（11月1日）後にデザインの値で仕上げる --}}
        <article class="mx-auto mt-8 flex max-w-[1000px] flex-col gap-6 rounded-[20px] bg-white px-4 py-6 shadow-card md:mt-16 md:gap-10 md:px-10 md:py-10">
            <div class="flex flex-col gap-6 md:flex-row md:gap-10">
                <div class="min-w-0 overflow-hidden rounded-[10px] md:w-1/2">
                    <x-item-image :item="$item" />
                </div>
                <div class="flex min-w-0 flex-col gap-4 md:w-1/2 md:gap-6">
                    <h2 class="font-maru text-2xl md:text-3xl">{{ $item->name }} {{ $item->weight }}kg</h2>
                    <p class="text-base leading-[1.7] break-words whitespace-pre-line md:text-lg">{{ $item->detail }}</p>
                    <p class="text-xl md:text-2xl">{{ number_format($item->price) }}円(税込)</p>
                </div>
            </div>

            {{-- 予約ページを直接開かれた商品が、すでに予約可能数に達していたときの案内 --}}
            @if (session(ReservationService::CLOSED_FLASH_KEY))
                <p class="text-center text-base text-danger md:text-lg" role="alert">{{ session(ReservationService::CLOSED_FLASH_KEY) }}</p>
            @endif

            @auth
                <div class="flex flex-col items-center gap-4 md:flex-row md:justify-center md:gap-8">
                    @if ($item->is_reserved_only)
                        @if ($reservationClosed)
                            {{-- 予約可能数に達した商品は押せない --}}
                            <x-filled-button type="button" disabled class="cursor-not-allowed opacity-60 hover:bg-action">予約受付終了</x-filled-button>
                        @else
                            <x-filled-button :href="route('shop.reservation.index', $item)">予約する</x-filled-button>
                        @endif
                    @else
                        <livewire:add-to-cart-button :item-id="$item->id" />
                        <x-filled-button :href="route('shop.checkout.index', $item)">購入する</x-filled-button>
                    @endif
                </div>
            @endauth

            {{-- 未ログインのときは会員登録の案内を表示する --}}
            @guest
                <div class="flex flex-col items-center gap-3 text-center font-maru md:gap-4">
                    <x-register-guide />
                </div>
            @endguest
        </article>

        <div class="mt-8 text-center md:mt-10">
            <a href="{{ route('shop.items.index') }}" class="inline-flex min-h-11 items-center font-maru text-base underline md:text-lg">商品一覧に戻る</a>
        </div>
    </div>
@endsection
