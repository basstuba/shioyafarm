<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,user-scalable=no">
    <meta property='og:type' content='website'>
    <meta property='og:title' content='Shioya Farm'>
    <meta property='og:description' content='TAKAYOSHI SHIOYAの個人開発ウェブサイトです。'>
    <meta name="description" content="TAKAYOSHI SHIOYAの個人開発ウェブサイトです。" />
    <title>Shioya Farm</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kiwi+Maru:wght@300;400;500&family=Noto+Sans+Hebrew:wght@100..900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/webp" href="{{ asset('image/tool/shioya-farm-logo-login.webp') }}">
    <link rel="stylesheet" href="{{ asset('css/reset.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common.css') }}">
    @yield('styles')
</head>
<body>
    @include('layouts.header')
    <main>@yield('content')</main>
    @include('layouts.footer')
</body>
</html>