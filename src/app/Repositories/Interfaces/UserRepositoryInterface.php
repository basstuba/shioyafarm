<?php

namespace App\Repositories\Interfaces;

use App\Enums\UserDeleteResult;
use App\Models\User;
use App\Services\AdminUserCriteria;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

/**
 * 会員リポジトリインターフェース
 */
interface UserRepositoryInterface
{
    /**
     * 会員の Stripe Customer ID を保存
     */
    public function updateStripeCustomerId(User $user, string $customerId): void;

    /**
     * 会員の登録情報を更新（パスワードは渡されたときだけ変更する）
     *
     * @param  array{last_name: string, first_name: string, prefecture: string, city: string, tell: string, email: string, password?: string}  $attributes
     */
    public function update(User $user, array $attributes): void;

    /**
     * 会員を退会させる（論理削除。購入履歴・予約・配送先住所は消さない）
     */
    public function withdraw(User $user): void;

    /**
     * 管理画面の会員一覧を新しく登録した順にページ分けして取得（退会済みの会員を含む）
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateForAdmin(AdminUserCriteria $criteria, int $perPage, ?int $page = null): LengthAwarePaginator;

    /**
     * 管理画面の詳細に出す会員を1件取得（退会済みの会員を含む。存在しない会員は null）
     */
    public function findForAdmin(int $id): ?User;

    /**
     * 退会済みの会員を物理削除する（会員と、注文・予約・振込情報・配送先住所、パスワード再設定のトークンも消える）
     *
     * トランザクションの中で、未発送で未取消の注文・予約が残っていないことを確かめてから削除する。
     * 退会していない・存在しない会員、未発送が残っている会員は、何も変えずに結果を返す（退会しているかの判定はここだけで行う）。
     *
     * @throws Throwable データベースのエラー（ロック待ちのタイムアウト・デッドロックなど）のとき。何も消えない（呼び出し側の AdminUserService が QueryException を結果に変える）
     */
    public function forceDeleteWithdrawn(int $id): UserDeleteResult;
}
