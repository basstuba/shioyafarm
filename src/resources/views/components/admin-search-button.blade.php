@props(['variant' => 'search', 'href' => null, 'disabled' => false])

{{--
    管理画面の一覧（注文・予約・会員）の検索欄まわりのボタン。href があれば <a>、なければ type="submit" の <button>（type は上書きできる。disabled が true なら押せない）。
    - variant：search（緑の地の「検索」）／reset（灰色の地の「リセット」）／export（濃い灰色の地の「エクスポート」。幅200px、押せないときは薄くする）
    SPは高さ48px・全幅、PC（lg以上）は高さ40px・文字14px・幅は variant ごと。
--}}
@php
    $classes = [
        'inline-flex h-12 w-full cursor-pointer items-center justify-center rounded-[10px] text-base transition-[background-color,box-shadow] duration-200 hover:shadow-button focus-visible:shadow-button lg:h-10 lg:text-sm',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink',
        match ($variant) {
            'reset' => 'bg-line-strong text-ink hover:bg-admin-gray hover:text-white focus-visible:bg-admin-gray focus-visible:text-white lg:w-[100px]',
            'export' => 'bg-admin-gray text-white hover:bg-admin-dark focus-visible:bg-admin-dark disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none lg:w-[200px]',
            default => 'bg-accent text-ink hover:bg-accent-hover hover:text-white focus-visible:bg-accent-hover focus-visible:text-white lg:w-[100px]',
        },
    ];
@endphp
@if ($href !== null)
    <a {{ $attributes->class($classes) }} href="{{ $href }}">{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit'])->class($classes) }} @disabled($disabled)>{{ $slot }}</button>
@endif
