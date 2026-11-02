@extends('layouts.thanks')

@section('page-title', 'ご注文完了 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    {{-- 写真を画面いっぱいに敷き、白いカードなしで文字を直接置く。SPでも文字が写真の空の部分に乗るよう上寄せにする --}}
    <div class="relative flex min-h-screen flex-col items-center overflow-hidden px-4 pt-30 pb-16 text-center md:pt-[209px]">
        <img class="absolute inset-0 size-full object-cover object-top" src="{{ asset('storage/image/tool/thanks-page-photo.webp') }}" alt="" aria-hidden="true">

        <h1 class="relative text-xl leading-[1.6] text-leaf text-shadow-thanks md:text-2xl md:whitespace-nowrap">ご注文ありがとうございました。<br class="md:hidden">心を込めてお届けいたします。</h1>
        <p class="relative mt-3.5 w-full max-w-[477px] font-sans text-base leading-[30px] text-left text-pretty md:w-max md:max-w-full">
            商品はお支払い確定後3~5営業日以内、<br class="hidden md:inline">代金引換の場合はご注文確定後3~5営業日以内に発送いたします。
        </p>
        <a class="relative mt-10 inline-flex min-h-11 items-center text-base underline underline-offset-4 hover:opacity-80 md:mt-[62px]" href="{{ route('top.index') }}">ホームに戻る</a>
    </div>
@endsection
