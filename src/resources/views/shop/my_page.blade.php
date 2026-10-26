@extends('layouts.app')

@section('page-title', 'マイページ | Shioya Farm')

@section('body-class', 'bg-cream-100')

@php
    // ページ内リンク（見出しのidへ移動する）
    $sections = [
        'profile' => '登録情報',
        'addresses' => '配送先住所一覧',
        'purchases' => '購入履歴',
        'reservations' => '予約履歴',
        'withdraw' => '退会手続き',
    ];
@endphp

@section('content')
    {{-- 見た目は仮置き。Figmaの上限リセット（11月1日）後にデザインの値で仕上げる --}}
    <div class="pt-header text-ink">
        {{-- ファーストビュー --}}
        <section class="relative h-[200px] overflow-hidden md:h-[320px]">
            <img class="absolute inset-0 size-full object-cover" src="{{ asset('storage/image/tool/my-page-fv-flowers-photo.webp') }}" alt="">
            <div class="relative flex h-full items-center justify-center px-5">
                <h1 class="font-maru text-[32px] font-medium text-white text-shadow-md/60 md:text-5xl">マイページ</h1>
            </div>
        </section>

        <div class="mx-auto max-w-[1000px] px-4 pb-15 md:pb-24">
            @if (session('flash_message'))
                <p class="mt-6 rounded-[10px] bg-white px-4 py-3 text-center text-base text-accent shadow-panel md:text-lg" role="status">{{ session('flash_message') }}</p>
            @endif

            {{-- ページ内リンク --}}
            <nav class="mt-6 flex flex-wrap justify-center gap-x-4 gap-y-1 font-maru text-base md:gap-x-8 md:text-lg" aria-label="マイページの目次">
                @foreach ($sections as $id => $label)
                    <a class="inline-flex min-h-11 items-center underline-accent hover:underline" href="#{{ $id }}">{{ $label }}</a>
                @endforeach
            </nav>

            {{-- 登録情報 --}}
            <section class="scroll-mt-header pt-8" id="profile">
                <x-page-title class="mt-0 mb-5 font-maru">登録情報</x-page-title>
                <dl class="rounded-[20px] bg-white px-5 py-4 shadow-card md:px-10 md:py-6">
                    <div class="flex flex-col gap-0.5 border-b border-line py-3 md:flex-row md:gap-6">
                        <dt class="font-maru md:w-40 md:shrink-0">お名前</dt>
                        <dd class="min-w-0 break-words">{{ $user->last_name }} {{ $user->first_name }}</dd>
                    </div>
                    <div class="flex flex-col gap-0.5 border-b border-line py-3 md:flex-row md:gap-6">
                        <dt class="font-maru md:w-40 md:shrink-0">ご住所</dt>
                        <dd class="min-w-0 break-words">{{ $user->prefecture }}{{ $user->city }}</dd>
                    </div>
                    <div class="flex flex-col gap-0.5 border-b border-line py-3 md:flex-row md:gap-6">
                        <dt class="font-maru md:w-40 md:shrink-0">電話番号</dt>
                        <dd class="min-w-0 break-words">{{ $user->tell }}</dd>
                    </div>
                    <div class="flex flex-col gap-0.5 py-3 md:flex-row md:gap-6">
                        <dt class="font-maru md:w-40 md:shrink-0">メールアドレス</dt>
                        <dd class="min-w-0 break-all">{{ $user->email }}</dd>
                    </div>
                </dl>
                <div class="mt-5 flex justify-center">
                    <x-outline-button :href="route('shop.mypage.edit')">登録情報を変更する</x-outline-button>
                </div>
            </section>

            {{-- お届け先住所一覧 --}}
            <section class="scroll-mt-header pt-12" id="addresses">
                <x-page-title class="mt-0 mb-5 font-maru">お届け先住所一覧</x-page-title>
                <livewire:my-page-address-list />
            </section>

            {{-- 購入履歴 --}}
            <section class="scroll-mt-header pt-12" id="purchases">
                <x-page-title class="mt-0 mb-5 font-maru">購入履歴</x-page-title>
                <livewire:my-page-purchase-history />
            </section>

            {{-- 予約履歴 --}}
            <section class="scroll-mt-header pt-12" id="reservations">
                <x-page-title class="mt-0 mb-5 font-maru">予約履歴</x-page-title>
                <livewire:my-page-reservation-history />
            </section>

            {{-- 退会手続き --}}
            <section class="scroll-mt-header pt-12" id="withdraw" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                <x-page-title class="mt-0 mb-5 font-maru">退会手続き</x-page-title>
                <p class="text-center text-base leading-relaxed md:text-lg">退会をご希望の場合は、こちらからお手続きください。<br class="hidden md:inline">ご不明点があればお問い合わせください。</p>
                <div class="mt-5 flex justify-center">
                    <button type="button" class="inline-flex h-11 w-40 cursor-pointer items-center justify-center rounded-[30px] border-2 border-danger bg-danger font-maru text-base text-white transition-opacity hover:opacity-80 focus-visible:opacity-80 focus-visible:outline-none" x-on:click="open = true">退会する</button>
                </div>

                {{-- 退会の確認モーダル --}}
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-show="open" x-cloak role="dialog" aria-modal="true" aria-labelledby="withdraw-modal-title">
                    <div class="absolute inset-0 bg-black/35" x-on:click="open = false" aria-hidden="true"></div>
                    <div class="relative max-h-full w-full max-w-[480px] overflow-y-auto rounded-[20px] bg-white px-6 py-8 shadow-card md:px-10">
                        <h2 id="withdraw-modal-title" class="sr-only">退会の確認</h2>
                        <p class="text-base leading-relaxed md:text-lg">退会すると、ログインやマイページのご利用ができなくなり、元に戻すことはできません。本当に退会してよろしいですか？</p>
                        <div class="mt-6 flex flex-col items-center gap-3 md:flex-row md:justify-center md:gap-5">
                            <form action="{{ route('shop.mypage.delete') }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex h-11 w-40 cursor-pointer items-center justify-center rounded-[30px] border-2 border-danger bg-danger font-maru text-base text-white transition-opacity hover:opacity-80 focus-visible:opacity-80 focus-visible:outline-none">退会する</button>
                            </form>
                            <button type="button" class="inline-flex h-11 w-40 cursor-pointer items-center justify-center rounded-[30px] border-2 border-line-strong bg-surface font-maru text-base text-ink transition-opacity hover:opacity-80 focus-visible:opacity-80 focus-visible:outline-none" x-on:click="open = false">キャンセル</button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
