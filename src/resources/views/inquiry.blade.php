@extends('layouts.app')

@section('page-title', 'お問い合わせ | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    <div class="pt-header">
        <div class="px-4 pt-10 pb-15 md:pt-14 md:pb-[54px]">
            <div class="mx-auto flex max-w-[1000px] flex-col gap-7 rounded-[30px] bg-white px-5 pt-7 pb-8 font-maru shadow-panel md:gap-0 md:p-0 md:pb-[34px]">
                <h1 class="text-center text-2xl text-primary md:flex md:h-[90px] md:items-center md:justify-center md:text-[32px]">お問い合わせフォーム</h1>

                {{-- 送信回数の制限にかかったときのメッセージ --}}
                @if (session('inquiry_throttled'))
                    <p class="text-center text-base text-danger md:mx-auto md:-mt-3 md:mb-5 md:w-100 md:text-left" role="alert">{{-- メッセージ中の改行は、PCのときだけ改行にする（SPは画面幅で折り返す） --}}{!! str_replace("\n", '<br class="hidden md:inline">', e(session('inquiry_throttled'))) !!}</p>
                @endif

                <form class="flex flex-col gap-7 md:mx-auto md:w-100" action="{{ route('top.inquiry.store') }}" method="post" novalidate x-data="{ sending: false }" x-on:submit="sending = true" x-on:pageshow.window="sending = false">
                    @csrf

                    {{-- お名前（姓・名） --}}
                    <fieldset class="min-w-0">
                        <legend class="mb-2 block font-maru text-lg leading-[30px] text-ink md:mb-1.5 md:text-xl">お名前</legend>
                        <div class="flex items-start gap-3 md:gap-5">
                            <x-form-field class="min-w-0 flex-1 md:w-35 md:flex-none" name="last_name" aria-label="姓" placeholder="例：山田" autocomplete="family-name" />
                            <x-form-field class="min-w-0 flex-1 md:w-35 md:flex-none" name="first_name" aria-label="名" placeholder="例：太郎" autocomplete="given-name" />
                        </div>
                    </fieldset>

                    {{-- メールアドレス --}}
                    <x-form-field name="email" label="メールアドレス" type="email" placeholder="例：sample@example.com" autocomplete="email" input-class="md:w-75" />

                    {{-- お問い合わせ内容 --}}
                    <x-form-field name="detail" label="お問い合わせ内容" textarea placeholder="例：直売所はあるのか知りたい" />

                    <div class="flex justify-center md:mt-[9px]">
                        {{-- 送信中はボタンを押せなくして二重送信を防ぐ（戻るで復元されたときは元に戻す） --}}
                        <x-outline-button size="lg" type="submit" x-bind:disabled="sending">送信する</x-outline-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
