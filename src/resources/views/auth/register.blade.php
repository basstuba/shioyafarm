@extends('layouts.auth')

@section('page-title', '会員登録 | Shioya Farm')

{{-- 会員登録はFigmaに合わせて、PCでは左に背景画像のパネル・右にフォームを置く --}}
@section('auth-direction', 'lg:flex-row-reverse')

@section('heading')
    <h1 class="text-[28px] font-medium text-accent lg:text-[32px]">会員登録</h1>
@endsection

@section('form')
    <form class="mx-auto w-full max-w-[700px] rounded-[20px] bg-white px-6 py-8 shadow-card lg:min-h-[900px] lg:px-8 lg:pt-15 lg:pb-14" action="{{ route('register.store') }}" method="POST" novalidate>
        @csrf
        <div class="mx-auto flex w-full max-w-[400px] flex-col gap-6 lg:gap-8">
            <fieldset class="min-w-0">
                <legend class="mb-2 text-base font-medium lg:mb-1.5">お名前</legend>
                <div class="flex items-start gap-3 lg:gap-5">
                    <x-auth-input class="min-w-0 flex-1 lg:w-35 lg:flex-none" name="last_name" label="姓" :sr-label="true" placeholder="例：山田" autocomplete="family-name" />
                    <x-auth-input class="min-w-0 flex-1 lg:w-35 lg:flex-none" name="first_name" label="名" :sr-label="true" placeholder="例：太郎" autocomplete="given-name" />
                </div>
            </fieldset>
            <x-auth-select class="lg:w-75" name="prefecture" label="お住まいの地域" :options="$prefectures" autocomplete="address-level1" />
            <x-auth-input name="city" label="ご住所" placeholder="例：適当市適当町３４－５６" autocomplete="address-level2" />
            <x-auth-input name="tell" label="電話番号" type="tel" placeholder="例：09012345678" autocomplete="tel" />
            <x-auth-input name="email" label="メールアドレス" type="email" placeholder="例：sample@example.com" autocomplete="email" />
            <x-auth-input name="password" label="パスワード" type="password" placeholder="例：demo12345" autocomplete="new-password" />
            <x-auth-input name="password_confirmation" label="確認用パスワード" type="password" placeholder="例：demo12345" autocomplete="new-password" />
            <x-auth-submit class="lg:mx-auto lg:mt-9.5 lg:w-[130px]">登録する</x-auth-submit>
        </div>
    </form>
@endsection

@section('guide')
    <x-auth-guide :href="route('login')" label="ログイン">すでに会員登録がお済みの方は、<br class="lg:hidden">こちらからログインしてください。</x-auth-guide>
@endsection
