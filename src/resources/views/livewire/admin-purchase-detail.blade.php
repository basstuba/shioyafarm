{{--
    管理画面の注文一覧の詳細モーダル（幅400px。SPは左右16pxの余白で横いっぱい）
    - $detail：App\Services\OrderDetail（開いていないときは null）
    一覧の「詳細」ボタンが open-purchase-detail を送ると開く。各項目は「ラベル（16px・太字）→ 値（14px）→ 薄い灰色の線」の形。
    下の文字だけの「閉じる」・背景・Esc で閉じる（×は付けない）。内容が長いときはモーダルの中をスクロールする。
--}}
<div>
    @if ($detail !== null)
        @php
            $itemClass = 'border-b border-line-modal pb-1';
            $labelClass = 'text-base leading-5 font-bold text-ink';
            $valueClass = 'mt-2 text-sm break-words';
        @endphp
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-data
            x-show="$wire.showDetail"
            x-cloak
            x-on:keydown.escape.window="$wire.showDetail = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="admin-purchase-detail-title"
        >
            <div class="absolute inset-0 bg-black/35" x-on:click="$wire.showDetail = false" aria-hidden="true"></div>

            <div class="relative max-h-full w-full max-w-[400px] overflow-y-auto rounded-[30px] bg-white px-6 pt-6 pb-3 text-ink shadow-panel lg:px-10 lg:pt-9">
                <h2 id="admin-purchase-detail-title" class="sr-only">注文の詳細</h2>

                <dl class="flex flex-col gap-7">
                    <div class="{{ $itemClass }}">
                        <dt class="{{ $labelClass }}">注文管理番号</dt>
                        <dd class="{{ $valueClass }}">{{ $detail->orderId }}</dd>
                    </div>
                    <div class="{{ $itemClass }}">
                        <dt class="{{ $labelClass }}">注文日</dt>
                        <dd class="{{ $valueClass }}">{{ $detail->orderedAt?->format('Y/m/d') }}</dd>
                    </div>
                    <div class="{{ $itemClass }}">
                        <dt class="{{ $labelClass }}">注文者</dt>
                        <dd class="{{ $valueClass }}">{{ $detail->ordererName }}</dd>
                    </div>

                    {{-- 商品内訳：1商品1行（商品名と重量・単価・数量・小計）。各行の下に線 --}}
                    <div>
                        <dt class="{{ $labelClass }}">商品内訳</dt>
                        <dd class="mt-2">
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
                        </dd>
                    </div>

                    <div class="{{ $itemClass }}">
                        <dt class="{{ $labelClass }}">送料</dt>
                        <dd class="{{ $valueClass }}">{{ number_format($detail->shippingFee) }}円</dd>
                    </div>
                    @if ($detail->codFee !== null)
                        <div class="{{ $itemClass }}">
                            <dt class="{{ $labelClass }}">代引手数料</dt>
                            <dd class="{{ $valueClass }}">{{ number_format($detail->codFee) }}円</dd>
                        </div>
                    @endif
                    <div class="{{ $itemClass }}">
                        <dt class="{{ $labelClass }}">合計金額</dt>
                        <dd class="{{ $valueClass }}">{{ number_format($detail->total) }}円</dd>
                    </div>

                    <div class="{{ $itemClass }}">
                        <dt class="{{ $labelClass }}">配送先氏名</dt>
                        <dd class="{{ $valueClass }}">{{ $detail->deliveryName }}</dd>
                    </div>
                    <div class="{{ $itemClass }}">
                        <dt class="{{ $labelClass }}">配送先住所</dt>
                        <dd class="{{ $valueClass }}">{{ $detail->deliveryAddress }}</dd>
                    </div>
                    <div class="{{ $itemClass }}">
                        <dt class="{{ $labelClass }}">配送先電話番号</dt>
                        <dd class="{{ $valueClass }}">{{ $detail->deliveryTell }}</dd>
                    </div>

                    <div class="{{ $itemClass }}">
                        <dt class="{{ $labelClass }}">支払い方法</dt>
                        <dd class="{{ $valueClass }}">{{ $detail->paymentLabel }}</dd>
                    </div>

                    {{-- 銀行振込のときだけ。注文後の入金はシステムで追跡していないため、入金済み・未入金とは書かない。口座情報は出さない --}}
                    @if ($detail->bankTransfer !== null)
                        <div class="{{ $itemClass }}">
                            <dt class="{{ $labelClass }}">お支払いの状況</dt>
                            <dd class="{{ $valueClass }}">
                                @if ($detail->bankTransfer->paid)
                                    注文時にお客様の残高からお支払い済みです
                                @else
                                    振込先をご案内済みです。入金はStripeの管理画面でご確認ください
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>

                {{-- 文字だけの「閉じる」 --}}
                <div class="mt-4 flex justify-center">
                    <button type="button" class="inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center px-4 text-base hover:underline focus-visible:underline focus-visible:outline-none" x-on:click="$wire.showDetail = false">閉じる</button>
                </div>
            </div>
        </div>
    @endif
</div>
