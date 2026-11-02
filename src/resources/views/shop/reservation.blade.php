@extends('layouts.app')

@section('page-title', '予約 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    {{-- 白い大きなカードはヘッダーの下69px（SPは24px）から。下の余白は「商品一覧に戻る」からフッターまで --}}
    <div class="px-4 pt-header pb-12 text-ink md:pb-[67px]">
        <div class="mx-auto mt-6 max-w-325 md:mt-[69px]">
            <livewire:reservation-form :item-id="$itemId" :item="$item" :max-quantity="$maxQuantity" />
        </div>
    </div>
@endsection
