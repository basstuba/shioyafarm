@props(['label', 'for', 'width'])

{{--
    管理画面の一覧の検索欄の1項目（ラベル＋入力欄）。ラベルはPCでは読み上げ専用（sr-only）、SPでは表示する。
    - label：ラベルの文言、for：入力欄のid、width：PC（lg以上）での幅のクラス（例：lg:w-[200px]）
    入力欄は <x-admin-search-input>／<x-admin-search-select> をスロットに置く。
--}}
<div class="{{ $width }}">
    <label class="mb-1 block text-base text-cancel-text lg:sr-only" for="{{ $for }}">{{ $label }}</label>
    {{ $slot }}
</div>
