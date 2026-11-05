<!DOCTYPE html>
<html lang="ja">
<head>
    {{-- Alpine.js と [x-cloak] のCSSは Livewire 同梱のものを使う（サイドメニューの開閉・詳細モーダル） --}}
    @livewireStyles
    @include('layouts.head')
</head>
{{--
    トップページ以外の管理画面用のレイアウト（ヘッダー・フッターは layouts/admin/header・footer で、トップページと共通）。
    ヘッダーとフッターの間に、左のサイドメニュー（layouts/admin/sidebar）と本文を横に並べる（SPではメニューは本文の上に開閉する）。
    トップページは layouts/admin/top（サイドメニューなし）。
    サイドメニューの開閉の状態は body の x-data が持つ。
    - open：PCで開いているか。Alpine の $persist で localStorage（admin-sidebar-open）に保存し、再読み込み・ページ移動でも保つ。管理者用ログイン画面で消す（ログイン直後は開く）
    - spOpen：SPでメニューを開いているか。保存しない（ページを移ると閉じる）。PCの状態は使わない
    - ready：Alpine の起動が終わったか。サイドメニューの開閉アニメーション（transition）は ready が true になってから付けるため、再読み込み・ページ移動では動かない
    セクション：page-title（<title>）、content（本文。ページタイトルの h1 は本文に置く）
    flash の status があれば、ヘッダーの下に案内を出す。
--}}
<body class="flex min-h-dvh flex-col bg-white font-sans text-ink" x-data="{ open: $persist(true).as('admin-sidebar-open'), spOpen: false, ready: false }" x-init="$nextTick(() => ready = true)">
    @include('layouts.admin.header', ['withMenu' => true])
    @if (session('status'))
        <div class="flex justify-center px-4 pt-8">
            <x-admin-status class="w-full max-w-md">{{ session('status') }}</x-admin-status>
        </div>
    @endif
    <div class="flex flex-1 flex-col lg:flex-row">
        @include('layouts.admin.sidebar')
        <main class="min-w-0 flex-1 px-4 py-8 lg:px-10 lg:py-10">
            @yield('content')
        </main>
    </div>
    @include('layouts.admin.footer')
    @livewireScripts
</body>
</html>
