@extends('layouts.auth')

@section('page-title', '新しいパスワードの設定 | Shioya Farm')

{{-- 送信ページと同じ構成（管理画面用の背景画像、ロゴはリンクにしない、PCは左にフォーム・右にパネル、案内は置かない） --}}
@section('auth-bg', 'admin-login-page-back-image.webp')
@section('logo-plain', '1')

@section('heading')
    <h1 class="text-[28px] font-medium text-ink lg:text-[32px]">新しいパスワードの設定</h1>
@endsection

@section('form')
    {{--
        メールのリンクで渡されたトークンとメールアドレスは hidden で送る。
        トークンが無効・期限切れ（メールアドレスの食い違いや欠落を含む）のときは、入力欄の下ではなく案内を出し、再設定メールの送信ページへ誘導する。
    --}}
    <form class="mx-auto flex w-full max-w-[600px] flex-col gap-6 rounded-[20px] bg-white px-6 py-8 shadow-card lg:min-h-[450px] lg:gap-10 lg:px-8 lg:pt-12 xl:px-25" action="{{ route('admin.password.update') }}" method="POST" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}" />
        <input type="hidden" name="email" value="{{ is_string(old('email')) ? old('email') : $email }}" />
        <p class="leading-[1.6]">新しいパスワードを入力してください。</p>
        @if (session('status'))
            <x-admin-status>{{ session('status') }}</x-admin-status>
        @endif
        @if ($errors->has('email') || $errors->has('token'))
            <p class="rounded-[10px] border border-line-strong bg-surface px-4 py-3 text-base text-danger" role="alert">
                このリンクは無効か、有効期限が切れています。もう一度再設定メールを送信してください。<br>
                <a class="inline-flex min-h-11 items-center text-ink underline" href="{{ route('admin.password.request') }}">再設定メールを送信する</a>
            </p>
        @endif
        <x-auth-input variant="neutral" name="password" label="新しいパスワード" type="password" placeholder="例：demo12345" autocomplete="new-password" />
        <x-auth-input variant="neutral" name="password_confirmation" label="確認用パスワード" type="password" placeholder="例：demo12345" autocomplete="new-password" />
        <x-auth-submit variant="accent">パスワードを再設定する</x-auth-submit>
        <a class="-mt-2 inline-flex min-h-11 items-center justify-center text-base text-ink underline lg:-mt-4" href="{{ route('admin.login') }}">ログインへ戻る</a>
    </form>
@endsection
