@extends('layouts.auth')

@section('page-title', 'パスワード再設定 | Shioya Farm')

{{-- 管理画面用の背景画像。ロゴはリンクにしない。PCは会員のパスワード再設定と同じく左にフォーム・右にパネル（ログインとは反対）。案内（guide）は置かない --}}
@section('auth-bg', 'admin-login-page-back-image.webp')
@section('logo-plain', '1')

@section('heading')
    <h1 class="text-[28px] font-medium text-ink lg:text-[32px]">パスワード再設定</h1>
@endsection

@section('form')
    <form class="mx-auto flex w-full max-w-[600px] flex-col gap-6 rounded-[20px] bg-white px-6 py-8 shadow-card lg:min-h-[450px] lg:gap-10 lg:px-8 lg:pt-12 xl:px-25" action="{{ route('admin.password.email') }}" method="POST" novalidate>
        @csrf
        {{-- SPで「くださ／い」のように語の途中で折り返さないよう、折り返してよい位置だけ <wbr> で示す --}}
        <p class="leading-[1.6] break-keep">ご登録のメールアドレスを<wbr>入力してください。<br>パスワード再設定用のメールを<wbr>お送りします。</p>
        @if (session('status'))
            <x-admin-status>{{ session('status') }}</x-admin-status>
        @endif
        <x-auth-input variant="neutral" name="email" label="メールアドレス" type="email" placeholder="例：sample@example.com" autocomplete="email" />
        <x-auth-submit variant="accent">再設定メールを送信</x-auth-submit>
        <a class="-mt-2 inline-flex min-h-11 items-center justify-center text-base text-ink underline lg:-mt-4" href="{{ route('admin.login') }}">ログインへ戻る</a>
    </form>
@endsection
