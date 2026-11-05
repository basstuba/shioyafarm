{{--
    管理画面のヘッダー（トップページとそれ以外の管理画面で共通）。PCは1行（タイトルが左、操作が右）、SPは2段（タイトルの下に操作）
    - $withMenu：true のとき、SPの2段目の左に「メニュー」ボタンを出す（サイドメニューのあるページ。状態 spOpen は layouts/admin/app の body の x-data）。省略時は false（トップページ）
    タイトルは、トップページだけ h1。それ以外のページは本文のページタイトルが h1 のため、見出しにしない（p）。
--}}
@php($withMenu = $withMenu ?? false)
<header class="bg-linear-to-r from-admin-bar from-60% to-admin-bar-end">
    <div class="flex flex-col px-4 py-2 lg:h-[90px] lg:flex-row lg:items-center lg:justify-between lg:py-0 lg:pr-[54px] lg:pl-[46px]">
        @if ($withMenu)
            <p class="py-1 text-xl font-bold lg:py-0">しおや農園直販サイト管理画面</p>
        @else
            <h1 class="py-1 text-xl font-bold lg:py-0">しおや農園直販サイト管理画面</h1>
        @endif
        {{-- メニューボタンがあるページは、幅が足りない（320px前後）とき、操作を次の行へ折り返す（横スクロールを出さない） --}}
        <div class="flex items-center {{ $withMenu ? 'flex-wrap justify-between gap-y-1' : 'justify-end' }} lg:justify-end">
            @if ($withMenu)
                <button class="inline-flex h-11 min-w-20 shrink-0 cursor-pointer items-center justify-center rounded-[10px] border border-line-strong bg-white px-3 text-base whitespace-nowrap lg:hidden" type="button" aria-controls="admin-menu" aria-expanded="false" x-bind:aria-expanded="spOpen" x-on:click="spOpen = ! spOpen">
                    <span x-text="spOpen ? '閉じる' : 'メニュー'">メニュー</span>
                </button>
            @endif
            <div class="flex items-center justify-end {{ $withMenu ? 'ml-auto gap-x-3 lg:gap-x-8' : 'gap-x-8' }}">
                <a class="whitespace-nowrap inline-flex min-h-11 items-center text-lg lg:text-base underline-accent hover:underline focus-visible:underline" href="{{ route('top.index') }}" target="_blank" rel="noopener">サイトを見る</a>
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button class="whitespace-nowrap group flex min-h-11 cursor-pointer items-center gap-2.5 text-lg lg:text-base" type="submit">
                        <img class="size-6" src="{{ asset('storage/image/icon/logout-icon.webp') }}" alt="" width="24" height="24">
                        <span class="underline-accent group-hover:underline group-focus-visible:underline">ログアウト</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
