@extends('layouts.admin.app')
@use('App\Support\ItemLabel')

@section('page-title', '掲載商品一覧 | 管理画面 | Shioya Farm')

{{--
    管理画面の掲載商品一覧（詳細モーダルは Livewire の AdminItemDetail）。レイアウトは layouts/admin/app（サイドメニューあり）。
    会員一覧（admin/users）などと同じ作りの表。商品は少ないため、検索欄・ページ送りはなく、全件を表示する。
    - $items：掲載中の商品（削除済みを除く。商品名ごとにまとめ、その中は重さの軽い順）
    PC（lg以上）は最大幅960pxの表（列の幅は割合）、SPはカード形式（同じ table 要素を、グリッドでカードの形にする）。
    「修正」のリンク先は /admin/items/edit/{商品ID}（修正ページは次のブランチ feat/admin-item-form で作る。それまでは404。作ったら route() にする）。
    「削除」は確認のモーダルを開き（1行目は「{商品名} {重さ}kg を削除します。よろしいですか？」。商品名は Alpine の x-text で入れるため、HTMLの記号は文字のまま出る）、確認の「削除する」で DELETE /admin/items/delete へ送る（ページを移る）。確認の状態は Alpine（Livewire 同梱）が持ち、確認の中は x-trap でフォーカスを閉じ込める。
    削除に成功したら一覧に戻り、表の上に緑の文字（flash の list_status）。削除済み・存在しない商品は、赤字（$errors）で案内する。
--}}
@php
    $focusClass = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink';
    $cellClass = 'lg:table-cell lg:border-b lg:border-line lg:px-3 lg:align-middle';
    // 「修正」「削除」。SPは「詳細」と同じ枠線つきのボタン（高さ44px）、PC（lg以上）は下線つきの文字
    $actionClass = 'inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-[10px] border border-line-strong bg-white px-2 text-base hover:no-underline focus-visible:no-underline lg:w-auto lg:min-w-16 lg:border-0 lg:bg-transparent lg:px-0 lg:underline '.$focusClass;
@endphp

@section('content')
    <div class="flex flex-col items-center">
        <h1 class="text-center text-[28px] font-normal text-accent-hover lg:text-[32px]">掲載商品一覧</h1>

        {{-- 削除に成功した案内（緑の文字。背景・枠なし）と、削除できなかった理由（赤字） --}}
        @if (session('list_status'))
            <p class="mt-6 w-full text-center text-base text-accent-hover" role="status">{{ session('list_status') }}</p>
        @endif
        @if ($errors->any())
            <ul class="mt-6 w-full text-center text-base text-danger" role="alert">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif

        <div
            class="mt-6 w-full max-w-[960px]"
            x-data="{ confirming: false, deleting: false, deleteId: null, deleteLabel: '' }"
            x-on:keydown.escape.window="confirming = false"
        >
            @if ($items->isEmpty())
                <p class="text-center text-base">掲載中の商品はありません。</p>
            @else
                <table class="block w-full lg:table lg:table-fixed">
                    <caption class="sr-only">掲載商品一覧</caption>
                    <colgroup>
                        <col class="lg:w-[26%]">
                        <col class="lg:w-[18%]">
                        <col class="lg:w-[21%]">
                        <col class="lg:w-[11%]">
                        <col class="lg:w-[11%]">
                        <col class="lg:w-[13%]">
                    </colgroup>
                    <thead class="hidden lg:table-header-group">
                        <tr class="h-[35px] bg-admin-head text-left text-white">
                            <th scope="col" class="px-3 text-base font-normal">商品名</th>
                            <th scope="col" class="px-3 text-base font-normal">商品重量（kg）</th>
                            <th scope="col" class="px-3 text-base font-normal">商品金額（円・税込）</th>
                            <th scope="col" class="px-3 text-base font-normal"><span class="sr-only">詳細</span></th>
                            <th scope="col" class="px-3 text-base font-normal"><span class="sr-only">修正</span></th>
                            <th scope="col" class="px-3 text-base font-normal"><span class="sr-only">削除</span></th>
                        </tr>
                    </thead>
                    <tbody class="flex flex-col gap-3 lg:table-row-group">
                        @foreach ($items as $item)
                            @php($label = ItemLabel::of($item->name, $item->weight))
                            <tr class="grid grid-cols-3 gap-x-2 gap-y-1 rounded-[10px] border border-line bg-white px-4 py-3 lg:table-row lg:h-[45px] lg:rounded-none lg:border-0 lg:p-0 lg:even:bg-admin-bar">
                                <td class="col-span-3 min-w-0 text-base break-words {{ $cellClass }}">
                                    <span class="mr-2 text-cancel-text lg:hidden">商品名</span>{{ $item->name }}
                                </td>
                                <td class="col-span-3 text-base {{ $cellClass }}">
                                    <span class="mr-2 text-cancel-text lg:hidden">商品重量（kg）</span>{{ $item->weight }}
                                </td>
                                <td class="col-span-3 text-base {{ $cellClass }}">
                                    <span class="mr-2 text-cancel-text lg:hidden">商品金額（円・税込）</span>{{ number_format($item->price) }}
                                </td>
                                <td class="mt-2 {{ $cellClass }} lg:mt-0">
                                    <x-admin-detail-button class="w-full lg:w-auto" :label="$label.'の詳細を見る'" event="open-item-detail" :id="$item->id" />
                                </td>
                                <td class="mt-2 {{ $cellClass }} lg:mt-0">
                                    <a
                                        class="{{ $actionClass }} text-mail-link"
                                        href="{{ url('/admin/items/edit/'.$item->id) }}"
                                        aria-label="{{ $label }}を修正"
                                    >修正</a>
                                </td>
                                <td class="mt-2 {{ $cellClass }} lg:mt-0">
                                    <button
                                        type="button"
                                        class="{{ $actionClass }} text-delete"
                                        aria-haspopup="dialog"
                                        aria-label="{{ $label }}を削除"
                                        x-on:click="deleting = false; deleteId = {{ $item->id }}; deleteLabel = @js($label); confirming = true"
                                    >削除</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- 削除の確認。一覧の上に重ねる（会員一覧の削除の確認と同じ形）。最初のフォーカスは autofocus の「キャンセル」、閉じたら開く前にフォーカスのあった「削除」へ戻る。送信中の「削除する」は押せなくするが、確認を開くときに解除する --}}
                <div
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/35 p-4"
                    x-show="confirming"
                    x-cloak
                    x-trap="confirming"
                    x-on:click.self="confirming = false"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="admin-item-delete-title"
                    aria-describedby="admin-item-delete-note"
                >
                    <form class="relative max-h-full w-full max-w-[400px] overflow-y-auto rounded-[30px] bg-white px-6 py-8 text-center text-ink shadow-panel" action="{{ route('admin.items.delete') }}" method="POST" x-on:submit="deleting = true">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="id" x-bind:value="deleteId">
                        <p id="admin-item-delete-title" class="text-base font-bold break-words" x-text="deleteLabel + ' を削除します。よろしいですか？'"></p>
                        <p id="admin-item-delete-note" class="mt-3 text-base">公開ページとカートから消えます。過去の注文・予約の記録は残ります。</p>
                        <div class="mt-6 flex flex-wrap justify-center gap-4">
                            <x-admin-small-button type="submit" x-bind:disabled="deleting" class="disabled:cursor-wait disabled:opacity-40">削除する</x-admin-small-button>
                            <x-admin-small-button variant="cancel" autofocus x-on:click="confirming = false">キャンセル</x-admin-small-button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <livewire:admin-item-detail />
@endsection
