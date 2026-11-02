{{--
    購入ページ・予約ページで共通の金額のカード（小計・送料・代引手数料・合計）。PCは幅200で列の右端に置く。
    - $summary：金額の内訳（subtotal・shippingFee・codFee・total）。代引手数料は 0 円より大きいときだけ行を出す
    - $topClass：PCでの上の余白（商品の行の下からの距離。購入 md:mt-[17px]／予約 md:mt-[19px]）
--}}
<dl class="mt-4 rounded-[10px] bg-white px-[15px] py-[14px] text-base leading-6 shadow-button {{ $topClass }} md:ml-auto md:w-50">
    <div class="flex justify-between px-[9px] text-muted"><dt>小計</dt><dd>{{ number_format($summary->subtotal) }}円</dd></div>
    <div class="mt-[14px] flex justify-between px-[9px] text-muted"><dt>送料</dt><dd>{{ number_format($summary->shippingFee) }}円</dd></div>
    @if ($summary->codFee > 0)
        <div class="mt-[14px] flex justify-between px-[9px] text-muted"><dt>代引手数料</dt><dd>{{ number_format($summary->codFee) }}円</dd></div>
    @endif
    <div class="mt-4 flex justify-between border-t border-muted px-[9px] pt-[19px] text-subtotal">
        <dt>合計</dt><dd>{{ number_format($summary->total) }}円</dd>
    </div>
</dl>
