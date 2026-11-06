@props(['variant' => 'danger', 'type' => 'button'])

{{--
    管理画面の小さな角丸のボタン（130×30、角丸10px、文字は本文と同じ font-sans・Medium）。会員の物理削除の「削除する」「キャンセル」で使う。
    マイページの <x-small-button>（font-maru 固定）とは別の部品。
    - variant は danger（既定。赤い地に白文字）／cancel（灰色の地に濃い灰色の文字）。ホバー・フォーカスで地の色が変わり、影が付く
    - SPは高さ44px・文字16px、PC（lg以上。管理画面のレイアウトが切り替わる幅）はFigmaの高さ30px・文字14px
    - type は既定で button（フォームの送信に使うときは type="submit" を渡す）
--}}
<button type="{{ $type }}" {{ $attributes->class([
    'inline-flex h-11 w-[130px] cursor-pointer items-center justify-center rounded-[10px] text-base font-medium lg:h-[30px] lg:text-sm',
    'transition-[background-color,box-shadow] duration-200 hover:shadow-button focus-visible:shadow-button focus-visible:outline-none',
    $variant === 'cancel'
        ? 'bg-cancel text-cancel-text hover:bg-cancel-hover focus-visible:bg-cancel-hover'
        : 'bg-delete text-white hover:bg-withdraw-hover focus-visible:bg-withdraw-hover',
]) }}>{{ $slot }}</button>
