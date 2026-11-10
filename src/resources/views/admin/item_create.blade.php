@extends('layouts.admin.app')

@section('page-title', '掲載商品登録 | 管理画面 | Shioya Farm')

{{--
    管理画面の掲載商品登録ページ（Figma 174:56）。レイアウトは layouts/admin/app（サイドメニューあり。「掲載商品登録」が太字）。
    フォームは登録・修正で共通の <x-admin-item-form>（App\View\Components\AdminItemForm）。タイトルは primary-text（#8f5e1c。橙 #f0a13a は文字だと読みにくいため）。
--}}
@section('content')
    <div class="flex flex-col items-center">
        <h1 class="text-center text-[28px] font-normal text-primary-text lg:text-[32px]">掲載商品登録</h1>

        <x-admin-item-form mode="create" />

        <a
            class="mt-6 inline-flex min-h-11 items-center justify-center px-4 text-base text-ink underline hover:no-underline focus-visible:no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
            href="{{ route('admin.items.index') }}"
        >一覧へ戻る</a>
    </div>
@endsection
