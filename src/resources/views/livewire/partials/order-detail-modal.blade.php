{{--
    購入履歴・予約履歴の詳細モーダル（マイページ用。幅400px）
    - $detail：App\Services\OrderDetail
    - $dateLabel：日付の見出し（注文日／予約日）
    - $title：モーダルの見出し（画面には出さず、スクリーンリーダー用）
    - $titleId：見出しのid（同じページに購入・予約のモーダルが並ぶため、コンポーネントごとに変える）
    各項目は「ラベル（16px）→ 値（12px）→ 薄い灰色の線」の形。下の「閉じる」・背景・Esc で閉じる。
--}}
@php
    $itemClass = 'border-b border-line-modal pb-1';
    $labelClass = 'font-maru text-base leading-5 font-medium text-ink';
    $valueClass = 'mt-2 text-xs break-words';
@endphp
<div
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    x-data
    x-show="$wire.showDetail"
    x-cloak
    x-on:keydown.escape.window="$wire.showDetail = false"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
>
    <div class="absolute inset-0 bg-black/35" x-on:click="$wire.showDetail = false" aria-hidden="true"></div>

    <div class="relative max-h-full w-full max-w-[400px] overflow-y-auto rounded-[30px] bg-white px-6 pt-6 pb-3 text-ink shadow-panel md:px-10 md:pt-9">
        <h2 id="{{ $titleId }}" class="sr-only">{{ $title }}</h2>

        <dl class="flex flex-col gap-7">
            <div class="{{ $itemClass }}">
                <dt class="{{ $labelClass }}">{{ $dateLabel }}</dt>
                <dd class="{{ $valueClass }}">{{ $detail->orderedAt?->format('Y/m/d') }}</dd>
            </div>

            {{-- 商品内訳：1商品1行（商品名・単価・数量・小計）。各行の下に線 --}}
            <div>
                <dt class="{{ $labelClass }}">商品内訳</dt>
                <dd class="mt-2">
                    <ul class="text-xs">
                        @foreach ($detail->lines as $line)
                            <li class="grid grid-cols-[minmax(0,1fr)_88px_36px_auto] gap-x-2 border-b border-line-modal pb-1.5">
                                <span class="break-words">{{ $line['name'] }}</span>
                                <span>{{ number_format($line['price']) }}円(税込)</span>
                                <span>{{ $line['quantity'] }}個</span>
                                <span class="text-right">{{ number_format($line['subtotal']) }}円</span>
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
                <dt class="{{ $labelClass }}">お届け先氏名</dt>
                <dd class="{{ $valueClass }}">{{ $detail->deliveryName }}</dd>
            </div>
            <div class="{{ $itemClass }}">
                <dt class="{{ $labelClass }}">お届け先住所</dt>
                <dd class="{{ $valueClass }}">{{ $detail->deliveryAddress }}</dd>
            </div>
            <div class="{{ $itemClass }}">
                <dt class="{{ $labelClass }}">お届け先電話番号</dt>
                <dd class="{{ $valueClass }}">{{ $detail->deliveryTell }}</dd>
            </div>

            <div class="{{ $itemClass }}">
                <dt class="{{ $labelClass }}">お支払い方法</dt>
                <dd class="{{ $valueClass }}">{{ $detail->paymentLabel }}</dd>
            </div>

            @if ($detail->bankTransfer !== null)
                <div class="{{ $itemClass }}">
                    <dt class="{{ $labelClass }}">お振込先</dt>
                    <dd class="{{ $valueClass }} flex flex-col gap-0.5">
                        @if ($detail->bankTransfer->paid)
                            <span>お客様の残高からお支払いが完了しています</span>
                        @else
                            <span>銀行名：{{ $detail->bankTransfer->bankName }}</span>
                            <span>支店名：{{ $detail->bankTransfer->branchName }}</span>
                            <span>口座種別：{{ $detail->bankTransfer->accountType }}</span>
                            <span>口座番号：{{ $detail->bankTransfer->accountNumber }}</span>
                            <span>口座名義：{{ $detail->bankTransfer->accountHolderName }}</span>
                            <span>お振込金額：{{ number_format($detail->bankTransfer->amount) }}円</span>
                        @endif
                    </dd>
                </div>
            @endif
        </dl>

        {{-- 文字だけの「閉じる」（住所追加モーダルと同じ） --}}
        <div class="mt-4 flex justify-center">
            <button type="button" class="inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center px-4 font-maru text-base hover:underline focus-visible:underline focus-visible:outline-none" x-on:click="$wire.showDetail = false">閉じる</button>
        </div>
    </div>
</div>
