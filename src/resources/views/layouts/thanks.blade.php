<!DOCTYPE html>
<html lang="ja">
<head>
    @include('layouts.head')
</head>
{{--
    サンクスページ用のレイアウト（お問い合わせ完了・購入完了・予約完了など）。
    共通ヘッダー・フッターは表示せず、<main> の中身だけを出す。
    <body> は縦並びの flex（layouts/auth と同じ）。デモの案内が出ても画面の高さに収まるよう、<main> を flex-1 で残りの高さに広げる。
    ページ側の本文は、<main> の高さに合わせる（min-h-screen ではなく flex-1 を付ける）。
    ページ側のセクション：page-title（<title>）、body-class（背景色など）、content
--}}
<body class="flex min-h-screen flex-col font-maru text-ink @yield('body-class')">
    {{-- 公開デモの案内（DEMO_NOTICE がオンのときだけ） --}}
    <x-demo-notice />
    <main class="flex min-w-0 flex-1 flex-col">@yield('content')</main>
</body>
</html>
