@extends('layouts.auth')

@section('page-title', 'ログイン | Shioya Farm')

@section('heading')
    <h1 class="text-[28px] font-medium text-accent lg:text-[32px]">ログイン</h1>
@endsection

@section('form')
    <form class="mx-auto flex w-full max-w-[600px] flex-col gap-6 rounded-[20px] bg-white px-6 py-8 shadow-card lg:min-h-[450px] lg:gap-15 lg:px-8 lg:pt-12 xl:px-25" action="{{ route('login.store') }}" method="POST" novalidate>
        @csrf
        <x-auth-input name="email" label="メールアドレス" type="email" placeholder="例：sample@example.com" autocomplete="email" />
        <x-auth-input name="password" label="パスワード" type="password" placeholder="例：demo12345" autocomplete="current-password" />
        <button class="h-11 w-full cursor-pointer rounded-full bg-primary text-base text-white transition-[background-color,box-shadow] duration-200 hover:bg-primary-hover hover:shadow-button focus-visible:bg-primary-hover focus-visible:shadow-button focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark lg:mt-6 lg:h-[34px]" type="submit">ログイン</button>
    </form>
@endsection

@section('guide')
    <p class="leading-[1.6]">まだ会員登録がお済みでない方は、<br class="lg:hidden">こちらからご登録いただけます。</p>
    <a class="inline-flex h-11 items-center justify-center rounded-full border border-primary-dark bg-cream-100 px-10 text-base text-primary-dark no-underline transition-[background-color,color,box-shadow] duration-200 hover:bg-primary-dark hover:text-white hover:shadow-button focus-visible:bg-primary-dark focus-visible:text-white focus-visible:shadow-button focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark lg:h-[34px] lg:w-[130px] lg:px-0" href="{{ route('register') }}">会員登録</a>
@endsection
