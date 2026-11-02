@extends('layouts.app')

@section('page-title', 'マイページ | Shioya Farm')

@section('body-class', 'bg-cream-100')

@php
    // ページ内リンク（見出しのidへ移動する）
    $sections = [
        'profile' => '登録情報',
        'addresses' => 'お届け先住所一覧',
        'purchases' => '購入履歴',
        'reservations' => '予約履歴',
        'withdraw' => '退会手続き',
    ];
@endphp

@section('content')
    <div class="pt-header text-ink">
        {{-- ファーストビュー：写真に白75%を重ね、緑の見出しを置く --}}
        <section class="relative flex h-[120px] items-center justify-center overflow-hidden px-4 md:h-[200px]">
            <img class="absolute inset-0 size-full object-cover" src="{{ asset('storage/image/tool/my-page-fv-flowers-photo.webp') }}" alt="">
            <div class="absolute inset-0 bg-white/75" aria-hidden="true"></div>
            <h1 class="relative font-maru text-[32px] text-accent md:text-5xl">マイページ</h1>
        </section>

        {{-- ページ内リンク（PCは間隔108px。狭い画面では折り返す） --}}
        <nav class="flex flex-wrap items-center justify-center gap-x-5 bg-white px-4 py-3 font-maru text-base md:min-h-[100px] md:gap-x-10 lg:gap-x-[108px]" aria-label="マイページの目次">
            @foreach ($sections as $id => $label)
                <a class="inline-flex min-h-11 items-center text-primary-text underline decoration-1" href="#{{ $id }}">{{ $label }}</a>
            @endforeach
        </nav>

        @if (session('flash_message'))
            <p class="mx-auto my-4 max-w-[600px] rounded-[10px] bg-white px-4 py-3 text-center text-base text-accent shadow-panel md:text-lg" role="status">{{ session('flash_message') }}</p>
        @endif

        {{-- 登録情報（ラベルの列は205px。PCは値の列の左端が中央から33pxの位置になるよう左に余白を取る） --}}
        <section class="scroll-mt-header bg-cream-50 px-4 pt-[52px] pb-7" id="profile">
            <x-section-title>登録情報</x-section-title>
            <dl class="mx-auto mt-14 max-w-[580px] md:pl-[118px]">
                <div class="flex flex-col py-3 md:flex-row md:py-5">
                    <dt class="font-maru text-base md:w-[205px] md:shrink-0">氏名</dt>
                    <dd class="min-w-0 break-words">{{ $user->last_name }} {{ $user->first_name }}</dd>
                </div>
                <div class="flex flex-col py-3 md:flex-row md:py-5">
                    <dt class="font-maru text-base md:w-[205px] md:shrink-0">住所</dt>
                    <dd class="min-w-0 break-words">{{ $user->prefecture }}{{ $user->city }}</dd>
                </div>
                <div class="flex flex-col py-3 md:flex-row md:py-5">
                    <dt class="font-maru text-base md:w-[205px] md:shrink-0">電話番号</dt>
                    <dd class="min-w-0 break-words">{{ $user->tell }}</dd>
                </div>
                <div class="flex flex-col py-3 md:flex-row md:py-5">
                    <dt class="font-maru text-base md:w-[205px] md:shrink-0">メールアドレス</dt>
                    <dd class="min-w-0 break-all">{{ $user->email }}</dd>
                </div>
            </dl>
            <div class="mt-6 flex justify-center md:mt-9">
                <a class="inline-flex min-h-11 items-center text-primary-text underline decoration-1" href="{{ route('shop.mypage.edit') }}">登録情報を変更する</a>
            </div>
        </section>

        {{-- お届け先住所一覧 --}}
        <section class="scroll-mt-header bg-white pt-10" id="addresses">
            <x-section-title>お届け先住所一覧</x-section-title>
            <livewire:my-page-address-list />
        </section>

        {{-- 購入履歴 --}}
        <section class="scroll-mt-header bg-cream-50 pt-8" id="purchases">
            <x-section-title>購入履歴</x-section-title>
            <livewire:my-page-purchase-history />
        </section>

        {{-- 予約履歴 --}}
        <section class="scroll-mt-header bg-white pt-7" id="reservations">
            <x-section-title>予約履歴</x-section-title>
            <livewire:my-page-reservation-history />
        </section>

        {{-- 退会手続き --}}
        <section class="scroll-mt-header bg-cream-50 px-4 pt-[51px] pb-[50px]" id="withdraw" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
            <x-section-title>退会手続き</x-section-title>
            <p class="mt-[29px] text-center font-maru text-sm leading-relaxed font-medium">退会をご希望の場合は、こちらからお手続きください。<br class="hidden md:inline">ご不明点があればお問い合わせください。</p>
            <div class="mt-[35px] flex justify-center">
                <x-small-button x-on:click="open = true">退会する</x-small-button>
            </div>

            {{-- 退会の確認モーダル（幅400px。高さは文とボタンの間の余白で決まる） --}}
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-show="open" x-cloak role="dialog" aria-modal="true" aria-labelledby="withdraw-modal-title">
                <div class="absolute inset-0 bg-black/35" x-on:click="open = false" aria-hidden="true"></div>
                <div class="relative max-h-full w-full max-w-[400px] overflow-y-auto rounded-[30px] bg-white px-6 pt-10 pb-10 shadow-panel md:px-[30px] md:pt-[51px] md:pb-[52px]">
                    <h2 id="withdraw-modal-title" class="sr-only">退会の確認</h2>
                    <p class="font-maru text-base leading-6">退会すると、ログインやマイページのご利用ができなくなり、元に戻すことはできません。本当に退会してよろしいですか？</p>
                    <div class="mt-8 flex flex-wrap justify-center gap-4 md:mt-[95px] md:gap-[50px]">
                        <form action="{{ route('shop.mypage.delete') }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <x-small-button type="submit">退会する</x-small-button>
                        </form>
                        <x-small-button variant="cancel" x-on:click="open = false">キャンセル</x-small-button>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
