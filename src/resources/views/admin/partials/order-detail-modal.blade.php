{{--
    管理画面の注文一覧・予約一覧の詳細モーダルの中身（幅400px。SPは左右16pxの余白で横いっぱい）
    - $detail：App\Services\OrderDetail
    - $listType：一覧の種類（App\Enums\AdminListType）。ラベル・案内文・見出しの語（注文・予約）と、取消のルートはここから決まる
    - $titleId：見出しのid（aria-labelledby 用）
    各項目は <x-admin-detail-item>（ラベル 16px・太字 → 値 14px → 薄い灰色の線。会員一覧の詳細と共通）。
    外枠（背景・パネル・「閉じる」・Esc）は <x-admin-detail-modal>（会員一覧の詳細モーダルと共通）。内容が長いときはモーダルの中をスクロールする。
    未発送で未取消のときだけ、赤い「取り消す」を出す。「取り消す」は確認のモーダルを開き、確認の「取り消す」で POST（取消のルート）へ送る（ページを移る。会員一覧の物理削除と同じ形）。
    詳細は開くたびに DB から読み直すため、一覧で発送済みにチェックしたあとに開いたときも出ない。取消済みは、先頭に「取消日」を出し、銀行振込の支払い状況は出さない（OrderDetail が振込先を持たない）。
    確認の状態（confirming）と、送信中（canceling）は Alpine が持つ。確認の中は x-trap（Livewire 同梱）でフォーカスを閉じ込め、最初のフォーカスは autofocus の「戻る」、閉じたら開く前にフォーカスのあった詳細の「取り消す」へ戻る。確認の Esc・背景・「戻る」は確認だけを閉じ、詳細は残す。
    送信中の「取り消す」は押せなくするが、確認を開くときに解除する（押せないまま固まらないように）。
    確認の返金の案内は支払方法で変わる（カードは「返金はStripeの管理画面で行ってください。」、銀行振込は「入金済みの場合、〜」、代金引換は出さない）。
--}}
@php
    $subject = $listType->subject();
    $cancelable = $detail->canRequestCancel();
    $refundNote = $detail->paymentMethod?->refundNote();
@endphp
<x-admin-detail-modal
    :title-id="$titleId"
    :title="$subject.'の詳細'"
    state="{ confirming: false, canceling: false }"
    close="confirming = false; $wire.showDetail = false"
    escape="if (confirming) { confirming = false } else { $wire.showDetail = false }"
>
    <dl class="flex flex-col gap-7">
        @if ($detail->isCanceled())
            <x-admin-detail-item label="取消日">{{ $detail->canceledAt?->format('Y/m/d') }}</x-admin-detail-item>
        @endif
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

    @if ($cancelable)
        <x-slot:actions>
            <div class="mt-6 flex justify-center">
                <x-admin-small-button aria-haspopup="dialog" x-on:click="canceling = false; confirming = true">取り消す</x-admin-small-button>
            </div>
        </x-slot:actions>

        {{-- 取消の確認。詳細の上に重ねる。ボタンは <x-admin-small-button>（SPは高さ44px・文字16px、lg以上は130×30・文字14px）。閉じるボタンは「キャンセル」ではなく「戻る」（「取り消す」と「キャンセル」が並ぶと意味を取り違えやすいため） --}}
        <x-slot:after>
            <div
                class="absolute inset-0 z-10 flex items-center justify-center bg-black/35 p-4"
                x-show="confirming"
                x-cloak
                x-trap="confirming"
                x-on:click.self="confirming = false"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="{{ $titleId }}-cancel-title"
                aria-describedby="{{ $titleId }}-cancel-note"
            >
                <form class="relative max-h-full w-full max-w-[400px] overflow-y-auto rounded-[30px] bg-white px-6 py-8 text-center text-ink shadow-panel" action="{{ route($listType->cancelRoute()) }}" method="POST" x-on:submit="canceling = true">
                    @csrf
                    <input type="hidden" name="id" value="{{ $detail->orderId }}">
                    <p id="{{ $titleId }}-cancel-title" class="text-base font-bold">{{ $subject }}を取り消します。よろしいですか？</p>
                    <div id="{{ $titleId }}-cancel-note" class="mt-3 flex flex-col gap-1 text-base">
                        <p>取り消すと元に戻せません。</p>
                        @if ($refundNote !== null)
                            <p>{{ $refundNote }}</p>
                        @endif
                    </div>
                    <div class="mt-6 flex flex-wrap justify-center gap-4">
                        <x-admin-small-button type="submit" x-bind:disabled="canceling" class="disabled:cursor-wait disabled:opacity-40">取り消す</x-admin-small-button>
                        <x-admin-small-button variant="cancel" autofocus x-on:click="confirming = false">戻る</x-admin-small-button>
                    </div>
                </form>
            </div>
        </x-slot:after>
    @endif
</x-admin-detail-modal>
