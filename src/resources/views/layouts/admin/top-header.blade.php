{{-- 管理画面トップのヘッダー。PCは1行（タイトルが左、操作が右）、SPは2段（タイトルの下に右寄せで操作） --}}
<header class="bg-linear-to-r from-admin-bar from-60% to-admin-bar-end">
    <div class="flex flex-col px-4 py-2 lg:h-[90px] lg:flex-row lg:items-center lg:justify-between lg:py-0 lg:pr-[54px] lg:pl-[46px]">
        <h1 class="py-1 text-xl font-bold lg:py-0">しおや農園直販サイト管理画面</h1>
        <div class="flex items-center justify-end gap-x-8">
            <a class="inline-flex min-h-11 items-center text-lg lg:text-base underline-accent hover:underline focus-visible:underline" href="{{ route('top.index') }}" target="_blank" rel="noopener">サイトを見る</a>
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button class="group flex min-h-11 cursor-pointer items-center gap-2.5 text-lg lg:text-base" type="submit">
                    <img class="size-6" src="{{ asset('storage/image/icon/logout-icon.webp') }}" alt="" width="24" height="24">
                    <span class="underline-accent group-hover:underline group-focus-visible:underline">ログアウト</span>
                </button>
            </form>
        </div>
    </div>
</header>
