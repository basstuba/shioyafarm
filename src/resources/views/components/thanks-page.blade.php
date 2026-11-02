@props(['colorClass', 'nowrap' => 'md'])

{{--
    購入完了・予約完了ページで共通の中身。写真を画面いっぱいに敷き、白いカードなしで文字を直接置く。
    SPでも文字が写真の空の部分に乗るよう上寄せにする。layouts/thanks の content セクションの中に置く。
    - color-class：見出しの文字色（購入 text-leaf／予約 text-reserve-text）
    - nowrap：見出しを1行（nowrap）にし始める画面幅。md（既定。購入は約696pxで収まる）／lg（予約は1行だと840pxあるため）
    - スロット heading：見出しの中身（画面幅ごとの改行の <br> も含めて渡す）
    - スロット（既定）：本文
--}}
<div class="relative flex min-h-screen flex-col items-center overflow-hidden px-4 pt-30 pb-16 text-center md:pt-[209px]">
    <img class="absolute inset-0 size-full object-cover object-top" src="{{ asset('storage/image/tool/thanks-page-photo.webp') }}" alt="" aria-hidden="true">

    <h1 @class([
        'relative text-xl leading-[1.6] text-shadow-thanks md:text-2xl',
        $colorClass,
        'md:whitespace-nowrap' => $nowrap === 'md',
        'lg:whitespace-nowrap' => $nowrap === 'lg',
    ])>{{ $heading }}</h1>
    <p class="relative mt-3.5 w-full max-w-[477px] font-sans text-base leading-[30px] text-left text-pretty md:w-max md:max-w-full">
        {{ $slot }}
    </p>
    <a class="relative mt-10 inline-flex min-h-11 items-center text-base underline underline-offset-4 hover:opacity-80 md:mt-[62px]" href="{{ route('top.index') }}">ホームに戻る</a>
</div>
