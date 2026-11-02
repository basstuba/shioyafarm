@extends('layouts.auth')

@section('page-title', 'パスワード再設定 | Shioya Farm')

{{-- パスワード再設定は会員登録と同じく、PCでは左に背景画像のパネル・右にフォームを置く --}}
@section('auth-direction', 'lg:flex-row-reverse')

@section('heading')
    <h1 class="text-[28px] font-medium text-accent lg:text-[32px]">パスワード再設定</h1>
@endsection

@section('form')
    <form class="mx-auto flex w-full max-w-[600px] flex-col gap-6 rounded-[20px] bg-white px-6 py-8 shadow-card lg:min-h-[450px] lg:gap-10 lg:px-8 lg:pt-12 xl:px-25" action="{{ route('password.email') }}" method="POST" novalidate>
        @csrf
        {{-- SPで「くださ／い」のように語の途中で折り返さないよう、折り返してよい位置だけ <wbr> で示す --}}
        <p class="leading-[1.6] break-keep">ご登録のメールアドレスを<wbr>入力してください。<br>パスワード再設定用のメールを<wbr>お送りします。</p>
        @if (session('status'))
            <p class="rounded-[10px] bg-cream-200 px-4 py-3 text-base text-ink" role="status">{{ session('status') }}</p>
        @endif
        <x-auth-input name="email" label="メールアドレス" type="email" placeholder="例：sample@example.com" autocomplete="email" />
        <x-auth-submit>再設定メールを送信</x-auth-submit>
    </form>
@endsection

@section('guide')
    <x-auth-guide :href="route('login')" label="ログイン">パスワードを思い出した方は、<br class="lg:hidden">こちらからログインしてください。</x-auth-guide>
@endsection
