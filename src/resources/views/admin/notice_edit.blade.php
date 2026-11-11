@extends('layouts.admin.app')

@section('page-title', 'お知らせ修正 | 管理画面 | Shioya Farm')

{{--
    管理画面のお知らせ修正ページ（設計書・Figma にない追加。作成ページ（Figma 172:2）の形を流用する）。レイアウトは layouts/admin/app（サイドメニューあり。「お知らせ一覧」が太字）。
    - $notice：修正するお知らせ（存在しないお知らせは、コントローラーが一覧へ戻すため、ここには来ない）
    フォームは作成・修正で共通の <x-admin-notice-form>（App\View\Components\AdminNoticeForm）。タイトルと「修正する」ボタンは青（mail-link。掲載商品修正と同じ）。
--}}
@section('content')
    <div class="flex flex-col items-center">
        <h1 class="text-center text-[28px] font-normal text-mail-link lg:text-[32px]">お知らせ修正</h1>

        <x-admin-notice-form mode="edit" :notice="$notice" />

        <a
            class="mt-6 inline-flex min-h-11 items-center justify-center px-4 text-base text-ink underline hover:no-underline focus-visible:no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
            href="{{ route('admin.notices.index') }}"
        >一覧へ戻る</a>
    </div>
@endsection
