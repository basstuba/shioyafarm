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
        <p class="leading-[1.6]">ご登録のメールアドレスを入力してください。<br class="hidden lg:inline">パスワード再設定用のメールをお送りします。</p>
        @if (session('status'))
            <p class="rounded-[10px] bg-cream-200 px-4 py-3 text-base text-ink" role="status">{{ session('status') }}</p>
        @endif
        <x-auth-input name="email" label="メールアドレス" type="email" placeholder="例：sample@example.com" autocomplete="email" />
        <button class="h-11 w-full cursor-pointer rounded-full bg-primary text-base text-white transition-[background-color,box-shadow] duration-200 hover:bg-primary-hover hover:shadow-button focus-visible:bg-primary-hover focus-visible:shadow-button focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark lg:h-[34px]" type="submit">再設定メールを送信</button>
    </form>
@endsection

@section('guide')
    <p class="leading-[1.6]">パスワードを思い出した方は、<br class="lg:hidden">こちらからログインしてください。</p>
    <a class="inline-flex h-11 items-center justify-center rounded-full border border-primary-dark bg-cream-100 px-10 text-base text-primary-dark no-underline transition-[background-color,color,box-shadow] duration-200 hover:bg-primary-dark hover:text-white hover:shadow-button focus-visible:bg-primary-dark focus-visible:text-white focus-visible:shadow-button focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark lg:h-[34px] lg:w-[130px] lg:px-0" href="{{ route('login') }}">ログイン</a>
@endsection
