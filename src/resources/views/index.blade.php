@extends('layouts.app')

@section('body-class', 'bg-cream-100')

@php
    // ファーストビューのスライド画像（6秒ずつ順にフェードで切り替える）
    $slides = [
        ['image' => 'fv-maine-photo.webp', 'delay' => '[animation-delay:0s]'],
        ['image' => 'fv-onion-photo.webp', 'delay' => '[animation-delay:6s]'],
        ['image' => 'fv-agriculture-photo.webp', 'delay' => '[animation-delay:12s]'],
        ['image' => 'fv-potatoes-photo.webp', 'delay' => '[animation-delay:18s]'],
    ];
@endphp

@section('content')
    <div class="pt-header text-ink">
        {{-- 退会したときのメッセージなど --}}
        @if (session('flash_message'))
            <p class="bg-white px-4 py-3 text-center text-base text-accent md:text-lg" role="status">{{ session('flash_message') }}</p>
        @endif

        {{-- ファーストビュー --}}
        <section class="relative h-[480px] overflow-hidden md:h-[640px] lg:h-[810px]">
            @foreach ($slides as $slide)
                {{-- 動きを減らす設定のときは1枚目だけを表示する --}}
                <img
                    class="absolute inset-0 size-full object-cover opacity-0 animate-fv-fade motion-reduce:animate-none {{ $slide['delay'] }} {{ $loop->first ? 'motion-reduce:opacity-100' : '' }}"
                    src="{{ asset('storage/image/tool/' . $slide['image']) }}"
                    alt=""
                >
            @endforeach
            <div class="relative flex h-full flex-col items-center justify-center gap-3 px-5 text-center font-maru font-medium text-white text-shadow-md/60 md:gap-8">
                <h1 class="text-[28px] leading-[1.4] md:text-[40px] xl:text-[64px]">
                    “誰が作ったか”まで、<br class="md:hidden">おいしい理由になる。
                </h1>
                <p class="text-base leading-normal md:text-2xl xl:text-[40px]">
                    ー野菜のことを誰より知る人から、<br class="md:hidden">まっすぐあなたへ。
                </p>
            </div>
        </section>

        {{-- お知らせ --}}
        <section class="scroll-mt-header px-4 py-15 md:py-50" id="news">
            <h2 class="mb-8 text-center font-maru text-[32px] text-accent md:mb-6 md:text-5xl">お知らせ</h2>
            <div class="mx-auto max-w-[1180px] rounded-[20px] bg-white px-5 py-6 md:rounded-[30px] md:px-6 md:py-5">
                {{-- 2件分の高さだけ表示し、それ以降はカードの中でスクロールする（3件目の上端を少し見せて続きがあるとわかるようにする） --}}
                <div class="max-h-70 overflow-y-auto overscroll-contain pr-2 md:max-h-66" tabindex="0" aria-label="お知らせ一覧">
                    @forelse ($notices as $notice)
                        <article class="flex flex-col gap-2 md:gap-6">
                            <div class="flex flex-col gap-2 md:flex-row md:items-baseline md:justify-between md:gap-6">
                                <h3 class="text-lg md:text-2xl">{{ $notice->title }}</h3>
                                <time class="shrink-0 text-sm md:text-base" datetime="{{ $notice->updated_at->toDateString() }}">
                                    {{ $notice->updated_at->format('Y-m-d') }}
                                </time>
                            </div>
                            <p class="text-sm leading-relaxed whitespace-pre-line md:text-base">{{ $notice->text }}</p>
                        </article>
                        @unless ($loop->last)
                            <hr class="my-5 border-line md:my-8">
                        @endunless
                    @empty
                        <p class="text-center text-sm md:text-base">現在お知らせはありません。</p>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- しおや農園について --}}
        <section class="scroll-mt-header" id="about">
            <div class="relative">
                <img
                    class="h-[300px] w-full object-cover md:h-[640px] lg:h-[810px]"
                    src="{{ asset('storage/image/tool/farmer-introduction-photo.webp') }}"
                    alt="畑で作業をする生産者"
                >
                <h2 class="absolute top-4 left-4 font-maru text-[32px] font-medium text-primary md:left-6 md:text-5xl">しおや農園について</h2>
                <p class="px-4 py-6 font-maru text-base leading-[1.8] font-medium md:absolute md:right-0 md:bottom-6 md:p-0 md:pr-6 md:text-2xl md:leading-normal md:text-white lg:text-[28px] xl:text-[36px]">
                    私たち「しおや農園」は、代々続く小さな家族農家です。<br class="hidden md:inline">
                    土づくりから収穫まで、すべてを自分たちの手で行い、<br class="hidden md:inline">
                    「自分の子どもに食べさせたい野菜」を合言葉に、<br class="hidden md:inline">
                    安心・安全な旬の野菜を育てています。
                </p>
            </div>
        </section>

        {{-- おすすめ商品 --}}
        <section class="px-4 pt-15 md:pt-45">
            <h2 class="mb-8 text-center font-maru text-[32px] font-medium text-accent md:mb-[90px] md:text-5xl">おすすめ商品</h2>
            <ul class="mx-auto grid max-w-[1260px] gap-6 md:grid-cols-3 xl:gap-30">
                @foreach ($recommendItems as $item)
                    <li class="flex flex-col bg-white text-center md:h-[460px]">
                        <x-item-image :item="$item" />
                        <div class="flex flex-1 flex-col items-center gap-3 px-5 pt-5 pb-6 md:gap-0 md:px-6 md:pt-[15px] md:pb-[30px]">
                            <h3 class="text-xl md:text-2xl">{{ $item->name }} {{ $item->weight }}kg</h3>
                            <p class="text-base leading-normal md:mt-7 md:text-xl">{{ $item->detail }}</p>
                            <p class="text-lg md:mt-auto md:text-xl">{{ number_format($item->price) }}円(税込)</p>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="mt-10 text-center md:mt-16">
                <a
                    class="block rounded-[30px] border-2 border-sage bg-white py-5 font-maru text-2xl text-forest transition hover:border-accent hover:bg-accent hover:text-white hover:shadow-button md:mx-auto md:flex md:h-[110px] md:w-[600px] md:items-center md:justify-center md:py-0 md:text-[40px]"
                    href="{{ route('shop.items.index') }}"
                >すべての商品を見る</a>
            </div>
        </section>

        {{-- お問い合わせ --}}
        <section class="px-4 pt-20 pb-15 text-center font-maru md:pt-55 md:pb-24">
            <h2 class="mb-5 text-[32px] text-accent md:mb-6 md:text-5xl">お問い合わせ</h2>
            <p class="mb-5 text-base md:mb-8 md:text-2xl">お問い合わせはこちらからお願いいたします</p>
            <a class="text-[22px] text-primary underline underline-offset-8 hover:opacity-80 md:text-[32px]" href="{{ route('top.inquiry') }}">お問い合わせフォームへ</a>
        </section>
    </div>
@endsection
