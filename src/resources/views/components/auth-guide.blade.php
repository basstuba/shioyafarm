{{--
    認証画面（layouts/auth）の `@section('guide')` に置く、パネルの案内文と枠線のリンクボタン。
    - href：リンク先、label：ボタンの文言
    - スロット：案内文（SPで改行したい位置に <br class="lg:hidden"> を入れる）
    - そのほかの属性（class など）は案内文の <p> に付く
--}}
@props(['href', 'label'])
<p {{ $attributes->class(['leading-[1.6]']) }}>{{ $slot }}</p>
<a class="inline-flex h-11 items-center justify-center rounded-full border border-primary-dark bg-cream-100 px-10 text-base text-primary-dark no-underline transition-[background-color,color,box-shadow] duration-200 hover:bg-primary-dark hover:text-white hover:shadow-button focus-visible:bg-primary-dark focus-visible:text-white focus-visible:shadow-button focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark lg:h-[34px] lg:w-[130px] lg:px-0" href="{{ $href }}">{{ $label }}</a>
