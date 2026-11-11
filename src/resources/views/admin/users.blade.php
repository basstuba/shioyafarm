@extends('layouts.admin.app')

@section('page-title', '会員一覧 | 管理画面 | Shioya Farm')

{{--
    管理画面の会員一覧（詳細モーダルは Livewire の AdminUserDetail）。レイアウトは layouts/admin/app（サイドメニューあり）。
    注文一覧・予約一覧（admin/partials/order-list）と同じ作りで、表の列が違うため別のビューにしている（検索フォームは <x-admin-search-form> などの部品、ページ送りは admin/partials/pager を共有）。
    - $users：会員のページネーター（退会済みを含む）
    - $conditions：検索欄に戻す検索条件（status・name）
    PC（lg以上）は最大幅960pxの表（列の幅は割合。最後の「メール送信」の列は右寄せ）、SPはカード形式（同じ table 要素を、グリッドでカードの形にする）。
    「メール送信」のリンク先は admin.mail.user（/admin/mail/user/{会員ID}。メール作成ページ。来た一覧の検索条件・ページに戻るため、リンクにクエリは付けない）。
--}}
@php
    $focusClass = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink';
    $statusValue = \App\Support\SearchCondition::restore($conditions, 'status');
    $cellClass = 'lg:table-cell lg:border-b lg:border-line lg:px-3 lg:align-middle';
@endphp

@section('content')
    <div class="flex flex-col items-center">
        <h1 class="text-center text-[28px] font-normal text-accent-hover lg:text-[32px]">会員一覧</h1>

        {{-- 検索（GET）。ラベルはPCでは読み上げ専用、SPでは表示する。入力の誤り・削除できなかった理由は、検索欄の近くに出す --}}
        <x-admin-search-form :action="route('admin.users.search')" label="会員の検索" :reset-href="route('admin.users.index')">
            <x-admin-search-field label="状態" for="status" width="lg:w-[200px]">
                {{-- 何も選ばないときの表示は、PC・SPとも「会員 / 退会済」 --}}
                <x-admin-search-select id="status" name="status" blank-label="会員 / 退会済" :blank-selected="$statusValue === ''">
                    <option value="{{ \App\Services\AdminUserCriteria::STATUS_MEMBER }}" @selected($statusValue === \App\Services\AdminUserCriteria::STATUS_MEMBER)>会員</option>
                    <option value="{{ \App\Services\AdminUserCriteria::STATUS_WITHDRAWN }}" @selected($statusValue === \App\Services\AdminUserCriteria::STATUS_WITHDRAWN)>退会済</option>
                </x-admin-search-select>
            </x-admin-search-field>
            <x-admin-search-field label="氏名" for="name" width="lg:w-[300px]">
                <x-admin-search-input id="name" name="name" type="text" maxlength="100" :value="\App\Support\SearchCondition::restore($conditions, 'name')" placeholder="氏名を入力してください" autocomplete="off" />
            </x-admin-search-field>
        </x-admin-search-form>

        <div class="mt-6 w-full max-w-[960px]">
            @if ($users->total() === 0)
                <p class="text-center text-base">該当する会員はいません。</p>
            @else
                <table class="block w-full lg:table lg:table-fixed">
                    <caption class="sr-only">会員一覧</caption>
                    <colgroup>
                        <col class="lg:w-[12%]">
                        <col class="lg:w-[32%]">
                        <col class="lg:w-[26%]">
                        <col class="lg:w-[12%]">
                        <col class="lg:w-[18%]">
                    </colgroup>
                    <thead class="hidden lg:table-header-group">
                        <tr class="h-[35px] bg-admin-head text-left text-white">
                            <th scope="col" class="px-3 text-base font-normal">状態</th>
                            <th scope="col" class="px-3 text-base font-normal">氏名</th>
                            <th scope="col" class="px-3 text-base font-normal">電話番号</th>
                            <th scope="col" class="px-3 text-base font-normal"><span class="sr-only">詳細</span></th>
                            <th scope="col" class="px-3 text-base font-normal lg:text-right"><span class="sr-only">メール送信</span></th>
                        </tr>
                    </thead>
                    <tbody class="flex flex-col gap-3 lg:table-row-group">
                        @foreach ($users as $user)
                            <tr class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-1 rounded-[10px] border border-line bg-white px-4 py-3 lg:table-row lg:h-[45px] lg:rounded-none lg:border-0 lg:p-0 lg:even:bg-admin-bar">
                                <td class="col-start-1 row-start-1 self-center text-base {{ $cellClass }}">
                                    <span class="mr-2 text-cancel-text lg:hidden">状態</span>{{ $user->trashed() ? '退会済' : '会員' }}
                                </td>
                                <td class="col-start-1 row-start-2 min-w-0 text-base break-words {{ $cellClass }}">
                                    <span class="mr-2 text-cancel-text lg:hidden">氏名</span>{{ $user->full_name }}
                                </td>
                                <td class="col-start-1 row-start-3 text-base {{ $cellClass }}">
                                    <span class="mr-2 text-cancel-text lg:hidden">電話番号</span>{{ $user->tell }}
                                </td>
                                <td class="col-start-2 row-start-1 {{ $cellClass }}">
                                    <x-admin-detail-button :label="$user->full_name.'の詳細を見る'" event="open-user-detail" :id="$user->id" />
                                </td>
                                <td class="col-start-2 row-span-2 row-start-2 self-start lg:text-right {{ $cellClass }}">
                                    <a
                                        class="inline-flex min-h-11 min-w-16 items-center justify-center text-base text-mail-link underline hover:no-underline focus-visible:no-underline {{ $focusClass }}"
                                        href="{{ route('admin.mail.user', ['user_id' => $user->id]) }}"
                                        aria-label="{{ $user->full_name }}にメール送信"
                                    >メール送信</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @include('admin.partials.pager', ['paginator' => $users])
    </div>

    <livewire:admin-user-detail />
@endsection
