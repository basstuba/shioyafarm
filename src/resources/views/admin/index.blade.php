@extends('layouts.admin.app')

@section('page-title', '管理画面 | Shioya Farm')

{{-- 仮のページ（見出しとログアウトだけ）。トップページは次のブランチでFigmaから作る --}}
@section('content')
    <h1 class="text-center text-[28px] font-medium text-ink">管理画面</h1>
    @if (session('status'))
        <p class="mx-auto mt-6 max-w-md rounded-[10px] border border-line-strong bg-surface px-4 py-3 text-base text-ink" role="status">{{ session('status') }}</p>
    @endif
    <form class="mx-auto mt-10 w-full max-w-60" action="{{ route('admin.logout') }}" method="POST">
        @csrf
        <x-auth-submit variant="accent">ログアウト</x-auth-submit>
    </form>
@endsection
