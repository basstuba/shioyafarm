<?php

namespace App\Services;

use App\Models\Purchase_history;
use App\Models\Reservation;
use App\Models\User;
use App\Repositories\Interfaces\PurchaseHistoryRepositoryInterface;
use App\Repositories\Interfaces\ReservationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * マイページの購入履歴・予約履歴サービス
 *
 * 一覧も詳細も、ログイン中の会員の分だけを取得する（他の会員の履歴は取得できない）。
 */
class OrderHistoryService
{
    /** 一覧の1ページの件数 */
    public const int PER_PAGE = 5;

    public function __construct(
        private readonly PurchaseHistoryRepositoryInterface $purchaseHistoryRepository,
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {}

    /**
     * 購入履歴を新しい順にページ分けして取得
     *
     * @return LengthAwarePaginator<int, Purchase_history>
     */
    public function paginatePurchases(User $user, string $pageName): LengthAwarePaginator
    {
        return $this->purchaseHistoryRepository->paginateByUserId($user->id, self::PER_PAGE, $pageName);
    }

    /**
     * 予約履歴を新しい順にページ分けして取得
     *
     * @return LengthAwarePaginator<int, Reservation>
     */
    public function paginateReservations(User $user, string $pageName): LengthAwarePaginator
    {
        return $this->reservationRepository->paginateByUserId($user->id, self::PER_PAGE, $pageName);
    }

    /**
     * 購入履歴の詳細を取得（他の会員の購入履歴・存在しない購入履歴は null）
     */
    public function findPurchaseDetail(User $user, int $purchaseId): ?OrderDetail
    {
        $purchase = $this->purchaseHistoryRepository->findDetailByUserId($user->id, $purchaseId);

        return $purchase === null ? null : OrderDetail::fromPurchase($purchase);
    }

    /**
     * 予約の詳細を取得（他の会員の予約・存在しない予約は null）
     */
    public function findReservationDetail(User $user, int $reservationId): ?OrderDetail
    {
        $reservation = $this->reservationRepository->findDetailByUserId($user->id, $reservationId);

        return $reservation === null ? null : OrderDetail::fromReservation($reservation);
    }
}
