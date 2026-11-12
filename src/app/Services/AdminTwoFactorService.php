<?php

namespace App\Services;

use App\Enums\AdminTwoFactorSendResult;
use App\Enums\AdminTwoFactorStartResult;
use App\Enums\AdminTwoFactorVerifyResult;
use App\Mail\AdminTwoFactorCodeMail;
use App\Models\Admin;
use App\Repositories\Interfaces\AdminRepositoryInterface;
use App\Repositories\Interfaces\AdminTwoFactorCodeRepositoryInterface;
use App\Support\AdminTwoFactorHash;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * 管理者の二段階認証（メールのワンタイムコード）
 *
 * パスワードが正しい管理者にだけ数字6桁のコードをメールで送り、そのコードを入力できたらログインを完了させる。
 * コードは1回限り・有効期限つきで、ハッシュ（AdminTwoFactorHash）にして保存する。オン・オフは config('auth.admin_two_factor.enabled')。
 */
class AdminTwoFactorService
{
    /** コードの桁数 */
    public const int CODE_LENGTH = 6;

    public function __construct(
        private readonly AdminRepositoryInterface $adminRepository,
        private readonly AdminTwoFactorCodeRepositoryInterface $codeRepository,
        private readonly AdminTwoFactorStore $store,
        private readonly AdminTwoFactorMissStore $missStore,
    ) {}

    /**
     * 二段階認証がオンか
     */
    public function isEnabled(): bool
    {
        return (bool) config('auth.admin_two_factor.enabled');
    }

    /**
     * メールアドレスとパスワードが正しい管理者を返す（ログインはしない。正しくなければ null）
     *
     * 照合は admin ガードの validate() に任せる（応答時間をそろえる処理（Timebox）と、成功時の Validated イベントは Laravel の標準のまま）。
     * attempt() がログインの前後に行う、Attempting・Failed イベントの発行とハッシュの再計算は、validate() では行われないので、同じ順で行う。
     */
    public function findByPassword(string $email, #[\SensitiveParameter] string $password): ?Admin
    {
        $guard = Auth::guard('admin');
        $credentials = ['email' => $email, 'password' => $password];

        event(new Attempting('admin', $credentials, false));

        if (! $guard->validate($credentials)) {
            event(new Failed('admin', $guard->getLastAttempted(), $credentials));

            return null;
        }

        $admin = $guard->getLastAttempted();
        if (! $admin instanceof Admin) {
            return null;
        }

        // ハッシュの設定が変わっていたら、ログインのときと同じく、このとき保存し直す
        $guard->getProvider()->rehashPasswordIfRequired($admin, $credentials);

        return $admin;
    }

    /**
     * ログインの最初の段階：パスワードを確かめ、確認コードを送って、入力待ちにする（まだログインしない）
     *
     * 確認コードの間違いが続いて、ログインできない状態のときは、コードを送らない（パスワードが正しいときだけ Suspended を返し、残り時間を添える）。
     * コードを送らずに終わったとき（パスワードの誤り・停止中・メールの送信失敗）は、セッションに残っている入力待ちも解く。
     */
    public function start(string $email, #[\SensitiveParameter] string $password): AdminTwoFactorStart
    {
        $admin = $this->findByPassword($email, $password);

        // コードを送らずに終わるときは、同じブラウザに残っている別の管理者の入力待ちを解く（Sent のときは issue() が上書きする）
        if ($admin === null) {
            $this->store->forget();

            return new AdminTwoFactorStart(AdminTwoFactorStartResult::InvalidCredentials);
        }

        $minutes = $this->suspendedMinutes($admin);
        if ($minutes !== null) {
            $this->store->forget();

            return new AdminTwoFactorStart(AdminTwoFactorStartResult::Suspended, $minutes);
        }

        if ($this->issue($admin) === AdminTwoFactorSendResult::MailFailed) {
            return new AdminTwoFactorStart(AdminTwoFactorStartResult::MailFailed);
        }

        return new AdminTwoFactorStart(AdminTwoFactorStartResult::Sent);
    }

    /**
     * パスワードを通って、確認コードの入力待ちの管理者を探す
     *
     * オフのとき・入力待ちでないとき・管理者が見つからないとき・入力待ちの途中でパスワードが変わったとき（再設定されたとき）は、
     * 入力待ちを解いて、管理者のいない結果を返す。パスワードが変わったときだけは、その理由を結果に持たせる（呼び出し側が、ログインページへ戻すときに案内を出す）。
     */
    public function pendingAdmin(): AdminTwoFactorPending
    {
        if (! $this->isEnabled()) {
            return AdminTwoFactorPending::none();
        }

        $pending = $this->store->get();
        if ($pending === null) {
            return AdminTwoFactorPending::none();
        }

        $admin = $this->adminRepository->find($pending['id']);

        if ($admin === null) {
            $this->store->forget();

            return AdminTwoFactorPending::none();
        }

        if (! hash_equals($pending['fingerprint'], AdminTwoFactorHash::passwordFingerprint($admin->password))) {
            $this->store->forget();

            return AdminTwoFactorPending::passwordChanged();
        }

        return AdminTwoFactorPending::found($admin);
    }

    /**
     * 新しいコードを作って送り、確認コードの入力待ちにする（ログイン時。前のコードは使えなくなり、失敗回数は0に戻る）
     */
    public function issue(Admin $admin): AdminTwoFactorSendResult
    {
        $code = $this->generateCode();
        $now = now();

        $this->codeRepository->replace($admin, AdminTwoFactorHash::code($code), $now->copy()->addMinutes($this->expireMinutes()), $now);

        return $this->deliver($admin, $code);
    }

    /**
     * 確認コードを再送する
     *
     * 前回の送信から決められた時間がたっていないときは送らない（判定と置き換えは1回の UPDATE で行うので、同時に押されても送るのは1通）。
     * 新しいコードに置き換え、有効期限は数え直すが、失敗回数は引き継ぐ（再送で失敗回数を戻せると、コードを何度でも推測できてしまうため）。
     * 置き換えられなかったとき、コードがなければ（無効になった・使われた）、新しく作らず、入力待ちを解く（ログインからやり直す）。
     */
    public function resend(Admin $admin): AdminTwoFactorSendResult
    {
        $code = $this->generateCode();
        $now = now();

        $replaced = $this->codeRepository->replaceIfSentBefore(
            $admin,
            $now->copy()->subSeconds($this->resendSeconds()),
            AdminTwoFactorHash::code($code),
            $now->copy()->addMinutes($this->expireMinutes()),
            $now,
        );

        if ($replaced) {
            return $this->deliver($admin, $code);
        }

        if ($this->codeRepository->findByAdmin($admin) === null) {
            $this->store->forget();

            return AdminTwoFactorSendResult::NoCode;
        }

        return AdminTwoFactorSendResult::TooSoon;
    }

    /**
     * 保存したコードをメールで送り、確認コードの入力待ちにする（送れなかったときはコードを消す）
     */
    private function deliver(Admin $admin, #[\SensitiveParameter] string $code): AdminTwoFactorSendResult
    {
        try {
            Mail::send(new AdminTwoFactorCodeMail($admin, $code, $this->expireMinutes()));
        } catch (Throwable $e) {
            // 届いていないコードを残さない
            Log::error('管理者の確認コードのメールを送信できませんでした', ['admin_id' => $admin->id, 'exception' => $e]);
            $this->codeRepository->deleteByAdmin($admin);
            $this->store->forget();

            return AdminTwoFactorSendResult::MailFailed;
        }

        $this->store->put($admin->id, AdminTwoFactorHash::passwordFingerprint($admin->password));

        return AdminTwoFactorSendResult::Sent;
    }

    /**
     * 確認コードを照合する
     *
     * 失敗回数を1つ使う（DB の UPDATE で、上限までしか増やせず、期限切れのコードには使えない）→ コードの照合・期限の確認・削除を1回の DELETE で行う、の順。
     * そのため、同時に何通送られても、試せるのは上限の回数までで、再送で置き換えられた前のコードは通らず、新しいコードも消されない。
     * 期限切れは失敗回数に数えない。正しければ Verified（コードは削除済み）。
     * 期限切れ（コードが無いときを含む）・誤りは ValidationException。
     * 間違いが上限に達したとき（今回の間違いで達したときを含む）は、コードと入力待ちの状態を消して Locked を返す。
     * 同じ管理者の間違いは、ログインし直しても別のIPからでも1時間に合計 miss_limit 回までで、達したら Suspended を返す（以後はその時間が過ぎるまでログインでコードを送らない）。
     *
     * @throws ValidationException コードの期限切れ・誤り
     */
    public function verify(Admin $admin, #[\SensitiveParameter] string $input): AdminTwoFactorVerifyResult
    {
        $now = now();
        $attempts = $this->codeRepository->incrementAttempts($admin, $this->maxAttempts(), $now);

        if ($attempts === null) {
            // すでに上限に達している（同時に送られた他のリクエストが使い切った）か、期限切れ・コードが無い
            $current = $this->codeRepository->findByAdmin($admin);

            return $current !== null && $current->attempts >= $this->maxAttempts() ? $this->lock($admin) : throw $this->expired();
        }

        if ($this->codeRepository->consumeIfMatches($admin, AdminTwoFactorHash::code($input), $now)) {
            $this->store->forget();

            return AdminTwoFactorVerifyResult::Verified;
        }

        if ($this->missStore->hit($admin)) {
            $this->lock($admin);

            return AdminTwoFactorVerifyResult::Suspended;
        }

        $remaining = $this->maxAttempts() - $attempts;

        if ($remaining <= 0) {
            return $this->lock($admin);
        }

        throw ValidationException::withMessages(['code' => [trans('admin_two_factor.wrong', ['remaining' => $remaining])]]);
    }

    /**
     * 確認コードの間違いが続いて、ログインできない状態なら、あと何分で試せるか（切り上げ。そうでなければ null）
     */
    public function suspendedMinutes(Admin $admin): ?int
    {
        if (! $this->missStore->reachedLimit($admin)) {
            return null;
        }

        return max(1, (int) ceil($this->missStore->availableIn($admin) / 60));
    }

    /**
     * コードと入力待ちの状態を消して、上限に達した結果を返す
     */
    private function lock(Admin $admin): AdminTwoFactorVerifyResult
    {
        $this->codeRepository->deleteByAdmin($admin);
        $this->store->forget();

        return AdminTwoFactorVerifyResult::Locked;
    }

    /**
     * 期限切れ（コードが無いときを含む）の入力エラー
     */
    private function expired(): ValidationException
    {
        return ValidationException::withMessages(['code' => [trans('admin_two_factor.expired')]]);
    }

    /**
     * コードの有効期限（分）
     */
    public function expireMinutes(): int
    {
        return (int) config('auth.admin_two_factor.expire');
    }

    /**
     * コードを間違えられる回数
     */
    public function maxAttempts(): int
    {
        return (int) config('auth.admin_two_factor.max_attempts');
    }

    /**
     * 再送までの待ち時間（分。切り上げ。案内の文言に使う）
     */
    public function resendMinutes(): int
    {
        return max(1, (int) ceil($this->resendSeconds() / 60));
    }

    /**
     * 再送までの待ち時間（秒）
     */
    private function resendSeconds(): int
    {
        return (int) config('auth.admin_two_factor.resend_seconds');
    }

    /**
     * 数字6桁のコードを作る（先頭が0でもよい）
     */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }
}
