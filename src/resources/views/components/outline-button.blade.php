{{--
    白地に橙の枠線のボタン。ホバー・フォーカス時は黄色の背景に濃い茶色の文字になる（枠線は透明にして大きさを変えない）。
    - href を渡すとリンク（<a>）、渡さないとボタン（<button>。type は既定で submit）として出す
    - size は sm（既定。会員登録・詳しく見るなど）／lg（フォームの送信ボタンなど）
--}}
@props(['href' => null, 'type' => 'submit', 'size' => 'sm'])
@php
    $classes = [
        'inline-flex items-center justify-center rounded-[30px] border-2 border-action bg-white font-maru text-action no-underline',
        'transition-[background-color,color,border-color,box-shadow] duration-200',
        'hover:border-transparent hover:bg-action-hover hover:text-action-hover-text hover:shadow-button',
        'focus-visible:border-transparent focus-visible:bg-action-hover focus-visible:text-action-hover-text focus-visible:shadow-button focus-visible:outline-none',
        match ($size) {
            'lg' => 'h-11 w-50 text-xl md:h-10',
            default => 'h-11 w-40 text-base md:h-[34px] md:w-[130px]',
        },
    ];
@endphp
@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([...$classes, 'cursor-pointer disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:border-action disabled:hover:bg-white disabled:hover:text-action disabled:hover:shadow-none']) }}>{{ $slot }}</button>
@endif
