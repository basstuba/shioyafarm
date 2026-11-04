<!DOCTYPE html>
<html lang="ja">
<head>
    @include('layouts.head')
</head>
{{--
    管理画面トップページ専用のレイアウト（ヘッダー・フッターはトップページだけのもの）。
    トップページ以外の管理画面は、左にサイドメニューのあるレイアウト（layouts/admin/app。一覧ページのブランチで作り直す）を使う。
    本文の高さが足りなくても、ヘッダー・本文・フッターが重ならないよう、画面いっぱいの縦積みにして main を伸ばす。
    セクション：page-title（<title>）、content（本文。ヘッダーとフッターの間の上下中央に置く）
    flash の status があれば、ヘッダーの下に案内を出す。
--}}
<body class="flex min-h-dvh flex-col bg-white font-sans text-ink">
    @include('layouts.admin.top-header')
    @if (session('status'))
        {{-- 案内（ログアウトの有効期限切れなど）はヘッダーの下に出る。出ている間、カードはその分下がり、残りの領域の上下中央に置かれる --}}
        <div class="flex justify-center px-4 pt-8">
            <x-admin-status class="w-full max-w-md">{{ session('status') }}</x-admin-status>
        </div>
    @endif
    <main class="flex flex-1 flex-col items-center justify-center px-4 py-8 lg:py-10">
        @yield('content')
    </main>
    @include('layouts.admin.top-footer')
</body>
</html>
