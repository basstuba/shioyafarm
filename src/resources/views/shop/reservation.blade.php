@extends('layouts.app')

@section('page-title', '予約 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    {{-- 見た目は仮置き。Figmaの上限リセット（11月1日）後にデザインの値で仕上げる --}}
    <div class="px-4 pt-header pb-15 text-ink md:pb-24">
        <div class="mx-auto mt-8 max-w-[1000px] md:mt-16">
            <livewire:reservation-form :item-id="$itemId" :item="$item" :max-quantity="$maxQuantity" />
        </div>
    </div>
@endsection
