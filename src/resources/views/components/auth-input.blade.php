@props(['name', 'label', 'type' => 'text', 'placeholder' => '', 'autocomplete' => 'off', 'value' => null, 'srLabel' => false, 'labelClass' => 'text-base font-medium', 'describedby' => null])

{{--
    認証ページ（ログイン・会員登録）の入力欄。ラベル・input・エラーメッセージをまとめて表示する。
    srLabel を true にすると、ラベルは画面に表示せずスクリーンリーダー用にだけ残す（姓・名のように1つの見出しの下に並べる場合）。
    labelClass でラベルのクラスを変えられる（既定は text-base font-medium）。
    スロット labelExtra を渡すと、ラベルの右に並べて出す（「任意」や補足の文言）。スロット hint を渡すと、ラベルの下の行（入力欄の上）に補足の文言を出す。補足の要素の id は describedby にスペース区切りで渡し、input の aria-describedby につなぐ。
    class などの属性は外側の div に付く。
    エラーは $errors から name で拾う。パスワードは再表示しない（type が password のときは value を出さない）。
    old() の値は、配列など文字列にできないもの（改変した送信）のときは表示しない。
--}}
@php
    $oldValue = old($name, $value);
    $oldValue = is_scalar($oldValue) ? $oldValue : '';
    $describedIds = trim(($describedby ?? '').' '.($errors->has($name) ? $name.'-error' : ''));
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col gap-2 lg:gap-1.5']) }}>
    @if (isset($labelExtra) && $labelExtra->isNotEmpty())
        <div class="flex flex-wrap items-baseline gap-x-[15px] gap-y-1">
            <label @class([$labelClass, 'sr-only' => $srLabel]) for="{{ $name }}">{{ $label }}</label>
            {{ $labelExtra }}
        </div>
    @else
        <label @class([$labelClass, 'sr-only' => $srLabel]) for="{{ $name }}">{{ $label }}</label>
    @endif
    @if (isset($hint) && $hint->isNotEmpty())
        {{ $hint }}
    @endif
    <input
        class="h-11 w-full rounded-[10px] border border-primary-dark bg-white px-4 text-base placeholder:text-line-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark lg:h-10 lg:px-5"
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ $oldValue }}" @endif
        placeholder="{{ $placeholder }}"
        autocomplete="{{ $autocomplete }}"
        @if ($errors->has($name)) aria-invalid="true" @endif
        @if ($describedIds !== '') aria-describedby="{{ $describedIds }}" @endif
    />
    @error($name)
        <p class="text-sm text-danger" id="{{ $name }}-error">{{ $message }}</p>
    @enderror
</div>
