@extends('layouts.admin.app')

@section('page-title', '掲載商品修正 | 管理画面 | Shioya Farm')

{{--
    管理画面の掲載商品修正ページ（Figma 180:21）。レイアウトは layouts/admin/app（サイドメニューあり。「掲載商品一覧」が太字）。
    - $item：修正する商品（削除済み・存在しない商品は、コントローラーが一覧へ戻すため、ここには来ない）
    フォームは登録・修正で共通の <x-admin-item-form>（App\View\Components\AdminItemForm）。タイトルと「修正する」ボタンは青（mail-link）。
--}}
@section('content')
    <div class="flex flex-col items-center">
        <h1 class="text-center text-[28px] font-normal text-mail-link lg:text-[32px]">掲載商品修正</h1>

        <x-admin-item-form mode="edit" :item="$item" />

        <a
            class="mt-6 inline-flex min-h-11 items-center justify-center px-4 text-base text-ink underline hover:no-underline focus-visible:no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
            href="{{ route('admin.items.index') }}"
        >一覧へ戻る</a>
    </div>
@endsection
