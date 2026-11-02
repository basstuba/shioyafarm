@extends('layouts.app')

@section('page-title', 'お支払いの確認 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    {{-- 購入ページ・振込先のご案内と同じ、白い大きなカードに緑の中央寄せの見出し --}}
    <div class="px-4 pt-header pb-12 text-ink md:pb-[67px]">
        <div class="mx-auto mt-6 max-w-325 md:mt-[69px]">
            <div class="flex flex-col items-center rounded-[20px] bg-white px-4 py-8 text-center shadow-panel md:rounded-[30px] md:px-6 md:pt-[49px] md:pb-[60px]">
                <h1 class="font-maru text-2xl text-accent md:text-[32px]">お支払いの確認</h1>
                <p class="mt-6 max-w-[860px] text-base leading-[1.8] break-words md:mt-[31px]" role="alert">{{ $message }}</p>
                {{-- 再読み込み（同じURLをもう一度開く）。「注文を確定する」からやり直すと再決済になるため、購入ページへのリンクは出さない --}}
                <x-filled-button :href="request()->fullUrl()" class="mt-8 md:mt-10">再読み込み</x-filled-button>
            </div>
        </div>
    </div>
@endsection
