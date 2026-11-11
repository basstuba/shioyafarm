@extends('layouts.admin.top')

@section('page-title', '管理画面 | Shioya Farm')

{{--
    管理画面のトップページ。レイアウトは layouts/admin/top（サイドメニューなし）。
    リンクは、サイドメニューと共通の App\Support\AdminNav。
--}}
@php
    $cards = \App\Support\AdminNav::groups();
@endphp

@section('content')
    <div class="flex w-full flex-col items-center gap-8 lg:flex-row lg:justify-center xl:gap-36">
        @foreach ($cards as $card)
            <section class="w-full rounded-[20px] bg-white px-4 py-8 shadow-card lg:h-100 lg:w-125 lg:px-8 lg:pt-[43px] lg:pb-0">
                <h2 class="text-center text-xl leading-6 {{ $card['titleClass'] }}">{{ $card['title'] }}</h2>
                <nav class="mt-4 lg:mt-[18px]" aria-label="{{ $card['title'] }}">
                    <ul>
                        @foreach ($card['links'] as $link)
                            <li>
                                <a class="flex min-h-11 items-center justify-center text-lg text-ink underline-thick hover:underline focus-visible:underline lg:min-h-0 lg:py-3.5 lg:text-base lg:leading-[19px] {{ $card['linkClass'] }}" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </section>
        @endforeach
    </div>
@endsection
