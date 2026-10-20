<!DOCTYPE html>
<html lang="ja">
<head>
    @include('layouts.head')
</head>
{{--
    サンクスページ用のレイアウト（お問い合わせ完了・購入完了・予約完了など）。
    共通ヘッダー・フッターは表示せず、<main> の中身だけを出す。
    ページ側のセクション：page-title（<title>）、body-class（背景色など）、content
--}}
<body class="min-h-screen font-maru text-ink @yield('body-class')">
    <main class="min-w-0">@yield('content')</main>
</body>
</html>
