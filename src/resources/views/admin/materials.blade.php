@extends('layouts.admin.app')

@section('page-title', '資材一覧 | 管理画面 | Shioya Farm')

{{--
    管理画面の資材一覧（発送用の化粧箱などの在庫数と置き場所。Figma にない追加の画面で、掲載商品一覧（admin/items）の作りに合わせた）。レイアウトは layouts/admin/app（サイドメニューあり）。
    - $materials：資材の全件（登録した順＝古い順。検索・ページ送りはない）
    表の列は 資材名・在庫数・置き場所・基準値・「修正」・「削除」。PC（lg以上）は最大幅960pxの表（列の幅は割合。在庫数・基準値は右寄せの3桁区切り）、SPはカード形式（同じ table 要素を、グリッドでカードの形にする）。
    在庫数が基準値を下回った資材（Material::isLow()。トップの件数と同じ判定）は、在庫数を赤字の太字にし、資材名の下に赤字で「残りわずか」を出す。
    「資材を追加」と各行の「修正」は、同じ形のモーダル（部品 <x-admin-material-form>）を開く。「削除」は確認のモーダル（部品 <x-admin-delete-confirm>）を開き、確認の「削除する」で DELETE /admin/materials/delete へ送る（ページを移る）。
    モーダルの状態は、ページの x-data（Alpine。Livewire 同梱）が持つ。初期値は App\Support\MaterialFormState（入力の誤り・419 で戻ってきたときだけ、入力を残したまま開く）。
    追加・修正・削除に成功した案内（緑の文字。flash の list_status）と、見つからない・削除できなかった案内（赤字。errors の material・delete）は、表の上に出す。入力の項目ごとのエラーはモーダルの中に出す。
--}}
@php
    $form = \App\Support\MaterialFormState::fromOld($errors);
    $blank = \App\Support\MaterialFormState::blank();
    $focusClass = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink';
    $cellClass = 'lg:table-cell lg:border-b lg:border-line lg:px-3 lg:align-middle';
    // 在庫数・基準値の数字の列（PCは右寄せ。次の列と離す）
    $numberCellClass = 'lg:text-right lg:tabular-nums lg:pr-8';
    // 「修正」「削除」。SPは枠線つきのボタン（高さ44px）、PC（lg以上）は下線つきの文字
    $actionClass = 'inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-[10px] border border-line-strong bg-white px-2 text-base hover:no-underline focus-visible:no-underline lg:w-auto lg:min-w-16 lg:border-0 lg:bg-transparent lg:px-0 lg:underline '.$focusClass;
@endphp

@section('content')
    <div class="flex flex-col items-center">
        <h1 class="text-center text-[28px] font-normal text-accent-hover lg:text-[32px]">資材一覧</h1>

        {{-- 追加・修正・削除に成功した案内（緑の文字。背景・枠なし）と、見つからない・削除できなかった理由（赤字） --}}
        @if (session('list_status'))
            <p class="mt-6 w-full text-center text-base text-accent-hover" role="status">{{ session('list_status') }}</p>
        @endif
        @if ($errors->hasAny(['material', 'delete']))
            <ul class="mt-6 w-full text-center text-base text-danger" role="alert">
                @foreach ([...$errors->get('material'), ...$errors->get('delete')] as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif

        <div
            class="mt-6 w-full max-w-[960px]"
            x-data="{
                ...@js($form),
                blank: @js($blank),
                sending: false,
                confirming: false,
                deleting: false,
                deleteId: null,
                deleteLabel: '',
                openCreate() {
                    Object.assign(this, this.blank, { showErrors: false, sending: false, formOpen: true });
                    this.$nextTick(() => this.focusField('name'));
                },
                openEdit(material) {
                    Object.assign(this, { mode: 'edit', ...material, showErrors: false, sending: false, formOpen: true });
                    this.$nextTick(() => this.focusField('stock'));
                },
                focusField(field) {
                    this.$refs[field].focus();
                    if (field === 'stock' && this.mode === 'edit') this.$refs[field].select();
                },
                focusInitial() {
                    const firstInvalid = this.showErrors ? ['name', 'stock', 'location', 'threshold'].find((field) => this.invalid[field]) : null;
                    this.focusField(firstInvalid ?? (this.mode === 'edit' ? 'stock' : 'name'));
                },
            }"
            x-init="if (formOpen) $nextTick(() => focusInitial())"
            x-on:keydown.escape.window="formOpen = false; confirming = false"
            x-on:pageshow.window="sending = false; deleting = false"
        >
            <div class="flex justify-end">
                <button
                    type="button"
                    class="inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-[30px] bg-primary px-8 text-base text-ink transition-[background-color,box-shadow] duration-200 hover:bg-primary-dark hover:shadow-button focus-visible:bg-primary-dark focus-visible:shadow-button lg:w-auto {{ $focusClass }}"
                    aria-haspopup="dialog"
                    x-on:click="openCreate()"
                >資材を追加</button>
            </div>

            @if ($materials->isEmpty())
                <p class="mt-6 text-center text-base">登録されている資材はありません。</p>
            @else
                <table class="mt-6 block w-full lg:table lg:table-fixed">
                    <caption class="sr-only">資材一覧</caption>
                    <colgroup>
                        <col class="lg:w-[28%]">
                        <col class="lg:w-[14%]">
                        <col class="lg:w-[28%]">
                        <col class="lg:w-[14%]">
                        <col class="lg:w-[8%]">
                        <col class="lg:w-[8%]">
                    </colgroup>
                    <thead class="hidden lg:table-header-group">
                        <tr class="h-[35px] bg-admin-head text-left text-white">
                            <th scope="col" class="px-3 text-base font-normal">資材名</th>
                            <th scope="col" class="px-3 text-base font-normal {{ $numberCellClass }}">在庫数</th>
                            <th scope="col" class="px-3 text-base font-normal">置き場所</th>
                            <th scope="col" class="px-3 text-base font-normal {{ $numberCellClass }}">基準値</th>
                            <th scope="col" class="px-3 text-base font-normal"><span class="sr-only">修正</span></th>
                            <th scope="col" class="px-3 text-base font-normal"><span class="sr-only">削除</span></th>
                        </tr>
                    </thead>
                    <tbody class="flex flex-col gap-3 lg:table-row-group">
                        @foreach ($materials as $material)
                            <tr class="grid grid-cols-2 gap-x-2 gap-y-1 rounded-[10px] border border-line bg-white px-4 py-3 lg:table-row lg:h-[45px] lg:rounded-none lg:border-0 lg:p-0 lg:even:bg-admin-bar">
                                <td class="col-span-2 min-w-0 text-base break-words {{ $cellClass }}">
                                    <span class="mr-2 text-muted lg:hidden">資材名</span>{{ $material->name }}
                                    @if ($material->isLow())
                                        <span class="block text-danger">残りわずか</span>
                                    @endif
                                </td>
                                <td class="col-span-2 text-base {{ $cellClass }} {{ $numberCellClass }}">
                                    <span class="mr-2 text-muted lg:hidden">在庫数</span><span @class(['font-bold text-danger' => $material->isLow()])>{{ number_format($material->stock) }}</span>
                                </td>
                                <td class="col-span-2 min-w-0 text-base break-words {{ $cellClass }}">
                                    <span class="mr-2 text-muted lg:hidden">置き場所</span>{{ $material->location }}
                                </td>
                                <td class="col-span-2 text-base {{ $cellClass }} {{ $numberCellClass }}">
                                    <span class="mr-2 text-muted lg:hidden">基準値</span>{{ number_format($material->threshold) }}
                                </td>
                                <td class="mt-2 {{ $cellClass }} lg:mt-0">
                                    <button
                                        type="button"
                                        class="{{ $actionClass }} text-mail-link"
                                        aria-haspopup="dialog"
                                        aria-label="{{ $material->name }}を修正"
                                        x-on:click="openEdit(@js(['id' => (string) $material->id, 'name' => $material->name, 'stock' => (string) $material->stock, 'location' => $material->location, 'threshold' => (string) $material->threshold]))"
                                    >修正</button>
                                </td>
                                <td class="mt-2 {{ $cellClass }} lg:mt-0">
                                    <button
                                        type="button"
                                        class="{{ $actionClass }} text-delete"
                                        aria-haspopup="dialog"
                                        aria-label="{{ $material->name }}を削除"
                                        x-on:click="deleting = false; deleteId = {{ $material->id }}; deleteLabel = @js($material->name); confirming = true"
                                    >削除</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- 削除の確認（部品 <x-admin-delete-confirm>。状態は上の x-data を使う。「削除」を押すと開く） --}}
                <x-admin-delete-confirm :action="route('admin.materials.delete')" id-prefix="admin-material-delete" note="在庫数と置き場所の記録も消えます。" title-before="「" title-after="」を削除します。よろしいですか？" />
            @endif

            {{-- 追加・修正のモーダル（状態は上の x-data を使う） --}}
            <x-admin-material-form :form="$form" />
        </div>
    </div>
@endsection
