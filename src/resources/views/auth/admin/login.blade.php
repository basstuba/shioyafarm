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
    <form class="mx-auto flex w-full max-w-[600px] flex-col gap-6 rounded-[20px] bg-white px-6 py-8 shadow-card lg:min-h-[450px] lg:gap-15 lg:px-8 lg:pt-12 xl:px-25" action="{{ route('admin.login.store') }}" method="POST" novalidate>
        @csrf
        @if (session('status'))
            <p class="rounded-[10px] border border-line-strong bg-surface px-4 py-3 text-base text-ink" role="status">{{ session('status') }}</p>
        @endif
        <x-auth-input variant="neutral" name="email" label="メールアドレス" type="email" placeholder="例：sample@example.com" autocomplete="email" />
        <x-auth-input variant="neutral" name="password" label="パスワード" type="password" placeholder="例：demo12345" autocomplete="current-password" />
        <a class="-mt-2 inline-flex min-h-11 items-center self-end text-base text-ink underline lg:-mt-10 lg:min-h-0" href="{{ route('admin.password.request') }}">パスワードをお忘れの方</a>
        <x-auth-submit variant="accent" class="lg:mt-6">ログイン</x-auth-submit>
    </form>
@endsection
