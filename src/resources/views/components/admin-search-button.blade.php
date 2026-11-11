{{--
    管理画面の一覧（注文・予約・会員・お問い合わせ）の検索欄まわりのボタン。href があれば <a>、なければ type="submit" の <button>。
    props（variant・href・disabled）の説明とクラスの組み立ては App\View\Components\AdminSearchButton にある。
--}}
@if ($href !== null)
    <a {{ $attributes->class($classes()) }} href="{{ $href }}">{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit'])->class($classes()) }} @disabled($disabled)>{{ $slot }}</button>
@endif
