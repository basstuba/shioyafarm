@extends('layouts.app')

@section('page-title', '商品一覧 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@php
    // ご案内（アコーディオン）の項目。1文ずつ段落にする
    $guides = [
        [
            'title' => '送料について',
            'lines' => [
                '送料は別途ご負担となります。',
                '送料は提携運送会社の価格に準じます。',
            ],
        ],
        [
            'title' => '支払方法について',
            'lines' => [
                'クレジットカード・銀行振込・代金引換をご利用いただけます。',
                '代金引換の場合は、別途手数料'.number_format(config('shipping.cod_fee')).'円（税込）がかかります。',
                '予約限定商品は、クレジットカードまたは銀行振込による先払いとなります。',
            ],
        ],
        [
            'title' => 'お届けについて',
            'lines' => [
                'お支払いの確定後、3〜5営業日以内に発送いたします（代金引換はご注文の確定後）。',
                '収穫状況により、発送が前後する場合がございます。',
                '北の恵みセットは9月中の発送となります。',
                '発送元：北海道架空郡架空町567-34',
            ],
        ],
        [
            'title' => '返品について',
            'lines' => [
                '商品の特性上、お客様のご都合による返品はお受けできません。',
                '万が一不良品が届いた場合は、商品の到着後3日以内にご連絡ください。交換または返金にて対応いたします。',
            ],
        ],
    ];
@endphp

@section('content')
    <div class="pt-header text-ink">
        {{-- ファーストビュー（SPは画像が上、PCは左にコピー・右に画像） --}}
        <section class="flex flex-col-reverse bg-white font-maru lg:flex-row lg:items-center lg:bg-transparent">
            <div class="flex flex-col gap-4 px-4 py-8 lg:w-1/2 lg:gap-12 lg:py-0">
                <h1 class="text-2xl leading-[1.6] lg:text-[28px] lg:leading-[1.875] xl:text-[40px] min-[1440px]:text-5xl">
                    作り手の顔が見える、<br>まっすぐな味をお届けします。
                </h1>
                <p class="text-lg lg:pl-7 lg:text-xl xl:text-3xl min-[1440px]:text-4xl">新鮮な畑の恵みを、あなたの食卓へ。</p>
            </div>
            <img
                class="h-[276px] w-full object-cover md:h-[360px] lg:h-[480px] lg:w-1/2"
                src="{{ asset('storage/image/tool/online-fv-family-photo.webp') }}"
                alt="食卓を囲んで笑顔で食事をする家族"
            >
        </section>

        {{-- 会員登録の案内（未ログインのときだけ表示する） --}}
        @guest
            <section class="flex flex-col items-center gap-3 bg-cream-50 px-4 py-5 text-center font-maru md:h-30 md:justify-center md:gap-4 md:py-0">
                <p class="text-sm text-primary md:text-base">※ご購入の際は会員登録と<wbr>ログインをお願いします。</p>
                <x-outline-button :href="route('register')">会員登録</x-outline-button>
            </section>
        @endguest

        {{-- 商品一覧 --}}
        <section class="px-4 pt-12 pb-15 md:pt-20 md:pb-45">
            <h2 class="mb-12 text-center font-maru text-[32px] font-medium text-accent md:mb-24 md:text-5xl">商品一覧</h2>
            <div class="mx-auto flex max-w-[1260px] flex-col gap-12 md:gap-[130px]">
                @forelse ($itemGroups as $name => $items)
                    <div class="flex flex-col gap-6 md:gap-[70px]">
                        <h3 class="flex h-16 font-maru items-center justify-center border border-primary bg-white text-2xl text-primary md:h-25 md:text-[32px]">{{ $name }}</h3>
                        <ul class="flex flex-wrap justify-center gap-x-8 gap-y-6 md:gap-y-10 xl:gap-x-30">
                            @foreach ($items as $item)
                                <li class="flex w-full flex-col md:max-w-[340px] bg-white text-center md:h-[360px] md:w-[calc((100%-2rem)/2)] lg:w-[calc((100%-4rem)/3)] xl:w-[calc((100%-15rem)/3)]">
                                    <x-item-image :item="$item" />
                                    <div class="flex flex-1 flex-col items-center gap-3 px-5 pt-5 pb-6 md:gap-3.5 md:px-4 md:pt-4 md:pb-5">
                                        <h4 class="text-xl md:text-2xl">{{ $item->name }} {{ $item->weight }}kg</h4>
                                        <p class="text-lg md:text-xl">{{ number_format($item->price) }}円(税込)</p>
                                        {{-- 商品詳細ページのルート（shop.items.show）を作成したら route() に差し替える --}}
                                        <x-outline-button class="md:mt-auto" href="#" aria-label="{{ $item->name }} {{ $item->weight }}kgの詳細を見る">詳しく見る</x-outline-button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="text-center text-base md:text-xl">現在販売中の商品はありません。</p>
                @endforelse
            </div>
        </section>

        {{-- ご案内（各項目を独立して開閉できる） --}}
        <section class="px-4 pb-15 md:pb-24">
            <div class="mx-auto grid max-w-[1260px] gap-3 md:grid-cols-2 md:items-start lg:grid-cols-4 lg:gap-x-[72px]">
                @foreach ($guides as $guide)
                    <details class="group">
                        <summary class="flex h-13 font-maru cursor-pointer list-none items-center justify-between rounded-[10px] border border-cream-200 bg-white px-4 text-lg transition-[border-color,box-shadow] duration-200 group-open:rounded-b-none hover:border-transparent hover:shadow-card focus-visible:border-transparent focus-visible:shadow-card focus-visible:outline-none md:h-10 md:text-xl [&::-webkit-details-marker]:hidden">
                            {{ $guide['title'] }}
                            <span class="text-xl text-black transition-transform duration-200 group-open:rotate-90 md:text-2xl" aria-hidden="true">＞</span>
                        </summary>
                        <div class="flex flex-col gap-2 rounded-b-[10px] border border-t-0 border-cream-200 bg-white px-4 pt-4 pb-5 text-base leading-[1.7] md:leading-normal">
                            @foreach ($guide['lines'] as $line)
                                <p>{{ $line }}</p>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    </div>
@endsection
