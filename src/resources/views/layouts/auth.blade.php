<!DOCTYPE html>
<html lang="ja">
<head>
    @include('layouts.head')
    @livewireStyles
</head>
{{--
    認証ページ用のレイアウト（ログイン・会員登録・パスワード再設定の2画面）。
    パスワード欄の表示／非表示の切り替え（components/auth-input）が Livewire 同梱の Alpine.js と [x-cloak] のCSSを使うため、@livewireStyles / @livewireScripts を読み込む（外すとボタンが出なくなる）。
    SPは全面に背景画像を敷いて縦に並べ、PC（lg以上）は左にフォーム・右に背景画像のパネルを置く。
    サイト名・見出し・案内・©は右のパネルに入るため、DOMの順序はSPの並び（order-*）で決めている。
    PCの並び順は auth-direction で変えられる（省略時は左にフォーム・右にパネル。左にパネルを置くなら lg:flex-row-reverse を指定する）。
    背景画像は auth-bg（storage/image/tool 以下のファイル名。省略時は公開側の login-page-back-image.webp）で変えられる。SPの全面背景とPCのパネルの両方に使う。
    ロゴは既定でトップへのリンク。logo-plain セクションを定義するとリンクにしない（管理者用ログイン）。
    guide セクションを定義しないページ（管理者用ログイン）は、SPで案内の分の余白を出さない（PCはパネルの縦の並びのため、余白は残す）。
    ページ側のセクション：page-title（<title>）、auth-direction、auth-bg、logo-plain、heading（見出し）、form（フォーム）、guide（別ページへの案内）
--}}
<body class="font-maru text-ink">
    <div class="flex min-h-screen flex-col bg-(image:--auth-bg) bg-cover bg-center @yield('auth-direction', 'lg:flex-row') lg:bg-none lg:bg-white" style="--auth-bg: url('{{ asset('storage/image/tool/'.$__env->yieldContent('auth-bg', 'login-page-back-image.webp')) }}')">
        <main class="order-3 min-w-0 px-4 pt-8 pb-2 lg:order-none lg:flex lg:flex-1 lg:items-center lg:justify-center lg:p-8">
            @yield('form')
        </main>
        <aside class="contents lg:flex lg:w-[550px] lg:shrink-0 lg:flex-col lg:bg-(image:--auth-bg) lg:bg-cover lg:bg-center">
            <header class="order-1 flex justify-center px-4 pt-4 lg:order-none lg:justify-start lg:px-11 lg:pt-5">
                @php($logoLink = ! $__env->hasSection('logo-plain'))
                <{{ $logoLink ? 'a' : 'div' }} class="flex items-center gap-1{{ $logoLink ? ' no-underline' : '' }}"@if ($logoLink) href="{{ route('top.index') }}"@endif>
                    <img class="h-14 w-14 lg:h-[70px] lg:w-[70px]" src="{{ asset('storage/image/tool/shioya-farm-logo-medium.webp') }}" alt="" />
                    <span class="text-[26px] font-medium text-primary lg:text-[32px]">しおや農園</span>
                </{{ $logoLink ? 'a' : 'div' }}>
            </header>
            <div class="order-2 px-4 pt-12 pb-14 text-center lg:order-none lg:mt-auto lg:p-0 lg:pb-32">
                @yield('heading')
            </div>
            <div @class(['order-4 flex flex-col items-center gap-5 text-center text-sm lg:order-none lg:mb-auto lg:gap-10 lg:px-6 lg:pt-0 lg:pb-46 lg:text-base', 'px-4 py-8' => $__env->hasSection('guide'), 'py-4' => ! $__env->hasSection('guide')])>
                @yield('guide')
            </div>
            <footer class="order-5 mt-auto py-4 text-center text-sm lg:order-none lg:mt-0 lg:pt-0 lg:pb-8 lg:text-base">
                <small>&copy;Shioya farm</small>
            </footer>
        </aside>
    </div>
    {{-- Livewire同梱のAlpine.jsを使う（パスワード欄の表示切り替え） --}}
    @livewireScripts
</body>
</html>
