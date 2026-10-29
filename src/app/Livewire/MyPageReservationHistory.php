<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithClampedPagination;
use App\Livewire\Concerns\WithOrderDetailModal;
use App\Models\Reservation;
use App\Models\User;
use App\Services\OrderDetail;
use App\Services\OrderHistoryService;
use App\Support\LoginUser;
use Illuminate\View\View;
use Livewire\Component;
use Override;

/**
 * マイページの予約履歴（5件ずつのページ送り）と、詳細モーダル
 *
 * 一覧も詳細も、ログイン中の会員の予約だけを取得する。
 */
class MyPageReservationHistory extends Component
{
    use WithClampedPagination;
    use WithOrderDetailModal;

    /** ページ番号の名前（お届け先住所・購入履歴とは別のページ番号にする） */
    public const string PAGE_NAME = 'reservationPage';

    #[Override]
    protected function findDetail(OrderHistoryService $historyService, User $user, int $id): ?OrderDetail
    {
        return $historyService->findReservationDetail($user, $id);
    }

    public function render(OrderHistoryService $historyService): View
    {
        $user = LoginUser::require();
        $reservations = $this->loadClamped(fn () => $historyService->paginateReservations($user, self::PAGE_NAME), self::PAGE_NAME);

        $rows = $reservations->getCollection()->map(fn (Reservation $reservation): array => [
            'id' => $reservation->id,
            'date' => $reservation->created_at?->format('Y/m/d'),
            'names' => [$reservation->item->display_name],
            'total' => $reservation->total_price,
        ]);

        return view('livewire.my-page-reservation-history', [
            'reservations' => $reservations,
            'rows' => $rows,
            'detail' => $this->currentDetail($historyService, $user),
        ]);
    }
}
