@extends('layouts.app')

@section('page-title', 'ページが見つかりません | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    <div class="px-4 pt-header pb-12 text-ink md:pb-[67px]">
        <div class="mx-auto mt-6 max-w-325 md:mt-[69px]">
            <div class="flex flex-col items-center rounded-[20px] bg-white px-4 py-8 text-center shadow-panel md:rounded-[30px] md:px-6 md:pt-[49px] md:pb-[60px]">
                <h1 class="font-maru text-2xl text-accent md:text-[32px]">ページが見つかりませんでした</h1>
                <p class="mt-6 text-base leading-[1.8] md:mt-[31px]">お探しのページは移動または削除された可能性があります。</p>
                <x-outline-button :href="route('top.index')" class="mt-8 md:mt-10">トップページへ</x-outline-button>
            </div>
        </div>
    </div>
@endsection
