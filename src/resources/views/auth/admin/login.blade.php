@extends('layouts.auth')

@section('page-title', '管理者用ログイン | Shioya Farm')

{{-- PCは左にパネル・右にフォーム。背景は管理画面用の画像。ロゴはリンクにしない --}}
@section('auth-direction', 'lg:flex-row-reverse')
@section('auth-bg', 'admin-login-page-back-image.webp')
@section('logo-plain', '1')

@section('heading')
    <h1 class="text-[28px] font-medium text-ink lg:text-[32px]">管理者用ログイン</h1>
@endsection

@section('form')
    <form class="mx-auto flex w-full max-w-[600px] flex-col gap-6 rounded-[20px] bg-white px-6 py-8 shadow-card lg:min-h-[450px] lg:gap-15 lg:px-8 lg:pt-12 xl:px-25" action="{{ route('admin.login.store') }}" method="POST" novalidate x-data="{ sending: false }" x-on:submit="if (sending) { $event.preventDefault(); return }; sending = true; setTimeout(() => sending = false, 10000)" x-on:pageshow.window="sending = false">
        {{-- 二重送信を防ぐ（コードのメールが2通届かないように）。送信中はボタンを押せなくし、「戻る」で戻ったとき（pageshow）と、送信から10秒たっても移らなかったとき（通信が止まった・中止を押した）に押せる状態へ戻す。ボタンに name/value はなく、送信の値は欠けない --}}
        @csrf
        {{-- ログイン画面を開いたら、サイドメニューの開閉の保存（layouts/admin/app の open）を消す（ログイン直後は開く） --}}
        <div x-data x-init="localStorage.removeItem('admin-sidebar-open')" hidden></div>
        @if (session('status'))
            <x-admin-status>{{ session('status') }}</x-admin-status>
        @endif
        <x-auth-input variant="neutral" name="email" label="メールアドレス" type="email" placeholder="例：sample@example.com" autocomplete="email" />
        <x-auth-input variant="neutral" name="password" label="パスワード" type="password" placeholder="例：demo12345" autocomplete="current-password" />
        <a class="-mt-2 inline-flex min-h-11 items-center self-end text-base text-ink underline lg:-mt-10 lg:min-h-0" href="{{ route('admin.password.request') }}">パスワードをお忘れの方</a>
        <x-auth-submit variant="accent" class="lg:mt-6" x-bind:disabled="sending">ログイン</x-auth-submit>
    </form>
@endsection
