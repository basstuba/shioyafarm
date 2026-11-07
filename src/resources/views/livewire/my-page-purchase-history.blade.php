{{-- マイページの購入履歴と詳細モーダル（幅600pxの表。SPは左右16pxの余白で横いっぱい） --}}
<div class="mx-auto max-w-[632px] px-4 pb-[39px]">
    @include('livewire.partials.history-table', ['rows' => $rows, 'dateLabel' => '注文日', 'emptyMessage' => '購入履歴はありません', 'keyPrefix' => 'purchase'])

    @include('livewire.partials.pager', ['paginator' => $purchases, 'pageName' => \App\Livewire\MyPagePurchaseHistory::PAGE_NAME, 'topClass' => 'mt-[45px]'])

    @if ($detail !== null)
        @include('livewire.partials.order-detail-modal', ['detail' => $detail, 'dateLabel' => '注文日', 'title' => '購入の詳細', 'listType' => \App\Enums\AdminListType::Purchase, 'titleId' => 'purchase-detail-title'])
    @endif
</div>
