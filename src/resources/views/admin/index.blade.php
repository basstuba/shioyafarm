@extends('layouts.admin.top')

@section('page-title', '管理画面 | Shioya Farm')

{{--
    管理画面のトップページ。レイアウトは layouts/admin/top（サイドメニューなし）。
    リンクは、サイドメニューと共通の App\Support\AdminNav。
    カードは、SP・PCとも上下に並べる（各種一覧が上、作成・登録が下。幅は最大800pxで中央寄せ、高さは中身に合わせる）。Figma は PC で左右2枚だが、資材一覧を足して「各種一覧」が7件になりカードに収まらなくなったため、2026-11-13 にユーザーが決めて変えた。
    リンクは、SP（md 未満）は縦1列、md は2列、PC（lg 以上）は4列（flex の折り返し。最後の行が埋まらないときは中央に寄る）。件数が増えても行が増えるだけで、はみ出さない。
    - $lowMaterialCount：残りわずか（在庫数が基準値を下回った）の資材の件数。1件以上のときだけ、カードの上に赤字の案内と資材一覧へのリンクを出す（0件のときは何も出さない）
--}}
@php
    $cards = \App\Support\AdminNav::groups();
@endphp

@section('content')
    @if ($lowMaterialCount > 0)
        <div class="mb-8 flex flex-col items-center text-center text-base text-danger" role="status">
            <p>残りわずかの資材が {{ $lowMaterialCount }}件 あります。</p>
            <a class="inline-flex min-h-11 items-center underline hover:no-underline focus-visible:no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink" href="{{ route('admin.materials.index') }}">資材一覧を見る</a>
        </div>
    @endif
    <div class="flex w-full max-w-[800px] flex-col gap-8">
        @foreach ($cards as $card)
            <section class="w-full rounded-[20px] bg-white px-4 py-8 shadow-card lg:px-8 lg:py-[43px]">
                <h2 class="text-center text-xl leading-6 {{ $card['titleClass'] }}">{{ $card['title'] }}</h2>
                <nav class="mt-4 lg:mt-[18px]" aria-label="{{ $card['title'] }}">
                    <ul class="flex flex-col md:flex-row md:flex-wrap md:justify-center">
                        @foreach ($card['links'] as $link)
                            <li class="md:w-1/2 lg:w-1/4">
                                <a class="flex min-h-11 items-center justify-center text-lg text-ink underline-thick hover:underline focus-visible:underline {{ $card['linkClass'] }}" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </section>
        @endforeach
    </div>
@endsection
