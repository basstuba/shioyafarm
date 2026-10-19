{{-- 白地に橙の枠線のリンクボタン。ホバー・フォーカス時は黄色の背景に濃い茶色の文字になる（枠線は透明にして大きさを変えない） --}}
@props(['href' => '#'])
<a
    href="{{ $href }}"
    {{ $attributes->class([
        'inline-flex h-11 w-40 items-center justify-center rounded-[30px] border-2 border-action bg-white font-maru text-base text-action no-underline',
        'transition-[background-color,color,border-color,box-shadow] duration-200',
        'hover:border-transparent hover:bg-action-hover hover:text-action-hover-text hover:shadow-button',
        'focus-visible:border-transparent focus-visible:bg-action-hover focus-visible:text-action-hover-text focus-visible:shadow-button focus-visible:outline-none',
        'md:h-[34px] md:w-[130px]',
    ]) }}
>{{ $slot }}</a>
