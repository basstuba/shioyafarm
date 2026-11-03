<!DOCTYPE html>
<html lang="ja">
<head>
    @include('layouts.head')
</head>
{{--
    管理画面のレイアウト（仮）。管理画面のトップページをFigmaから作るブランチで、サイドバー付きの構成に作り直す。
    セクション：page-title（<title>）、content（本文）
--}}
<body class="bg-white font-maru text-ink">
    <main class="mx-auto w-full max-w-[1520px] px-4 py-10">
        @yield('content')
    </main>
</body>
</html>
