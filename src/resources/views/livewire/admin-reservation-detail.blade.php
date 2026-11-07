{{--
    管理画面の予約一覧の詳細モーダル（中身は admin/partials/order-detail-modal）
    - $detail：App\Services\OrderDetail（開いていないときは null）
    一覧の「詳細」ボタンが open-reservation-detail を送ると開く。
--}}
<div>
    @if ($detail !== null)
        @include('admin.partials.order-detail-modal', ['detail' => $detail, 'listType' => \App\Enums\AdminListType::Reservation, 'titleId' => 'admin-reservation-detail-title'])
    @endif
</div>
