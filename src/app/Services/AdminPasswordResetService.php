<?php

namespace App\Services;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\PasswordValidationRules;
use App\Enums\AdminPasswordResetResult;
use App\Models\Admin;
use App\Repositories\Interfaces\AdminRepositoryInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * 管理者のパスワード再設定
 *
 * 会員とは別のパスワードブローカー（admins。トークンのテーブルも別）を使う。
 */
class AdminPasswordResetService
{
    use PasswordValidationRules;

    public function __construct(
        private readonly AdminRepositoryInterface $adminRepository,
    ) {}

    /**
     * 再設定メールを送る
     *
     * 管理者でないメールアドレスや、1分以内の再送信のときは何もしない（結果を返さないので、
     * 呼び出し側は送れたときと同じ応答にでき、管理者のメールアドレスかどうかが漏れない）。
     * メールの送信に失敗したときは、トークンを削除したうえで例外が投げられる（Admin::sendPasswordResetNotification）。
     */
    public function sendResetLink(string $email): void
    {
        $this->broker()->sendResetLink(['email' => $email]);
    }

    /**
     * トークンを確認して、パスワードを再設定する
     *
     * パスワードの桁数・確認用との一致は、トークンの確認の後に検証する（会員の再設定と同じ順）。
     * 検証に失敗したときはトークンを消さないので、同じリンクで入力し直せる。
     *
     * @throws ValidationException パスワードが規則に合わないとき
     */
    public function reset(string $email, #[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password, #[\SensitiveParameter] ?string $passwordConfirmation): AdminPasswordResetResult
    {
        $status = $this->broker()->reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            function (Admin $admin) use ($password, $passwordConfirmation): void {
                Validator::make(
                    ['password' => $password, 'password_confirmation' => $passwordConfirmation],
                    ['password' => $this->passwordRules()],
                    CreateNewUser::validationMessages(),
                )->validate();

                $this->adminRepository->updatePassword($admin, $password);

                // 会員の再設定（Fortify の CompletePasswordReset）に揃える。remember_token は管理者にはない
                event(new PasswordReset($admin));
            },
        );

        return $status === Password::PASSWORD_RESET ? AdminPasswordResetResult::Reset : AdminPasswordResetResult::InvalidLink;
    }

    /**
     * 管理者用のパスワードブローカー
     */
    private function broker(): PasswordBroker
    {
        return Password::broker(Admin::PASSWORD_BROKER);
    }
}
