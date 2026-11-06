{{--
    管理画面の注文一覧・予約一覧の詳細モーダルの中身（幅400px。SPは左右16pxの余白で横いっぱい）
    - $detail：App\Services\OrderDetail
    - $subject：「注文」か「予約」（ラベル・案内文・見出しの語）
    - $titleId：見出しのid（aria-labelledby 用）
    各項目は <x-admin-detail-item>（ラベル 16px・太字 → 値 14px → 薄い灰色の線。会員一覧の詳細と共通）。
    外枠（背景・パネル・「閉じる」・Esc）は <x-admin-detail-modal>（会員一覧の詳細モーダルと共通）。内容が長いときはモーダルの中をスクロールする。
--}}
<x-admin-detail-modal :title-id="$titleId" :title="$subject.'の詳細'">
    <dl class="flex flex-col gap-7">
        <x-admin-detail-item :label="$subject.'管理番号'">{{ $detail->orderId }}</x-admin-detail-item>
        <x-admin-detail-item :label="$subject.'日'">{{ $detail->orderedAt?->format('Y/m/d') }}</x-admin-detail-item>
        <x-admin-detail-item :label="$subject.'者'">{{ $detail->ordererName }}</x-admin-detail-item>

        {{-- 商品内訳：1商品1行（商品名と重量・単価・数量・小計）。各行の下に線 --}}
        <x-admin-detail-item label="商品内訳" :bordered="false">
            <ul class="text-sm">
                @foreach ($detail->lines as $line)
                    <li class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-2 border-b border-line-modal pb-1.5">
                        <span class="break-words">{{ $line['name'] }}</span>
                        <span class="text-right">{{ number_format($line['subtotal']) }}円</span>
                        <span class="col-span-2 flex gap-4">
                            <span>{{ number_format($line['price']) }}円(税込)</span>
                            <span>{{ $line['quantity'] }}個</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-admin-detail-item>

        <x-admin-detail-item label="送料">{{ number_format($detail->shippingFee) }}円</x-admin-detail-item>
        @if ($detail->codFee !== null)
            <x-admin-detail-item label="代引手数料">{{ number_format($detail->codFee) }}円</x-admin-detail-item>
        @endif
        <x-admin-detail-item label="合計金額">{{ number_format($detail->total) }}円</x-admin-detail-item>

        <x-admin-detail-item label="配送先氏名">{{ $detail->deliveryName }}</x-admin-detail-item>
        <x-admin-detail-item label="配送先住所">{{ $detail->deliveryAddress }}</x-admin-detail-item>
        <x-admin-detail-item label="配送先電話番号">{{ $detail->deliveryTell }}</x-admin-detail-item>

        <x-admin-detail-item label="支払い方法">{{ $detail->paymentLabel }}</x-admin-detail-item>

        {{-- 銀行振込のときだけ。注文・予約後の入金はシステムで追跡していないため、入金済み・未入金とは書かない。口座情報は出さない --}}
        @if ($detail->bankTransfer !== null)
            <x-admin-detail-item label="お支払いの状況">
                @if ($detail->bankTransfer->paid)
                    {{ $subject }}時にお客様の残高からお支払い済みです
                @else
                    振込先をご案内済みです。入金はStripeの管理画面でご確認ください
                @endif
            </x-admin-detail-item>
        @endif
    </dl>
</x-admin-detail-modal>
