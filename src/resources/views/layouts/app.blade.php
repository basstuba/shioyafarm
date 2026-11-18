<!DOCTYPE html>
<html lang="ja">
<head>
    @include('layouts.head')
    @livewireStyles
</head>
{{--
    公開側の共通レイアウト（固定ヘッダー・フッター付き）。
    このレイアウトを使うページは、content セクションの最上位の要素に pt-header（固定ヘッダーの高さの余白）を付ける前提。
    デモの案内（DEMO_NOTICE）は、その余白の中に収めるため、本文を -mt-header で引き上げて表示している。pt-header がないページでは、案内の分だけ本文がヘッダーの下に潜り込む。
    ページ側のセクション：page-title（<title>）、body-class、content
--}}
<body class="grid min-h-screen grid-rows-[auto_1fr_auto] @yield('body-class')">
    @include('layouts.header')
    <main class="min-w-0">
        @if (config('demo.notice'))
            {{--
                公開デモの案内。固定ヘッダー（高さ h-header）の真下に置く。各ページの本文は先頭に pt-header の余白を持つため、
                案内の分だけ本文を h-header 上へ引き上げて、案内がその余白に収まるようにする（本文の位置は案内の高さの分だけ下がる）
            --}}
            <x-demo-notice class="relative mt-header" />
            <div class="-mt-header">@yield('content')</div>
        @else
            @yield('content')
        @endif
    </main>
    @include('layouts.footer')
    {{-- Livewire同梱のAlpine.jsを全ページで使う（ヘッダーのメニュー開閉など） --}}
    @livewireScripts
</body>
</html>
