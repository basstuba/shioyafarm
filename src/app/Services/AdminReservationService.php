<?php

namespace App\Services;

use App\Exports\ReservationExport;
use App\Models\Reservation;
use App\Repositories\Interfaces\ReservationRepositoryInterface;
use App\Services\Concerns\PaginatesAdminList;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 管理画面の予約一覧サービス（検索・発送済みの更新・詳細・エクスポート）
 *
 * 検索条件は、検索欄の値の形（発送状況は '0'・'1'・空、予約日は Y-m-d・空、予約者名）で受け取る。
 */
class AdminReservationService
{
    use PaginatesAdminList;

    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {}

    /**
     * 検索条件に合う予約を新しい順にページ分けして取得（範囲外のページ番号は最終ページにする）
     *
     * @param  array{shipped?: string, date?: string, name?: string}  $conditions
     * @return LengthAwarePaginator<int, Reservation>
     */
    public function search(array $conditions): LengthAwarePaginator
    {
        $criteria = AdminListCriteria::fromConditions($conditions);

        return $this->paginateWithinRange(fn (?int $page): LengthAwarePaginator => $this->reservationRepository->paginateForAdmin($criteria, self::PER_PAGE, $page));
    }

    /**
     * 予約の発送済みかどうかを更新する（存在しない予約は、呼び出し側のバリデーションで弾く）
     */
    public function updateShipped(int $id, bool $shipped): void
    {
        $this->reservationRepository->updateShipped($id, $shipped);
    }

    /**
     * 予約の詳細を取得（存在しない予約は null）
     */
    public function findDetail(int $id): ?OrderDetail
    {
        $reservation = $this->reservationRepository->findDetailForAdmin($id);

        return $reservation === null ? null : OrderDetail::fromReservation($reservation);
    }

    /**
     * 検索条件に合う全件（ページ番号は無視）を出力するエクスポートを作る
     *
     * @param  array{shipped?: string, date?: string, name?: string}  $conditions
     */
    public function reservationExport(array $conditions): ReservationExport
    {
        return new ReservationExport($this->reservationRepository->lazyForExport(AdminListCriteria::fromConditions($conditions)));
    }
}
