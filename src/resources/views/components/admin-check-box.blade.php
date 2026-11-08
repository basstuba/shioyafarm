@props(['id', 'checked', 'label', 'text'])

{{--
    管理画面の一覧の表のチェック（注文・予約の発送済み、お問い合わせの対応済みで共通）。<x-admin-check-row> の td の中に置く。
    チェックボックスは24px、押せる範囲（label）は高さ44px。SPは右にラベルの文字（text）を出し、PCは読み上げ専用にする。
    押した瞬間に、外側の <x-admin-instant-save> の save(id, checked) で保存し、失敗したらチェック（と行の地）を戻す。保存中の操作は無視して二重送信しない（disabled にするとフォーカスが外れるため、aria-busy を付ける）。
    - id：更新するレコードのID、checked：サーバーが描画した時点のチェックの状態
    - label：読み上げ用の名前（aria-label。チェックの状態で変わらない形。例：注文12の発送済み）
    - text：SPで表示するラベルの文字（例：発送済）
--}}
<label class="flex h-11 cursor-pointer items-center justify-end gap-2 text-base lg:justify-center">
    <span class="lg:sr-only">{{ $text }}</span>
    <input
        class="size-6 cursor-pointer accent-admin-gray aria-busy:cursor-wait focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
        type="checkbox"
        aria-label="{{ $label }}"
        @checked($checked)
        x-model="checked"
        x-bind:aria-busy="saving"
        x-on:change="if (saving) { checked = ! checked; return } saving = true; if (! await save({{ $id }}, checked)) { checked = ! checked } saving = false"
    >
</label>
