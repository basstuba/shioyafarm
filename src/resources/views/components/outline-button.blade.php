{{--
    白地に橙の枠線のボタン。href があれば <a>、なければ <button>。
    props（href・type・size・livewire）の説明とクラスの組み立ては App\View\Components\OutlineButton にある。
--}}
@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes()) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $buttonAttributes($attributes)->class([...$classes(), ...$disabledClasses()]) }}>{{ $slot }}</button>
@endif
