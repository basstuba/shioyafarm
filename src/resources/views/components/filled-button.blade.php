{{--
    橙の塗りつぶしのボタン（購入する・予約する）。
    - href を渡すとリンク（<a>）、渡さないとボタン（<button>。type は既定で submit）として出す
    - size は lg（既定。200×40。SPは高さ44）／xl（200×60。商品詳細）／2xl（240×60で文字24px。購入ページの「注文を確定する」）／sm（カートの小計。PCは200×34、SPは全幅）
    - variant は action（既定。購入など）／reserve（予約）
    - ホバー・フォーカスで色が変わる（枠線も背景とそろえる）。xl・2xl・sm は影も付く
    - disabled のボタンは薄くして押せなくし、ホバーでも変わらない
--}}
@props(['href' => null, 'type' => 'submit', 'size' => 'lg', 'variant' => 'action'])
@php
    $isReserve = $variant === 'reserve';
    $hasShadow = in_array($size, ['xl', '2xl', 'sm'], true);
    $classes = [
        'inline-flex items-center justify-center border-2 font-maru text-ink no-underline',
        'transition-[background-color,border-color,box-shadow] duration-200 focus-visible:outline-none',
        $isReserve
            ? 'border-reserve bg-reserve hover:border-reserve-hover hover:bg-reserve-hover focus-visible:border-reserve-hover focus-visible:bg-reserve-hover'
            : 'border-action bg-action hover:border-primary-hover hover:bg-primary-hover focus-visible:border-primary-hover focus-visible:bg-primary-hover',
        $hasShadow ? 'hover:shadow-button focus-visible:shadow-button' : '',
        match ($size) {
            'xl' => 'h-15 w-50 rounded-[40px] text-base',
            '2xl' => 'h-15 w-60 rounded-[40px] text-2xl',
            'sm' => 'h-11 w-full rounded-[40px] text-base md:h-[34px] md:w-50',
            default => 'h-11 w-50 rounded-[30px] text-xl md:h-10',
        },
    ];
    $disabledClasses = [
        'cursor-pointer disabled:cursor-not-allowed disabled:opacity-60',
        $isReserve ? 'disabled:hover:border-reserve disabled:hover:bg-reserve' : 'disabled:hover:border-action disabled:hover:bg-action',
        $hasShadow ? 'disabled:hover:shadow-none' : '',
    ];
@endphp
@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([...$classes, ...$disabledClasses]) }}>{{ $slot }}</button>
@endif
