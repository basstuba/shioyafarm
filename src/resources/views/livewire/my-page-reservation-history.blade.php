{{-- マイページの予約履歴と詳細モーダル（幅600pxの表。SPは左右16pxの余白で横いっぱい） --}}
<div class="mx-auto max-w-[632px] px-4 pb-[39px]">
    @include('livewire.partials.history-table', ['rows' => $rows, 'dateLabel' => '予約日', 'emptyMessage' => '予約履歴はありません', 'keyPrefix' => 'reservation'])

    @include('livewire.partials.pager', ['paginator' => $reservations, 'pageName' => \App\Livewire\MyPageReservationHistory::PAGE_NAME, 'topClass' => 'mt-[45px]'])

    @if ($detail !== null)
        @include('livewire.partials.order-detail-modal', ['detail' => $detail, 'dateLabel' => '予約日', 'title' => '予約の詳細', 'titleId' => 'reservation-detail-title'])
    @endif
</div>
