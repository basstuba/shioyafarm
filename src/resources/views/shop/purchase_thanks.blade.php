@extends('layouts.thanks')

@section('page-title', 'ご注文完了 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    {{-- 見た目は仮置き。Figmaの上限リセット（11月1日）後にデザインの値で仕上げる --}}
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-16">
        <img class="absolute inset-0 size-full object-cover" src="{{ asset('storage/image/tool/thanks-page-photo.webp') }}" alt="" aria-hidden="true">

        <div class="relative flex w-full max-w-[720px] flex-col items-center gap-6 rounded-[20px] bg-white/80 px-6 py-10 text-center md:gap-8 md:px-10">
            <h1 class="font-maru text-2xl leading-[1.6] text-accent md:text-3xl">ご注文ありがとうございました。<br class="hidden md:inline">心を込めてお届けいたします。</h1>
            <p class="text-base leading-[1.8] md:text-lg">
                商品はお支払い確定後3~5営業日以内、<br class="hidden md:inline">代金引換の場合はご注文確定後3~5営業日以内に発送いたします。
            </p>
            <a class="inline-flex min-h-11 items-center text-base underline underline-offset-4 hover:opacity-80 md:text-lg" href="{{ route('top.index') }}">ホームに戻る</a>
        </div>
    </div>
@endsection
