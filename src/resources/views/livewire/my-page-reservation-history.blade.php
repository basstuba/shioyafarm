{{-- マイページの予約履歴と詳細モーダル。見た目は仮置き（Figmaの上限リセット後に仕上げる） --}}
<div>
    @include('livewire.partials.history-table', ['rows' => $rows, 'dateLabel' => '予約日', 'emptyMessage' => '予約履歴はありません', 'keyPrefix' => 'reservation'])

    @include('livewire.partials.pager', ['paginator' => $reservations, 'pageName' => \App\Livewire\MyPageReservationHistory::PAGE_NAME])

    @if ($detail !== null)
        @include('livewire.partials.order-detail-modal', ['detail' => $detail, 'dateLabel' => '予約日', 'title' => '予約の詳細', 'titleId' => 'reservation-detail-title'])
    @endif
</div>
