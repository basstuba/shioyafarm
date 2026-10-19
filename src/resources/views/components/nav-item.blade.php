{{--
    ヘッダーナビの1項目（リンク、またはログアウトなどのPOSTのform）

    variant="bar"：ヘッダーの項目。SPのメニューに入る項目は、PC（md以上）だけ表示する
    variant="menu"：SPのメニューパネルの行。<li> ごと出力する。リンクを押すと親（header）の close(false) でメニューを閉じる
--}}
@props(['item', 'variant' => 'bar'])
@php
    $isPost = ($item['method'] ?? null) === 'post';
    $onBar = \App\Support\Nav::isSpBar($item);
    $iconUrl = asset('storage/'.$item['icon']);
@endphp
@if ($variant === 'menu')
    <li class="border-b border-line-menu last:border-b-0">
        @if ($isPost)
            <form action="{{ $item['url'] }}" method="POST">
                @csrf
                <button class="flex h-14 w-full cursor-pointer items-center gap-3 px-6 text-left text-base text-ink" type="submit">
                    <img class="size-6 shrink-0 object-contain" src="{{ $iconUrl }}" alt="" />
                    <span class="min-w-0 flex-1">{{ $item['label'] }}</span>
                    <span class="shrink-0 opacity-50" aria-hidden="true">＞</span>
                </button>
            </form>
        @else
            <a class="flex h-14 items-center gap-3 px-6 text-base text-ink no-underline" href="{{ $item['url'] }}" x-on:click="close(false)">
                <img class="size-6 shrink-0 object-contain" src="{{ $iconUrl }}" alt="" />
                <span class="min-w-0 flex-1">{{ $item['label'] }}</span>
                <span class="shrink-0 opacity-50" aria-hidden="true">＞</span>
            </a>
        @endif
    </li>
@elseif ($isPost)
    <form class="{{ $onBar ? '' : 'hidden md:block' }}" action="{{ $item['url'] }}" method="POST">
        @csrf
        <button class="group flex cursor-pointer flex-col items-center gap-0.5 md:flex-row" type="submit">
            <img class="w-6 md:w-5" src="{{ $iconUrl }}" alt="" />
            <span class="text-[10px] whitespace-nowrap text-ink underline-accent group-hover:underline md:text-sm">{{ $item['label'] }}</span>
        </button>
    </form>
@else
    <a class="group {{ $onBar ? 'flex' : 'hidden md:flex' }} flex-col items-center gap-0.5 md:flex-row" href="{{ $item['url'] }}">
        <img class="w-6 md:w-5" src="{{ $iconUrl }}" alt="" />
        <span class="text-[10px] whitespace-nowrap text-ink underline-accent group-hover:underline md:text-sm">{{ $item['label'] }}</span>
    </a>
@endif
