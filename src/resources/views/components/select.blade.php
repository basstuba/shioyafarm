@props(['error' => false, 'breakpoint' => 'md', 'wrapperClass' => ''])

{{--
    選択欄（select）と、右端の下向きの三角。購入ページ・住所追加モーダル・会員登録ページで使う。
    - 選択肢（<option>）はスロットに渡す。id・name・wire:model・aria-* などの属性は select に付く
    - 未選択（value="" の option を選んでいる）のときの文字色は placeholder と同じ。そのため select に required を付けて使う
    - error：true で枠線を赤にする（エラー表示は呼び出し側で、aria-describedby でつなぐ）
    - breakpoint：高さ40px・左余白に切り替える画面幅。md（既定。購入ページ）／lg（会員登録ページ）
    - wrapper-class：select と三角を包む div のクラス（幅など。既定は包みの幅いっぱい）
--}}
<div @class(['relative', $wrapperClass])>
    <select {{ $attributes->class([
        'h-11 w-full cursor-pointer appearance-none rounded-[10px] border bg-white pr-10 text-base text-ink invalid:text-line-strong',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark',
        $error ? 'border-danger' : 'border-primary-dark',
        $breakpoint === 'lg' ? 'pl-4 lg:h-10 lg:pl-5' : 'pl-[18px] md:h-10',
    ]) }}>{{ $slot }}</select>
    <svg class="pointer-events-none absolute top-1/2 right-4 h-3 w-4 -translate-y-1/2 text-primary-dark" viewBox="0 0 16 12" fill="currentColor" aria-hidden="true">
        <path d="M0 0h16L8 12z" />
    </svg>
</div>
