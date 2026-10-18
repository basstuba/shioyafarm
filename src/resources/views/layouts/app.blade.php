<!DOCTYPE html>
<html lang="ja">
<head>
    @include('layouts.head')
</head>
<body class="grid min-h-screen grid-rows-[auto_1fr_auto] @yield('body-class')">
    @include('layouts.header')
    <main class="min-w-0">@yield('content')</main>
    @include('layouts.footer')
</body>
</html>
