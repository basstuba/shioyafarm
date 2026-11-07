<?php

namespace App\Repositories\Interfaces;

use App\Enums\OrderCancelResult;
use App\Enums\ShippedUpdateResult;
use App\Models\Reservation;
use App\Services\AdminListCriteria;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use Throwable;

/**
 * 予約リポジトリインターフェース
 */
interface ReservationRepositoryInterface
{
    /**
     * 商品のすでに予約された数量の合計を取得（取消済みの予約は含めない）
     */
    public function getReservedQuantity(int $itemId): int;

    /**
     * 予約（銀行振込のときは振込先も）をトランザクションで作成
     *
     * @param  array{user_id: int, item_id: int, delivery_name: string, delivery_prefecture: string, delivery_city: string, delivery_tell: string, quantity: int, price: int, subtotal: int, payment_method: string, shipping_fee: int, total_price: int}  $reservation
     * @param  array{stripe_payment_intent_id: string, bank_name: string|null, branch_name: string|null, account_type: string|null, account_number: string|null, account_holder_name: string|null, amount: int}|null  $bankTransfer  銀行振込の振込先（銀行振込以外は null。PaymentIntent を作った時点で支払い済みだった予約は、振込先の各項目が null）
     *
     * @throws Throwable 保存に失敗したとき（どちらも保存されない）
     */
    public function createWithBankTransfer(array $reservation, ?array $bankTransfer = null): Reservation;

    /**
     * 会員の予約を新しい順にページ分けして取得（予約した商品を Eager Load する）
     *
     * @return LengthAwarePaginator<int, Reservation>
     */
    public function paginateByUserId(int $userId, int $perPage, string $pageName): LengthAwarePaginator;

    /**
     * 会員の予約を1件、詳細の表示に必要な関連（商品・振込先）つきで取得（他の会員の予約・存在しない予約は null）
     */
    public function findDetailByUserId(int $userId, int $id): ?Reservation;

    /**
     * サンクスメールの表示に必要な関連（会員・商品。銀行振込のときは振込先も）を、まだ読み込んでいなければ読み込む
     */
    public function loadForThanksMail(Reservation $reservation): Reservation;

    /**
     * 管理画面の予約一覧を新しい順にページ分けして取得（予約者を Eager Load する。退会済みの会員の予約も含む）
     *
     * @return LengthAwarePaginator<int, Reservation>
     */
    public function paginateForAdmin(AdminListCriteria $criteria, int $perPage, ?int $page = null): LengthAwarePaginator;

    /**
     * 管理画面のエクスポート用に、検索条件に合う予約を新しい順に少しずつ取得する（予約者・商品を Eager Load する）
     *
     * 取消済みの予約は、発送状況が「取消済」のとき以外は含めない。
     *
     * @return LazyCollection<int, Reservation>
     */
    public function lazyForExport(AdminListCriteria $criteria): LazyCollection;

    /**
     * エクスポートで出力される予約の件数（lazyForExport と同じ条件）
     */
    public function countForExport(AdminListCriteria $criteria): int;

    /**
     * 管理画面の詳細の表示に必要な関連（予約者・商品・振込先）つきで、予約を1件取得する（存在しない予約は null）
     */
    public function findDetailForAdmin(int $id): ?Reservation;

    /**
     * 予約の発送済みかどうかを更新する（取消済みの予約は更新しない）
     *
     * 行をロックして判定する（存在しない行は NotFound、取消済みの行は Canceled を返し、どちらも何も変えない）。
     */
    public function updateShipped(int $id, bool $shipped): ShippedUpdateResult;

    /**
     * 予約を取り消す（行をロックして、存在・発送済み・取消済みを判定する）
     *
     * 取り消せるときだけ $beforeCancel（支払方法と、銀行振込の PaymentIntent の ID（振込先の行がなければ null）を受け取り、Stripe の支払いの取消などをする）を行のロックを持ったまま呼び、
     * 取消済みにしてよい結果（OrderCancelResult::isCanceled）を返したときに取消日時を入れる。それ以外を返したときは、取消日時を入れずにその結果を返す。
     *
     * @param  Closure(string, string|null): OrderCancelResult  $beforeCancel
     */
    public function cancel(int $id, Closure $beforeCancel): OrderCancelResult;
}
