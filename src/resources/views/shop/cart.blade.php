@extends('layouts.app')

@section('page-title', 'カート | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    {{-- 見た目は仮置き。Figmaの上限リセット（11月1日）後にデザインの値で仕上げる --}}
    <div class="px-4 pt-header pb-15 text-ink md:pb-24">
        <div class="mx-auto mt-8 max-w-[1000px] md:mt-16">
            <x-page-title class="mt-0 mb-8 font-maru md:mb-10">カートに入っている商品</x-page-title>
            <livewire:cart-list />
        </div>
    </div>
@endsection
