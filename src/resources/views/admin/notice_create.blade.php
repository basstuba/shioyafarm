@extends('layouts.admin.app')

@section('page-title', 'お知らせ作成 | 管理画面 | Shioya Farm')

{{--
    管理画面のお知らせ作成ページ（Figma 172:2）。レイアウトは layouts/admin/app（サイドメニューあり。「お知らせ作成」が太字）。
    フォームは作成・修正で共通の <x-admin-notice-form>（App\View\Components\AdminNoticeForm）。タイトルは primary-text（#8f5e1c。橙 #f0a13a は文字だと読みにくいため。掲載商品登録と同じ）。
--}}
@section('content')
    <div class="flex flex-col items-center">
        <h1 class="text-center text-[28px] font-normal text-primary-text lg:text-[32px]">お知らせ作成</h1>

        <x-admin-notice-form mode="create" />

        <a
            class="mt-6 inline-flex min-h-11 items-center justify-center px-4 text-base text-ink underline hover:no-underline focus-visible:no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
            href="{{ route('admin.notices.index') }}"
        >一覧へ戻る</a>
    </div>
@endsection
