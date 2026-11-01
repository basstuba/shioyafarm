@extends('layouts.app')

@section('page-title', 'カート | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    <div class="px-4 pt-header pb-15 text-ink md:pb-24">
        <div class="mx-auto mt-6 max-w-244 md:mt-[46px]">
            {{-- 見出しは左寄せ（x-page-title を使わない例外） --}}
            <h2 class="mb-6 font-maru text-2xl text-accent md:mb-[46px] md:text-[32px]">カートに入っている商品</h2>
            <livewire:cart-list />
        </div>
    </div>
@endsection
