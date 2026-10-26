{{-- マイページの購入履歴と詳細モーダル。見た目は仮置き（Figmaの上限リセット後に仕上げる） --}}
<div>
    @include('livewire.partials.history-table', ['rows' => $rows, 'dateLabel' => '注文日', 'emptyMessage' => '購入履歴はありません', 'keyPrefix' => 'purchase'])

    @include('livewire.partials.pager', ['paginator' => $purchases, 'pageName' => \App\Livewire\MyPagePurchaseHistory::PAGE_NAME])

    @if ($detail !== null)
        @include('livewire.partials.order-detail-modal', ['detail' => $detail, 'dateLabel' => '購入日', 'title' => '購入の詳細', 'titleId' => 'purchase-detail-title'])
    @endif
</div>
