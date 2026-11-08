@props(['checked', 'active' => true])

{{--
    管理画面の一覧の表の、チェックのある行（tr）。注文・予約（発送済み）とお問い合わせ（対応済み）で共通。<x-admin-instant-save> の中の tbody に置く。
    PC（lg以上）は表の行、SPはカード（grid）。チェックしている行は地が admin-shipped（白と灰色の交互より優先）で、チェックの操作と同時に切り替わる。文字色は変えない。
    - checked：サーバーが描画した時点のチェックの状態
    - active：false のとき（取消済みの行）は、チェックできない行として Alpine の状態を持たず、地は未チェックと同じにする
    行の Alpine の状態は checked（チェックの状態）と saving（保存中）。<x-admin-check-box> がこれを使う。
    子のセル（td）は、スロットに置く。class などの属性は tr に付く（取消済みの文字色など）。
--}}
@php
    $on = $active && $checked;
@endphp
<tr
    {{ $attributes->class([
        'grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-1 rounded-[10px] border border-line px-4 py-3 lg:table-row lg:h-[45px] lg:rounded-none lg:border-0 lg:p-0',
        'bg-admin-shipped' => $on,
        'bg-white lg:even:bg-admin-bar' => ! $on,
    ]) }}
    @if ($active)
        x-data="{ checked: @js((bool) $checked), saving: false }"
        x-bind:class="{ 'bg-admin-shipped': checked, 'bg-white lg:even:bg-admin-bar': ! checked }"
    @endif
>
    {{ $slot }}
</tr>
