<?php

namespace App\Services;

use App\Enums\UserDeleteResult;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Concerns\PaginatesAdminList;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * 管理画面の会員一覧サービス（検索・詳細・退会済みの会員の物理削除）
 *
 * 検索条件は、検索欄の値の形（状態は 'member'・'withdrawn'・空、氏名）で受け取る。
 */
class AdminUserService
{
    use DetectsConcurrencyErrors;
    use PaginatesAdminList;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * 検索条件に合う会員を新しく登録した順にページ分けして取得（退会済みを含む。範囲外のページ番号は最終ページにする）
     *
     * @param  array{status?: string, name?: string}  $conditions
     * @return LengthAwarePaginator<int, User>
     */
    public function search(array $conditions): LengthAwarePaginator
    {
        $criteria = AdminUserCriteria::fromConditions($conditions);

        return $this->paginateWithinRange(fn (?int $page): LengthAwarePaginator => $this->userRepository->paginateForAdmin($criteria, self::PER_PAGE, $page));
    }

    /**
     * 会員の詳細を取得（存在しない会員は null）
     */
    public function findDetail(int $id): ?UserDetail
    {
        $user = $this->userRepository->findForAdmin($id);

        return $user === null ? null : UserDetail::fromUser($user);
    }

    /**
     * 退会済みの会員を物理削除する（Stripe の顧客データは消さない）
     *
     * 同時実行のエラー（ロック待ちのタイムアウト・デッドロック。DetectsConcurrencyErrors の判定）は、ログに残して Failed を返す（何も消えていない）。
     * それ以外の例外（制約違反・接続エラー・コードの誤りなど）は、隠さずそのまま投げる。
     *
     * 同時実行のエラーは、外側のトランザクションの中で呼ばれると DeadlockException（外側が再試行できるようにするためのLaravelの動き）になるため、両方を捕まえる。
     *
     * @throws QueryException 同時実行のエラー以外のデータベースのエラー
     */
    public function delete(int $id): UserDeleteResult
    {
        try {
            return $this->userRepository->forceDeleteWithdrawn($id);
        } catch (QueryException|DeadlockException $e) {
            if (! $this->causedByConcurrencyError($e)) {
                throw $e;
            }

            Log::error('会員の物理削除に失敗しました。', ['user_id' => $id, 'exception' => $e]);

            return UserDeleteResult::Failed;
        }
    }
}
