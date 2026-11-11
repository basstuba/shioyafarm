@extends('layouts.admin.app')

@section('page-title', 'お知らせ一覧 | 管理画面 | Shioya Farm')

{{--
    管理画面のお知らせ一覧（Figma 164:25。詳細モーダルは Livewire の AdminNoticeDetail、Figma 166:2）。レイアウトは layouts/admin/app（サイドメニューあり）。
    お問い合わせ一覧（admin/inquiries）と同じ作りの表（検索フォームは <x-admin-search-form> などの部品、ページ送りは admin/partials/pager を共有）に、掲載商品一覧（admin/items）と同じ「修正」「削除」の列を足した。
    - $notices：お知らせのページネーター（作成日の新しい順、同じ日時は新しく作った順。1ページ10件）
    - $conditions：検索欄に戻す検索条件（date）
    PC（lg以上）は最大幅960pxの表（列の幅は割合。Figma の表は幅600pxだが、列を2つ足すため、ほかの一覧に合わせた）、SPはカード形式（同じ table 要素を、グリッドでカードの形にする）。
    「修正」のリンク先は route('admin.notices.edit', お知らせID)。「削除」は確認のモーダルを開き（1行目は「「{タイトル}」を削除します。よろしいですか？」。タイトルは Alpine の x-text で入れるため、HTMLの記号は文字のまま出る）、確認の「削除する」で DELETE /admin/notices/delete へ送る（ページを移る）。確認の状態は Alpine（Livewire 同梱）が持ち、確認の中は x-trap でフォーカスを閉じ込める。
    作成・修正・削除に成功した案内（緑の文字。flash の list_status）と、見つからない・日付の誤りなどの案内（赤字。$errors）は、<x-admin-search-form> が検索欄の下に出す。
--}}
@php
    $dateValue = \App\Support\SearchCondition::restoreDate($conditions, $errors->has('date'));
    $focusClass = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink';
    $cellClass = 'lg:table-cell lg:border-b lg:border-line lg:px-3 lg:align-middle';
    // 「修正」「削除」。SPは「詳細」と同じ枠線つきのボタン（高さ44px）、PC（lg以上）は下線つきの文字
    $actionClass = 'inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-[10px] border border-line-strong bg-white px-2 text-base hover:no-underline focus-visible:no-underline lg:w-auto lg:min-w-16 lg:border-0 lg:bg-transparent lg:px-0 lg:underline '.$focusClass;
@endphp

@section('content')
    <div class="flex flex-col items-center">
        <h1 class="text-center text-[28px] font-normal text-accent-hover lg:text-[32px]">お知らせ一覧</h1>

        {{-- 検索（GET）。ラベルはPCでは読み上げ専用、SPでは表示する。入力の誤りは、検索欄の近くに出す --}}
        <x-admin-search-form :action="route('admin.notices.search')" label="お知らせの検索" :reset-href="route('admin.notices.index')">
            <x-admin-search-field label="作成日" for="date" width="lg:w-[190px]">
                <x-admin-search-input id="date" name="date" type="date" :value="$dateValue" />
            </x-admin-search-field>
        </x-admin-search-form>

        <div
            class="mt-6 w-full max-w-[960px]"
            x-data="{ confirming: false, deleting: false, deleteId: null, deleteLabel: '' }"
            x-on:keydown.escape.window="confirming = false"
        >
            @if ($notices->total() === 0)
                <p class="text-center text-base">該当するお知らせはありません。</p>
            @else
                <table class="block w-full lg:table lg:table-fixed">
                    <caption class="sr-only">お知らせ一覧</caption>
                    <colgroup>
                        <col class="lg:w-[18%]">
                        <col class="lg:w-[46%]">
                        <col class="lg:w-[12%]">
                        <col class="lg:w-[12%]">
                        <col class="lg:w-[12%]">
                    </colgroup>
                    <thead class="hidden lg:table-header-group">
                        <tr class="h-[35px] bg-admin-head text-left text-white">
                            <th scope="col" class="px-3 text-base font-normal">作成日</th>
                            <th scope="col" class="px-3 text-base font-normal">タイトル</th>
                            <th scope="col" class="px-3 text-base font-normal"><span class="sr-only">詳細</span></th>
                            <th scope="col" class="px-3 text-base font-normal"><span class="sr-only">修正</span></th>
                            <th scope="col" class="px-3 text-base font-normal"><span class="sr-only">削除</span></th>
                        </tr>
                    </thead>
                    <tbody class="flex flex-col gap-3 lg:table-row-group">
                        @foreach ($notices as $notice)
                            <tr class="grid grid-cols-3 gap-x-2 gap-y-1 rounded-[10px] border border-line bg-white px-4 py-3 lg:table-row lg:h-[45px] lg:rounded-none lg:border-0 lg:p-0 lg:even:bg-admin-bar">
                                <td class="col-span-3 text-base {{ $cellClass }}">
                                    <span class="mr-2 text-cancel-text lg:hidden">作成日</span>{{ $notice->created_at?->format('Y/m/d') }}
                                </td>
                                <td class="col-span-3 min-w-0 text-base break-words {{ $cellClass }}">
                                    <span class="mr-2 text-cancel-text lg:hidden">タイトル</span>{{ $notice->title }}
                                </td>
                                <td class="mt-2 {{ $cellClass }} lg:mt-0">
                                    <x-admin-detail-button class="w-full lg:w-auto" :label="$notice->title.'の詳細を見る'" event="open-notice-detail" :id="$notice->id" />
                                </td>
                                <td class="mt-2 {{ $cellClass }} lg:mt-0">
                                    <a
                                        class="{{ $actionClass }} text-mail-link"
                                        href="{{ route('admin.notices.edit', $notice->id) }}"
                                        aria-label="{{ $notice->title }}を修正"
                                    >修正</a>
                                </td>
                                <td class="mt-2 {{ $cellClass }} lg:mt-0">
                                    <button
                                        type="button"
                                        class="{{ $actionClass }} text-delete"
                                        aria-haspopup="dialog"
                                        aria-label="{{ $notice->title }}を削除"
                                        x-on:click="deleting = false; deleteId = {{ $notice->id }}; deleteLabel = @js($notice->title); confirming = true"
                                    >削除</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- 削除の確認（部品 <x-admin-delete-confirm>。状態は上の x-data を使う。「削除」を押すと開く） --}}
                <x-admin-delete-confirm :action="route('admin.notices.delete')" id-prefix="admin-notice-delete" note="公開サイトのトップからも消えます。" title-before="「" title-after="」を削除します。よろしいですか？" />
            @endif
        </div>

        @include('admin.partials.pager', ['paginator' => $notices])
    </div>

    <livewire:admin-notice-detail />
@endsection
