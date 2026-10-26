<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;

/**
 * マイページサービス（登録情報の変更・退会）
 */
class MyPageService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * 登録情報を変更する
     *
     * 姓・名・地域・ご住所・電話番号・メールアドレスがすべて今の値と同じで、パスワードが空のときは「変更なし」とみなし、保存しない。
     * メールアドレスは大文字小文字の違いも変更として扱う（前後の空白はリクエストの TrimStrings で除かれる）。
     * パスワードが空（未入力）のときは、パスワードを変更しない。パスワードを変えたときは、ほかの端末のセッションが無効になる（AuthenticateSession ミドルウェア）。
     * 呼び出し側は、$user にログイン中の会員を渡す。
     *
     * @param  array{last_name: string, first_name: string, prefecture: string, city: string, tell: string, email: string, password?: string|null}  $validated
     * @return bool 変更して保存したか（変更がなくて保存しなかったときは false）
     */
    public function updateProfile(User $user, array $validated): bool
    {
        $attributes = [
            'last_name' => $validated['last_name'],
            'first_name' => $validated['first_name'],
            'prefecture' => $validated['prefecture'],
            'city' => $validated['city'],
            'tell' => $validated['tell'],
            'email' => $validated['email'],
        ];

        $password = $validated['password'] ?? null;
        $passwordChanged = is_string($password) && $password !== '';

        $profileChanged = array_any(
            array_keys($attributes),
            fn (string $column): bool => $user->getAttribute($column) !== $attributes[$column],
        );
        if (! $profileChanged && ! $passwordChanged) {
            return false;
        }

        if ($passwordChanged) {
            $attributes['password'] = $password; // ハッシュ化は User の cast が1回だけ行い、ほかの項目と一緒に1回で保存する
        }

        $this->userRepository->update($user, $attributes);

        if ($passwordChanged) {
            $this->refreshRememberCookie($user);
        }

        return true;
    }

    /**
     * パスワードを変えたあと、今の端末のログイン保持（remember）の cookie を新しいパスワードで作り直す
     *
     * ほかの端末のセッション・remember は、AuthenticateSession ミドルウェアが、保存しているパスワードのハッシュと
     * 会員のものを比べて無効にする。今の端末のセッションは、同じミドルウェアが応答の最後に新しいハッシュで更新する。
     * remember の cookie だけは古いハッシュを持ったままになるため、持っているときは login() で作り直す。
     */
    private function refreshRememberCookie(User $user): void
    {
        if (request()->hasCookie(Auth::getRecallerName())) {
            Auth::login($user, true);
        }
    }

    /**
     * 退会する（論理削除）
     *
     * 購入履歴・予約・配送先住所は消さない。Stripe の顧客情報も消さない。
     * ログアウトとセッションの破棄は呼び出し側（Controller）で行う。
     */
    public function withdraw(User $user): void
    {
        $this->userRepository->withdraw($user);
    }
}
