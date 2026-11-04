<!DOCTYPE html>
<html lang="ja">
<head>
    @include('layouts.head')
</head>
{{--
    トップページ以外の管理画面用のレイアウト（いまは仮で、どのページも使っていない）。一覧ページのブランチで、サイドメニュー付き（layouts/admin/sidebar）に作り直す。
    トップページは layouts/admin/top。
    セクション：page-title（<title>）、content（本文）
--}}
<body class="bg-white font-maru text-ink">
    <main class="mx-auto w-full max-w-[1520px] px-4 py-10">
        @yield('content')
    </main>
</body>
</html>
