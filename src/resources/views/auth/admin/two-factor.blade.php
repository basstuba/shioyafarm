@extends('layouts.auth')

@section('page-title', '確認コードの入力 | Shioya Farm')

{{-- パスワード再設定の2画面と同じ構成（管理画面用の背景画像、ロゴはリンクにしない、PCは左にフォーム・右にパネル、案内（guide）は置かない） --}}
@section('auth-bg', 'admin-login-page-back-image.webp')
@section('logo-plain', '1')

@section('heading')
    <h1 class="text-[28px] font-medium text-ink lg:text-[32px]">確認コードの入力</h1>
@endsection

@section('form')
    {{-- 「コードを再送する」は別のフォーム（入れ子にできないため、ボタンの form 属性でつなぐ）。Enter で送信されるのは先頭の「ログインする」 --}}
    <form id="two-factor-resend" action="{{ route('admin.two-factor.resend') }}" method="POST" hidden>
        @csrf
    </form>
    <form class="mx-auto flex w-full max-w-[600px] flex-col gap-6 rounded-[20px] bg-white px-6 py-8 shadow-card lg:min-h-[450px] lg:gap-10 lg:px-8 lg:pt-12 xl:px-25" action="{{ route('admin.two-factor.store') }}" method="POST" novalidate>
        @csrf
        {{-- SPで語の途中で折り返さないよう、折り返してよい位置だけ <wbr> で示す --}}
        <p class="leading-[1.6] break-keep">ご登録のメールアドレス宛てに、<wbr>6桁の確認コードを<wbr>お送りしました。<wbr>メールに記載のコードを<wbr>入力してください。<wbr>コードの有効期限は{{ $expireMinutes }}分です。</p>
        @if (session('status'))
            <x-admin-status>{{ session('status') }}</x-admin-status>
        @endif
        <x-auth-input variant="neutral" name="code" label="確認コード" type="text" inputmode="numeric" maxlength="6" placeholder="例：123456" autocomplete="one-time-code" />
        <div class="flex flex-col gap-3 lg:gap-4">
            <x-auth-submit variant="accent">ログインする</x-auth-submit>
            <button class="h-11 w-full cursor-pointer rounded-full border border-line-strong bg-white text-base text-ink transition-colors duration-200 hover:bg-surface focus-visible:bg-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink lg:h-[34px]" type="submit" form="two-factor-resend">コードを再送する</button>
        </div>
        <a class="-mt-2 inline-flex min-h-11 items-center justify-center text-base text-ink underline lg:-mt-4" href="{{ route('admin.login') }}">ログインへ戻る</a>
    </form>
@endsection
