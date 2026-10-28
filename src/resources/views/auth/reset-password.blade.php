@extends('layouts.auth')

@section('page-title', '新しいパスワードの設定 | Shioya Farm')

{{-- 送信ページと同じく、PCでは左に背景画像のパネル・右にフォームを置く --}}
@section('auth-direction', 'lg:flex-row-reverse')

@section('heading')
    <h1 class="text-[28px] font-medium text-accent lg:text-[32px]">新しいパスワードの設定</h1>
@endsection

@section('form')
    {{--
        メールのリンクで渡されたトークンとメールアドレスは hidden で送る。
        トークンが無効・期限切れ（メールアドレスの食い違いや欠落を含む）のときは、入力欄の下ではなく案内を出し、再設定メールの送信ページへ誘導する。
    --}}
    <form class="mx-auto flex w-full max-w-[600px] flex-col gap-6 rounded-[20px] bg-white px-6 py-8 shadow-card lg:min-h-[450px] lg:gap-10 lg:px-8 lg:pt-12 xl:px-25" action="{{ route('password.update') }}" method="POST" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}" />
        <input type="hidden" name="email" value="{{ is_string(old('email')) ? old('email') : $email }}" />
        <p class="leading-[1.6]">新しいパスワードを入力してください。</p>
        @if ($errors->has('email') || $errors->has('token'))
            <p class="rounded-[10px] bg-cream-200 px-4 py-3 text-base text-danger" role="alert">
                このリンクは無効か、有効期限が切れています。もう一度再設定メールを送信してください。<br>
                <a class="text-primary-dark underline" href="{{ route('password.request') }}">再設定メールを送信する</a>
            </p>
        @endif
        <x-auth-input name="password" label="新しいパスワード" type="password" placeholder="例：demo12345" autocomplete="new-password" />
        <x-auth-input name="password_confirmation" label="確認用パスワード" type="password" placeholder="例：demo12345" autocomplete="new-password" />
        <button class="h-11 w-full cursor-pointer rounded-full bg-primary text-base text-white transition-[background-color,box-shadow] duration-200 hover:bg-primary-hover hover:shadow-button focus-visible:bg-primary-hover focus-visible:shadow-button focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark lg:h-[34px]" type="submit">パスワードを再設定する</button>
    </form>
@endsection

@section('guide')
    <p class="leading-[1.6]">パスワードを思い出した方は、<br class="lg:hidden">こちらからログインしてください。</p>
    <a class="inline-flex h-11 items-center justify-center rounded-full border border-primary-dark bg-cream-100 px-10 text-base text-primary-dark no-underline transition-[background-color,color,box-shadow] duration-200 hover:bg-primary-dark hover:text-white hover:shadow-button focus-visible:bg-primary-dark focus-visible:text-white focus-visible:shadow-button focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark lg:h-[34px] lg:w-[130px] lg:px-0" href="{{ route('login') }}">ログイン</a>
@endsection
