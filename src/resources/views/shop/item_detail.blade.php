@extends('layouts.app')

@use('App\Services\ReservationService')

@section('page-title', '商品詳細 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    <div class="px-4 pt-header pb-15 text-ink md:pb-24">
        <article class="mx-auto mt-6 max-w-300 rounded-[20px] bg-white pb-6 shadow-panel md:mt-[65px] md:rounded-[30px] md:px-10 md:pt-19 md:pb-15">
            {{-- PCは画像（460px）とテキスト列を横に並べ、ブロック全体を中央に置く。SPは画像を上に全幅で出す --}}
            <div class="flex flex-col md:mx-auto md:w-fit md:max-w-full md:flex-row md:gap-10">
                <div class="min-w-0 overflow-hidden rounded-t-[20px] md:w-115 md:shrink md:self-start md:rounded-none">
                    <x-item-image :item="$item" img-class="aspect-[460/300]" />
                </div>
                {{-- テキスト列は240〜360px（商品名が長いときだけ広がる）。説明文はFigmaと同じ1行10文字になるよう、10.5文字分の幅にする（日本語は代わりのフォントで表示され、1文字が24pxより少し広いため）。価格は列の下端（＝画像の下端）にそろえる --}}
                <div class="flex min-w-0 flex-col gap-4 px-4 pt-4 md:min-w-60 md:max-w-90 md:justify-between md:gap-6 md:p-0">
                    <div class="flex flex-col gap-4 md:gap-[42px]">
                        <h2 class="font-maru text-2xl break-words md:text-[32px]">{{ $item->name }} {{ $item->weight }}kg</h2>
                        <p class="text-lg leading-[1.7] break-words whitespace-pre-line [line-break:strict] md:max-w-[10.5em] md:text-2xl md:leading-10">{{ $item->detail }}</p>
                    </div>
                    <p class="text-xl md:text-2xl">{{ number_format($item->price) }}円(税込)</p>
                </div>
            </div>

            {{-- 予約ページを直接開かれた商品が、すでに予約可能数に達していたときの案内 --}}
            @if (session(ReservationService::CLOSED_FLASH_KEY))
                <p class="mt-6 px-4 text-center text-base text-danger md:mt-8 md:text-lg" role="alert">{{ session(ReservationService::CLOSED_FLASH_KEY) }}</p>
            @endif

            @auth
                <div class="mt-6 flex flex-col items-center gap-4 px-4 md:mt-[58px] md:flex-row md:justify-center md:gap-28">
                    @if ($item->is_reserved_only)
                        @if ($reservationClosed)
                            {{-- 予約可能数に達した商品は押せない --}}
                            <x-filled-button type="button" size="xl" variant="reserve" disabled>予約受付終了</x-filled-button>
                        @else
                            <x-filled-button size="xl" variant="reserve" :href="route('shop.reservation.index', $item)">予約する</x-filled-button>
                        @endif
                    @else
                        <livewire:add-to-cart-button :item-id="$item->id" />
                        <x-filled-button size="xl" :href="route('shop.checkout.index', $item)">購入する</x-filled-button>
                    @endif
                </div>
            @endauth

            {{-- 未ログインのときは会員登録の案内を表示する --}}
            @guest
                <div class="mt-6 flex flex-col items-center gap-3 px-4 text-center font-maru md:mt-[58px] md:gap-4">
                    <x-register-guide />
                </div>
            @endguest
        </article>

        <div class="mt-6 text-center md:mt-[65px]">
            <a href="{{ route('shop.items.index') }}" class="inline-flex min-h-11 items-center font-maru text-base underline">商品一覧に戻る</a>
        </div>
    </div>
@endsection
