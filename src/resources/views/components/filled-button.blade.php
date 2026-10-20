{{--
    橙の塗りつぶしのボタン（購入する・予約する）。outline-button と同じ大きさ（lg）で、仮のリンクなど href を渡して使う。
    - href を渡すとリンク（<a>）、渡さないとボタン（<button>。type は既定で submit）として出す
--}}
@props(['href' => null, 'type' => 'submit'])
@php
    $classes = 'inline-flex h-11 w-50 items-center justify-center rounded-[30px] border-2 border-action bg-action font-maru text-xl text-ink no-underline transition-colors duration-200 hover:bg-primary-hover focus-visible:bg-primary-hover focus-visible:outline-none md:h-10';
@endphp
@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes, 'cursor-pointer']) }}>{{ $slot }}</button>
@endif
