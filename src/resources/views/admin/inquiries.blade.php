@extends('layouts.admin.app')

@section('page-title', 'お問い合わせ一覧 | 管理画面 | Shioya Farm')

{{--
    管理画面のお問い合わせ一覧（詳細モーダルは Livewire の AdminInquiryDetail）。レイアウトは layouts/admin/app（サイドメニューあり）。
    注文一覧・予約一覧（admin/partials/order-list）、会員一覧（admin/users）と同じ作りで、表の列が違うため別のビューにしている（検索フォームは <x-admin-search-form> などの部品、ページ送りは admin/partials/pager を共有）。
    - $inquiries：お問い合わせのページネーター
    - $conditions：検索欄に戻す検索条件（addressed・date）
    PC（lg以上）は最大幅960pxの表（列の幅は割合。最後の「返信」の列は右寄せ）、SPはカード形式（同じ table 要素を、グリッドでカードの形にする）。
    対応済みのチェックは、注文一覧の発送済みのチェックと同じ形（押した瞬間に Alpine から保存し、保存できなかったときはチェックと行の色を戻して案内を出す。対応済みの行の地は admin-shipped）。お客様にメールは送らない。
    「返信」のリンク先 /admin/mail/inquiry/{お問い合わせID} は、メール機能のブランチで route() に置き換える（それまでは404）。
--}}
@php
    $focusClass = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink';
    // 検索欄に戻す値。配列など文字列でない入力は戻さない（TypeError にしない）。不正な日付は type=date の欄に戻さない（コンソール警告になる）
    $restore = fn (string $key): string => \App\Support\OldInput::scalar($key, $conditions[$key]);
    $dateValue = $errors->has('date') ? '' : $restore('date');
    $addressedValue = $restore('addressed');
    $cellClass = 'lg:table-cell lg:border-b lg:border-line lg:px-3 lg:align-middle';
@endphp

@section('content')
    <div class="flex flex-col items-center">
        <h1 class="text-center text-[28px] font-normal text-accent-hover lg:text-[32px]">お問い合わせ一覧</h1>

        {{-- 検索（GET）。ラベルはPCでは読み上げ専用、SPでは表示する。入力の誤りは、検索欄の近くに出す --}}
        <x-admin-search-form :action="route('admin.inquiries.search')" label="お問い合わせの検索" :reset-href="route('admin.inquiries.index')">
            <x-admin-search-field label="対応状況" for="addressed" width="lg:w-[200px]">
                {{-- 何も選ばないときの表示は、PC・SPとも「対応済 / 非対応」 --}}
                <x-admin-search-select id="addressed" name="addressed" blank-label="対応済 / 非対応" :blank-selected="$addressedValue === ''">
                    <option value="{{ \App\Services\AdminInquiryCriteria::ADDRESSED_YES }}" @selected($addressedValue === \App\Services\AdminInquiryCriteria::ADDRESSED_YES)>対応済</option>
                    <option value="{{ \App\Services\AdminInquiryCriteria::ADDRESSED_NO }}" @selected($addressedValue === \App\Services\AdminInquiryCriteria::ADDRESSED_NO)>非対応</option>
                </x-admin-search-select>
            </x-admin-search-field>
            <x-admin-search-field label="問い合わせ日" for="date" width="lg:w-[190px]">
                <x-admin-search-input id="date" name="date" type="date" :value="$dateValue" />
            </x-admin-search-field>
        </x-admin-search-form>

        {{-- 対応済みの保存（注文一覧の発送済みと共通の <x-admin-instant-save>）。保存に失敗したときの案内もここに出る --}}
        <x-admin-instant-save
            class="mt-6 w-full max-w-[960px]"
            :url="route('admin.inquiries.update')"
            field="addressed"
            not-found-message="お問い合わせが見つかりません。ページを再読み込みしてください。"
        >
            @if ($inquiries->total() === 0)
                <p class="text-center text-base">該当するお問い合わせはありません。</p>
            @else
                <table class="block w-full lg:table lg:table-fixed">
                    <caption class="sr-only">お問い合わせ一覧</caption>
                    <colgroup>
                        <col class="lg:w-[12%]">
                        <col class="lg:w-[24%]">
                        <col class="lg:w-[34%]">
                        <col class="lg:w-[14%]">
                        <col class="lg:w-[16%]">
                    </colgroup>
                    <thead class="hidden lg:table-header-group">
                        <tr class="h-[35px] bg-admin-head text-left text-white">
                            <th scope="col" class="px-3 text-center text-base font-normal">対応済</th>
                            <th scope="col" class="px-3 text-base font-normal">問い合わせ日</th>
                            <th scope="col" class="px-3 text-base font-normal">氏名</th>
                            <th scope="col" class="px-3 text-base font-normal"><span class="sr-only">詳細</span></th>
                            <th scope="col" class="px-3 text-base font-normal lg:text-right"><span class="sr-only">返信</span></th>
                        </tr>
                    </thead>
                    <tbody class="flex flex-col gap-3 lg:table-row-group">
                        @foreach ($inquiries as $inquiry)
                            @php
                                $date = $inquiry->created_at?->format('Y/m/d');
                            @endphp
                            {{-- 対応済みの行は地が admin-shipped（白と灰色の交互より優先）。文字色は変えない --}}
                            <x-admin-check-row :checked="$inquiry->addressed">
                                <td class="col-start-2 row-start-1 {{ $cellClass }} lg:text-center">
                                    <x-admin-check-box :id="$inquiry->id" :checked="$inquiry->addressed" :label="$inquiry->full_name.'（'.$date.'）のお問い合わせの対応済み'" text="対応済" />
                                </td>
                                <td class="col-start-1 row-start-1 self-center text-base {{ $cellClass }}">
                                    <span class="mr-2 text-cancel-text lg:hidden">問い合わせ日</span>{{ $date }}
                                </td>
                                <td class="col-span-2 col-start-1 row-start-2 min-w-0 text-base break-words {{ $cellClass }}">
                                    <span class="mr-2 text-cancel-text lg:hidden">氏名</span>{{ $inquiry->full_name }}
                                </td>
                                <td class="col-start-1 row-start-3 self-start {{ $cellClass }}">
                                    <x-admin-detail-button :label="$inquiry->full_name.'のお問い合わせの詳細を見る'" event="open-inquiry-detail" :id="$inquiry->id" />
                                </td>
                                <td class="col-start-2 row-start-3 self-start lg:text-right {{ $cellClass }}">
                                    <a
                                        class="inline-flex min-h-11 min-w-16 items-center justify-center text-base text-mail-link underline hover:no-underline focus-visible:no-underline {{ $focusClass }}"
                                        href="{{ url('/admin/mail/inquiry/'.$inquiry->id) }}"
                                        aria-label="{{ $inquiry->full_name }}に返信"
                                    >返信</a>
                                </td>
                            </x-admin-check-row>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-admin-instant-save>

        @include('admin.partials.pager', ['paginator' => $inquiries])
    </div>

    <livewire:admin-inquiry-detail />
@endsection
