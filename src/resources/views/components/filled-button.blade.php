{{--
    橙の塗りつぶしのボタン（購入する・予約する）。href があれば <a>、なければ <button>。
    props（href・type・size・variant）の説明とクラスの組み立ては App\View\Components\FilledButton にある。
--}}
@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes()) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([...$classes(), ...$disabledClasses()]) }}>{{ $slot }}</button>
@endif
