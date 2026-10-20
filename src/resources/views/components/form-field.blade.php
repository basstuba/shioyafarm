@props(['name', 'label' => null, 'ariaLabel' => null, 'type' => 'text', 'placeholder' => '', 'autocomplete' => null, 'textarea' => false, 'inputClass' => ''])

{{--
    公開ページのフォームの入力欄（ラベル・input または textarea・エラーメッセージをまとめたもの）。
    - label を渡すと上にラベルを出す。姓・名のように別の見出しの下に並べる場合は label の代わりに ariaLabel を渡す
    - textarea を true にすると複数行の入力欄にする
    - class などの属性は外側の div に付き、inputClass は入力欄そのものに足される
    - old() の値は、配列など文字列にできないもの（改変した送信）のときは表示しない
--}}
@php
    $oldValue = old($name);
    $oldValue = is_scalar($oldValue) ? (string) $oldValue : '';
    $base = 'w-full rounded-[10px] border bg-white px-4 font-maru text-base text-ink placeholder:text-line-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
    $fieldClass = [
        $base,
        $errors->has($name) ? 'border-danger' : 'border-primary-dark',
        $textarea ? 'h-50 resize-y py-2.5' : 'h-11 md:h-10',
        $inputClass,
    ];
@endphp
<div {{ $attributes }}>
    @if ($label !== null)
        <label class="mb-2 block font-maru text-lg leading-[30px] text-ink md:mb-1.5 md:text-xl" for="{{ $name }}">{{ $label }}</label>
    @endif
    @if ($textarea)
        <textarea
            @class($fieldClass)
            id="{{ $name }}"
            name="{{ $name }}"
            placeholder="{{ $placeholder }}"
            @if ($ariaLabel !== null) aria-label="{{ $ariaLabel }}" @endif
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        >{{ $oldValue }}</textarea>
    @else
        <input
            @class($fieldClass)
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $oldValue }}"
            placeholder="{{ $placeholder }}"
            @if ($ariaLabel !== null) aria-label="{{ $ariaLabel }}" @endif
            @if ($autocomplete !== null) autocomplete="{{ $autocomplete }}" @endif
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        >
    @endif
    @error($name)
        <p class="mt-1.5 text-sm text-danger" id="{{ $name }}-error">{{ $message }}</p>
    @enderror
</div>
