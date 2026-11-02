@extends('layouts.auth')

@section('page-title', 'ログイン | Shioya Farm')

@section('heading')
    <h1 class="text-[28px] font-medium text-accent lg:text-[32px]">ログイン</h1>
@endsection

@section('form')
    <form class="mx-auto flex w-full max-w-[600px] flex-col gap-6 rounded-[20px] bg-white px-6 py-8 shadow-card lg:min-h-[450px] lg:gap-15 lg:px-8 lg:pt-12 xl:px-25" action="{{ route('login.store') }}" method="POST" novalidate>
        @csrf
        @if (session('status'))
            <p class="rounded-[10px] bg-cream-200 px-4 py-3 text-base text-ink" role="status">{{ session('status') }}</p>
        @endif
        <x-auth-input name="email" label="メールアドレス" type="email" placeholder="例：sample@example.com" autocomplete="email" />
        <x-auth-input name="password" label="パスワード" type="password" placeholder="例：demo12345" autocomplete="current-password" />
        <a class="-mt-2 inline-flex min-h-11 items-center self-end text-base text-primary-dark underline lg:-mt-10 lg:min-h-0" href="{{ route('password.request') }}">パスワードをお忘れの方</a>
        <x-auth-submit class="lg:mt-6">ログイン</x-auth-submit>
    </form>
@endsection

@section('guide')
    <x-auth-guide :href="route('register')" label="会員登録">まだ会員登録がお済みでない方は、<br class="lg:hidden">こちらからご登録いただけます。</x-auth-guide>
@endsection
