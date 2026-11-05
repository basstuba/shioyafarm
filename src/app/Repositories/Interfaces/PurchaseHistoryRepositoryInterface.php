<?php

namespace App\Repositories\Interfaces;

use App\Models\Purchase_history;
use App\Services\AdminListCriteria;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use Throwable;

/**
 * 購入履歴リポジトリインターフェース
 */
interface PurchaseHistoryRepositoryInterface
{
    /**
     * 購入履歴・購入明細（銀行振込のときは振込先も）をトランザクションで作成
     *
     * @param  array{user_id: int, delivery_name: string, delivery_prefecture: string, delivery_city: string, delivery_tell: string, payment_method: string, shipping_fee: int, cod_fee: int, total_price: int}  $history
     * @param  list<array{item_id: int, quantity: int, price: int, subtotal: int}>  $items
     * @param  array{stripe_payment_intent_id: string, bank_name: string|null, branch_name: string|null, account_type: string|null, account_number: string|null, account_holder_name: string|null, amount: int}|null  $bankTransfer  銀行振込の振込先（銀行振込以外は null。PaymentIntent を作った時点で支払い済みだった注文は、振込先の各項目が null）
     *
     * @throws Throwable 保存に失敗したとき（どちらも保存されない）
     */
    public function createWithItems(array $history, array $items, ?array $bankTransfer = null): Purchase_history;

    /**
     * 会員の購入履歴を新しい順にページ分けして取得（購入商品を Eager Load する）
     *
     * @return LengthAwarePaginator<int, Purchase_history>
     */
    public function paginateByUserId(int $userId, int $perPage, string $pageName): LengthAwarePaginator;

    /**
     * 会員の購入履歴を1件、詳細の表示に必要な関連（商品・振込先）つきで取得（他の会員の購入履歴・存在しない購入履歴は null）
     */
    public function findDetailByUserId(int $userId, int $id): ?Purchase_history;

    /**
     * サンクスメールの表示に必要な関連（会員・購入商品。銀行振込のときは振込先も）を、まだ読み込んでいなければ読み込む
     */
    public function loadForThanksMail(Purchase_history $purchase): Purchase_history;

    /**
     * 管理画面の注文一覧を新しい順にページ分けして取得（注文者を Eager Load する。退会済みの会員の注文も含む）
     *
     * @return LengthAwarePaginator<int, Purchase_history>
     */
    public function paginateForAdmin(AdminListCriteria $criteria, int $perPage, ?int $page = null): LengthAwarePaginator;

    /**
     * 管理画面のエクスポート用に、検索条件に合う注文を新しい順に少しずつ取得する（注文者・購入商品を Eager Load する）
     *
     * @return LazyCollection<int, Purchase_history>
     */
    public function lazyForExport(AdminListCriteria $criteria): LazyCollection;

    /**
     * 管理画面の詳細の表示に必要な関連（注文者・商品・振込先）つきで、注文を1件取得する（存在しない注文は null）
     */
    public function findDetailForAdmin(int $id): ?Purchase_history;

    /**
     * 注文の発送済みかどうかを更新する
     */
    public function updateShipped(int $id, bool $shipped): void;
}
