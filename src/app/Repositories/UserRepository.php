<?php

namespace App\Repositories;

use App\Enums\UserDeleteResult;
use App\Models\Purchase_history;
use App\Models\Reservation;
use App\Models\User;
use App\Repositories\Concerns\MatchesFullName;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\AdminUserCriteria;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Override;

/**
 * 会員リポジトリ
 */
class UserRepository implements UserRepositoryInterface
{
    use MatchesFullName;

    /**
     * 会員の Stripe Customer ID を保存
     *
     * stripe_customer_id は fillable に含めないため、forceFill で保存する。
     */
    #[Override]
    public function updateStripeCustomerId(User $user, string $customerId): void
    {
        $user->forceFill(['stripe_customer_id' => $customerId])->save();
    }

    /**
     * 会員の登録情報を更新（パスワードは渡されたときだけ変更する）
     *
     * @param  array{last_name: string, first_name: string, prefecture: string, city: string, tell: string, email: string, password?: string}  $attributes
     */
    #[Override]
    public function update(User $user, array $attributes): void
    {
        $user->update($attributes);
    }

    /**
     * 会員を退会させる（論理削除。購入履歴・予約・配送先住所は消さない）
     */
    #[Override]
    public function withdraw(User $user): void
    {
        $user->delete();
    }

    /**
     * 管理画面の会員一覧を新しく登録した順にページ分けして取得（退会済みの会員を含む）
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Override]
    public function paginateForAdmin(AdminUserCriteria $criteria, int $perPage, ?int $page = null): LengthAwarePaginator
    {
        if ($criteria->withdrawn === true) {
            $query = User::onlyTrashed();
        } elseif ($criteria->withdrawn === false) {
            $query = User::withoutTrashed();
        } else {
            $query = User::withTrashed();
        }
        if ($criteria->name !== '') {
            $this->whereFullNameLike($query, $criteria->name);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage, page: $page);
    }

    /**
     * 管理画面の詳細に出す会員を1件取得（退会済みの会員を含む。存在しない会員は null）
     */
    #[Override]
    public function findForAdmin(int $id): ?User
    {
        return User::withTrashed()->find($id);
    }

    /**
     * 退会済みの会員を物理削除する（会員と、注文・予約・振込情報・配送先住所は外部キーの cascade で消える）
     *
     * 会員の行と、その会員の注文・予約の行をロックしてから判定するため、判定と削除の間に発送済みのチェックや取消が変わらない。
     * 同じトランザクションの中で、その会員のパスワード再設定のトークンも、会員のパスワードブローカー（SendsPasswordResetMail と同じ取り方）の deleteToken で消す
     * （同じメールアドレスで再登録した人に、古いトークンが使われないようにする）。
     * セッション（sessions）は消さない。sessions.user_id は既定のガードのIDで、管理者のセッションにも管理者のIDが入るため、同じ番号の管理者のセッションを消してしまう。
     * 退会済みの会員はソフトデリートで既に認証できないので、セッションを消す必要もない。
     */
    #[Override]
    public function forceDeleteWithdrawn(int $id): UserDeleteResult
    {
        return DB::transaction(function () use ($id): UserDeleteResult {
            $user = User::withTrashed()->whereKey($id)->lockForUpdate()->first();
            if ($user === null || ! $user->trashed()) {
                return UserDeleteResult::NotWithdrawn;
            }

            // 未発送で未取消のものが1件でもあれば止める（発送済みか取消済みなら削除できる）
            $purchases = Purchase_history::where('user_id', $id)->lockForUpdate()->get(['id', 'shipped', 'canceled_at']);
            $reservations = Reservation::where('user_id', $id)->lockForUpdate()->get(['id', 'shipped', 'canceled_at']);
            $isUnshipped = fn (Purchase_history|Reservation $order): bool => ! $order->shipped && $order->canceled_at === null;
            if ($purchases->contains($isUnshipped) || $reservations->contains($isUnshipped)) {
                return UserDeleteResult::HasUnshipped;
            }

            /** @var PasswordBroker $passwordBroker deleteToken は契約（Contracts\Auth\PasswordBroker）にはなく、実装クラスにある */
            $passwordBroker = Password::broker((string) config('fortify.passwords'));
            $passwordBroker->deleteToken($user);
            $user->forceDelete();

            return UserDeleteResult::Deleted;
        });
    }
}
