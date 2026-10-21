@extends('layouts.app')

@section('page-title', 'お支払いの確認 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    {{-- 見た目は仮置き。Figmaの上限リセット（11月1日）後にデザインの値で仕上げる --}}
    <div class="px-4 pt-header pb-15 text-ink md:pb-24">
        <div class="mx-auto mt-8 flex max-w-[1000px] flex-col items-center gap-6 rounded-[20px] bg-white px-4 py-10 text-center shadow-card md:mt-16">
            <h1 class="font-maru text-2xl text-accent md:text-3xl">お支払いの確認</h1>
            <p class="text-base leading-[1.8] break-words md:text-lg" role="alert">{{ $message }}</p>
            {{-- 再読み込み（同じURLをもう一度開く）。「注文を確定する」からやり直すと再決済になるため、購入ページへのリンクは出さない --}}
            <a href="{{ request()->fullUrl() }}" class="inline-flex h-11 items-center justify-center rounded-[30px] border-2 border-action bg-action px-8 font-maru text-xl text-ink no-underline transition-colors duration-200 hover:bg-primary-hover focus-visible:bg-primary-hover focus-visible:outline-none md:h-10">再読み込み</a>
        </div>
    </div>
@endsection
