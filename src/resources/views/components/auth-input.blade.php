@props(['name', 'label', 'type' => 'text', 'placeholder' => '', 'autocomplete' => 'off', 'value' => null, 'srLabel' => false])

{{--
    認証ページ（ログイン・会員登録）の入力欄。ラベル・input・エラーメッセージをまとめて表示する。
    srLabel を true にすると、ラベルは画面に表示せずスクリーンリーダー用にだけ残す（姓・名のように1つの見出しの下に並べる場合）。
    class などの属性は外側の div に付く。
    エラーは $errors から name で拾う。パスワードは再表示しない（type が password のときは value を出さない）。
    old() の値は、配列など文字列にできないもの（改変した送信）のときは表示しない。
--}}
@php
    $oldValue = old($name, $value);
    $oldValue = is_scalar($oldValue) ? $oldValue : '';
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col gap-2 lg:gap-1.5']) }}>
    <label @class(['text-base font-medium', 'sr-only' => $srLabel]) for="{{ $name }}">{{ $label }}</label>
    <input
        class="h-11 w-full rounded-[10px] border border-primary-dark bg-white px-4 text-base placeholder:text-line-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark lg:h-10 lg:px-5"
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ $oldValue }}" @endif
        placeholder="{{ $placeholder }}"
        autocomplete="{{ $autocomplete }}"
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    />
    @error($name)
        <p class="text-sm text-danger" id="{{ $name }}-error">{{ $message }}</p>
    @enderror
</div>
