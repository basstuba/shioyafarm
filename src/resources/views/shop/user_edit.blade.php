@extends('layouts.app')

@section('page-title', '登録情報変更 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    {{-- 背景はクリーム色、白いカード（幅700px）の中に見出しと入力欄の列（幅400px）を置く。カードの下に「マイページに戻る」 --}}
    <div class="px-4 pt-header pb-[43px] text-ink">
        <div class="mx-auto mt-8 max-w-[700px] md:mt-[81px]">
            <div class="rounded-[20px] bg-white px-4 pt-6 pb-8 shadow-card md:pt-[23px] md:pb-[33px]">
                {{-- x-page-title（ink・semibold）ではなく、緑・Kiwi Maru Regular で直接書く --}}
                <h1 class="text-center font-maru text-2xl leading-tight text-accent md:text-[32px]">登録情報変更</h1>

                @if (session('info_message'))
                    <p class="mx-auto mt-6 max-w-[400px] rounded-[10px] bg-cream-200 px-4 py-3 text-center text-base text-ink md:text-lg" role="status">{{ session('info_message') }}</p>
                @endif

                <form class="mx-auto mt-6 max-w-[400px] md:mt-10" action="{{ route('shop.mypage.update') }}" method="POST" novalidate>
                    @csrf
                    @php($labelClass = 'font-maru text-base font-medium')
                    <div class="flex flex-col gap-6 md:gap-10">
                        <fieldset class="min-w-0">
                            <legend class="mb-2 font-maru text-base font-medium lg:mb-1.5">お名前</legend>
                            <div class="flex items-start gap-3 md:gap-[18px]">
                                <x-auth-input class="min-w-0 flex-1 md:w-[140px] md:flex-none" name="last_name" label="姓" :sr-label="true" :value="$user->last_name" placeholder="例：山田" autocomplete="family-name" />
                                <x-auth-input class="min-w-0 flex-1 md:w-[140px] md:flex-none" name="first_name" label="名" :sr-label="true" :value="$user->first_name" placeholder="例：太郎" autocomplete="given-name" />
                            </div>
                        </fieldset>
                        <x-auth-select class="md:max-w-[300px]" name="prefecture" :label-class="$labelClass" label="お住まいの地域" :options="$prefectures" :value="$user->prefecture" autocomplete="address-level1" />
                        <x-auth-input name="city" label="ご住所" :label-class="$labelClass" :value="$user->city" placeholder="例：適当市適当町３４－５６" autocomplete="address-level2" />
                        <x-auth-input name="tell" label="電話番号" :label-class="$labelClass" type="tel" :value="$user->tell" placeholder="例：09012345678" autocomplete="tel" />
                        <x-auth-input name="email" label="メールアドレス" :label-class="$labelClass" type="email" :value="$user->email" placeholder="例：sample@example.com" autocomplete="email" />
                        <x-auth-input name="password" label="パスワード" :label-class="$labelClass" type="password" placeholder="例：demo12345" autocomplete="new-password" describedby="password-optional password-hint">
                            <x-slot:labelExtra>
                                <span class="font-maru text-base font-medium text-primary-text" id="password-optional">任意</span>
                            </x-slot:labelExtra>
                            <x-slot:hint>
                                <p class="text-xs font-medium" id="password-hint">変更を希望する場合は入力してください。</p>
                            </x-slot:hint>
                        </x-auth-input>
                        <x-auth-input name="password_confirmation" label="確認用パスワード" :label-class="$labelClass" type="password" placeholder="例：demo12345" autocomplete="new-password" describedby="password-confirmation-optional password-confirmation-hint">
                            <x-slot:labelExtra>
                                <span class="font-maru text-base font-medium text-primary-text" id="password-confirmation-optional">任意</span>
                            </x-slot:labelExtra>
                            <x-slot:hint>
                                <p class="text-xs font-medium" id="password-confirmation-hint">変更を希望する場合は入力してください。</p>
                            </x-slot:hint>
                        </x-auth-input>
                        {{-- Figmaにない欄。パスワード欄と同じ形で、確認用パスワードの下に置く --}}
                        <x-auth-input name="current_password" label="現在のパスワード" :label-class="$labelClass" type="password" autocomplete="current-password" describedby="current-password-hint">
                            <x-slot:hint>
                                <p class="text-xs font-medium" id="current-password-hint">メールアドレス・パスワードを変更する場合は入力してください。</p>
                            </x-slot:hint>
                        </x-auth-input>
                    </div>
                    <div class="mt-10 flex justify-center md:mt-[54px]">
                        <x-filled-button size="xs">変更する</x-filled-button>
                    </div>
                </form>
            </div>

            <div class="mt-[42px] flex justify-center">
                <a class="inline-flex min-h-11 items-center font-maru text-base text-ink underline decoration-1" href="{{ route('shop.mypage.index') }}">マイページに戻る</a>
            </div>
        </div>
    </div>
@endsection
