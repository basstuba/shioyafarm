{{--
    管理画面の掲載商品一覧の詳細モーダル
    - $detail：App\Services\ItemDetail（開いていないときは null）
    一覧の「詳細」ボタンが open-item-detail を送ると開く。外枠（背景・パネル・「閉じる」・Esc）は <x-admin-detail-modal>、各項目は <x-admin-detail-item>（注文・予約・会員・お問い合わせの詳細と共通）。
    項目は Figma の8つ（商品名・商品重量（kg）・商品金額（円・税込）・商品画像・商品紹介文・おすすめ商品掲載・通常/予約限定・予約可能数）。
    商品画像はモーダルの中身の幅いっぱい（PCは320px、SPはモーダルの幅）で、縦横比を保つ（切り抜かない。<x-item-image>、h-auto。予約限定のバッジは付けない）。紹介文は改行をそのまま表示し（whitespace-pre-wrap。HTMLの記号はエスケープされる）、長いときは枠の中をスクロールする。
--}}
<div>
    @if ($detail !== null)
        @php($item = $detail->item)
        <x-admin-detail-modal title-id="admin-item-detail-title" title="掲載商品の詳細">
            <dl class="flex flex-col gap-7">
                <x-admin-detail-item label="商品名">{{ $item->name }}</x-admin-detail-item>
                <x-admin-detail-item label="商品重量（kg）">{{ $item->weight }}</x-admin-detail-item>
                <x-admin-detail-item label="商品金額（円・税込）">{{ number_format($item->price) }}</x-admin-detail-item>
                <x-admin-detail-item label="商品画像">
                    <x-item-image :item="$item" :badge="false" img-class="h-auto" />
                </x-admin-detail-item>
                <x-admin-detail-item label="商品紹介文">
                    <div class="max-h-[40dvh] overflow-y-auto whitespace-pre-wrap focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink" tabindex="0" role="region" aria-label="商品紹介文">{{ $item->detail }}</div>
                </x-admin-detail-item>
                <x-admin-detail-item label="おすすめ商品掲載">{{ $item->recommend ? '掲載' : '未掲載' }}</x-admin-detail-item>
                <x-admin-detail-item label="通常/予約限定">{{ $item->is_reserved_only ? '予約限定' : '通常' }}</x-admin-detail-item>
                <x-admin-detail-item label="予約可能数">{{ $detail->reservationLimitLabel() }}</x-admin-detail-item>
            </dl>
        </x-admin-detail-modal>
    @endif
</div>
