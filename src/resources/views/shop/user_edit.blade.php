@extends('layouts.app')

@section('page-title', '登録情報変更 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    {{-- 見た目は仮置き。Figmaの上限リセット（11月1日）後にデザインの値で仕上げる --}}
    <div class="px-4 pt-header pb-15 text-ink md:pb-24">
        <div class="mx-auto mt-8 max-w-[700px] md:mt-16">
            <x-page-title class="mt-0 mb-8 font-maru md:mb-10">登録情報変更</x-page-title>

            @if (session('info_message'))
                <p class="mb-6 rounded-[10px] bg-cream-200 px-4 py-3 text-center text-base text-ink md:text-lg" role="status">{{ session('info_message') }}</p>
            @endif

            <form class="rounded-[20px] bg-white px-6 py-8 shadow-card lg:px-8 lg:py-10" action="{{ route('shop.mypage.update') }}" method="POST" novalidate>
                @csrf
                <div class="mx-auto flex w-full max-w-[400px] flex-col gap-6">
                    <fieldset class="min-w-0">
                        <legend class="mb-2 text-base font-medium lg:mb-1.5">お名前</legend>
                        <div class="flex items-start gap-3 lg:gap-5">
                            <x-auth-input class="min-w-0 flex-1" name="last_name" label="姓" :sr-label="true" :value="$user->last_name" placeholder="例：山田" autocomplete="family-name" />
                            <x-auth-input class="min-w-0 flex-1" name="first_name" label="名" :sr-label="true" :value="$user->first_name" placeholder="例：太郎" autocomplete="given-name" />
                        </div>
                    </fieldset>
                    <x-auth-select name="prefecture" label="お住まいの地域" :options="$prefectures" :value="$user->prefecture" autocomplete="address-level1" />
                    <x-auth-input name="city" label="ご住所" :value="$user->city" placeholder="例：適当市適当町３４－５６" autocomplete="address-level2" />
                    <x-auth-input name="tell" label="電話番号" type="tel" :value="$user->tell" placeholder="例：09012345678" autocomplete="tel" />
                    <x-auth-input name="email" label="メールアドレス" type="email" :value="$user->email" placeholder="例：sample@example.com" autocomplete="email" />
                    <div class="flex flex-col gap-2">
                        <x-auth-input name="current_password" label="現在のパスワード" type="password" autocomplete="current-password" />
                        <p class="text-sm leading-relaxed">メールアドレス・パスワードを変更する場合は入力してください。</p>
                    </div>
                    <x-auth-input name="password" label="パスワード（変更する場合のみ）" type="password" placeholder="例：demo12345" autocomplete="new-password" />
                    <x-auth-input name="password_confirmation" label="確認用パスワード（変更する場合のみ）" type="password" placeholder="例：demo12345" autocomplete="new-password" />
                    <div class="flex justify-center">
                        <x-filled-button>変更する</x-filled-button>
                    </div>
                </div>
            </form>

            <div class="mt-6 flex justify-center">
                <a class="inline-flex min-h-11 items-center font-maru text-base underline-accent hover:underline md:text-lg" href="{{ route('shop.mypage.index') }}">マイページに戻る</a>
            </div>
        </div>
    </div>
@endsection
