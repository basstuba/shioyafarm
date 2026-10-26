{{--
    購入履歴・予約履歴の詳細モーダル（マイページ用。見た目は仮置き）
    - $detail：App\Services\OrderDetail
    - $dateLabel：日付の見出し（購入日／予約日）
    - $title：モーダルの見出し（画面には出さず、スクリーンリーダー用）
    - $titleId：見出しのid（同じページに購入・予約のモーダルが並ぶため、コンポーネントごとに変える）
    ×・背景・Esc で閉じる。
--}}
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

    <div class="relative max-h-full w-full max-w-[640px] overflow-y-auto rounded-[20px] bg-white px-5 pt-14 pb-8 shadow-card md:px-10">
        <button type="button" class="absolute top-2 right-2 inline-flex size-11 cursor-pointer items-center justify-center rounded-full text-ink hover:bg-surface focus-visible:bg-surface focus-visible:outline-none" x-on:click="$wire.showDetail = false" aria-label="閉じる">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                <path d="M6 6l12 12M18 6L6 18" />
            </svg>
        </button>

        <h2 id="{{ $titleId }}" class="sr-only">{{ $title }}</h2>

        <dl class="flex flex-col gap-4 text-base md:text-lg">
            <div class="flex flex-wrap justify-between gap-x-4">
                <dt class="font-maru">{{ $dateLabel }}</dt>
                <dd>{{ $detail->orderedAt?->format('Y/m/d') }}</dd>
            </div>

            <div class="border-t border-line pt-4">
                <dt class="mb-2 font-maru">商品内訳</dt>
                <dd>
                    <ul class="flex flex-col gap-3">
                        @foreach ($detail->lines as $line)
                            <li class="flex flex-col gap-0.5">
                                <span class="break-words">{{ $line['name'] }}</span>
                                <span class="flex flex-wrap justify-between gap-x-4 text-sm md:text-base">
                                    <span>{{ number_format($line['price']) }}円(税込) × {{ $line['quantity'] }}個</span>
                                    <span>{{ number_format($line['subtotal']) }}円</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </dd>
            </div>

            <div class="flex flex-wrap justify-between gap-x-4 border-t border-line pt-4">
                <dt class="font-maru">送料</dt>
                <dd>{{ number_format($detail->shippingFee) }}円</dd>
            </div>
            @if ($detail->codFee !== null)
                <div class="flex flex-wrap justify-between gap-x-4">
                    <dt class="font-maru">代引手数料</dt>
                    <dd>{{ number_format($detail->codFee) }}円</dd>
                </div>
            @endif
            <div class="flex flex-wrap justify-between gap-x-4 text-xl md:text-2xl">
                <dt class="font-maru">合計金額</dt>
                <dd>{{ number_format($detail->total) }}円</dd>
            </div>

            <div class="border-t border-line pt-4">
                <dt class="mb-2 font-maru">お届け先</dt>
                <dd class="flex flex-col gap-0.5 break-words">
                    <span>{{ $detail->deliveryName }} 様</span>
                    <span>{{ $detail->deliveryAddress }}</span>
                    <span>電話番号：{{ $detail->deliveryTell }}</span>
                </dd>
            </div>

            <div class="flex flex-wrap justify-between gap-x-4 border-t border-line pt-4">
                <dt class="font-maru">お支払い方法</dt>
                <dd>{{ $detail->paymentLabel }}</dd>
            </div>

            @if ($detail->bankTransfer !== null)
                <div class="rounded-[10px] bg-cream-100 p-4">
                    <dt class="mb-2 font-maru">お振込先</dt>
                    <dd class="flex flex-col gap-0.5 break-words">
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
    </div>
</div>
