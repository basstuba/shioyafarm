@props(['label', 'bordered' => true])

{{--
    管理画面の詳細モーダルの1項目（ラベル 16px・太字 → 値 14px → 薄い灰色の線）。注文・予約・会員の詳細で共通。<dl> の中に置く。
    - label：ラベル、値はスロット
    - bordered：false のとき線を付けず、値の文字の大きさも決めない（値の中で自分で決める商品内訳）
--}}
<div @class(['border-b border-line-modal pb-1' => $bordered])>
    <dt class="text-base leading-5 font-bold text-ink">{{ $label }}</dt>
    <dd @class(['mt-2', 'text-sm break-words' => $bordered])>{{ $slot }}</dd>
</div>
