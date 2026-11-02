{{--
    マイページの退会手続きで使う、小さな角丸のボタン（130×30、角丸10px、文字14px）。ホバー・フォーカスで地の色が変わり、影が付く。
    - variant は danger（既定。赤い地に白文字の「退会する」）／cancel（灰色の地に濃い灰色の文字の「キャンセル」）
    - SPは高さ44px（PCはFigmaの30px）
    - type は既定で button（フォームの送信に使うときは type="submit" を渡す）
--}}
@props(['variant' => 'danger', 'type' => 'button'])
<button type="{{ $type }}" {{ $attributes->class([
    'inline-flex h-11 w-[130px] cursor-pointer items-center justify-center rounded-[10px] font-maru text-sm font-medium md:h-[30px]',
    'transition-[background-color,box-shadow] duration-200 hover:shadow-button focus-visible:shadow-button focus-visible:outline-none',
    $variant === 'cancel'
        ? 'bg-cancel text-cancel-text hover:bg-cancel-hover focus-visible:bg-cancel-hover'
        : 'bg-delete text-white hover:bg-withdraw-hover focus-visible:bg-withdraw-hover',
]) }}>{{ $slot }}</button>
