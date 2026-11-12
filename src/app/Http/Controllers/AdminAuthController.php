<?php

namespace App\Http\Controllers;

use App\Enums\AdminTwoFactorStartResult;
use App\Http\Requests\AdminLoginRequest;
use App\Services\AdminTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 管理者のログインページの表示・ログイン・ログアウト
 *
 * 会員（web ガード）とは別の admin ガードだけを扱う。
 */
class AdminAuthController extends Controller
{
    /**
     * ログインページ
     */
    public function create(): View
    {
        Log::debug('AdminAuthController@create start');

        $view = view('auth.admin.login');

        Log::debug('AdminAuthController@create end');

        return $view;
    }

    /**
     * ログイン
     *
     * 二段階認証がオンのときは、パスワードが正しくてもログインせず、確認コードをメールで送って確認コードの入力ページへ進める。
     * メールを送れなかったときは、ログインページへ戻す（ログインしない）。
     *
     * @throws ValidationException 資格情報が正しくないとき
     */
    public function login(AdminLoginRequest $request, AdminTwoFactorService $twoFactor): RedirectResponse
    {
        Log::debug('AdminAuthController@login start');

        $credentials = $request->safe()->only(['email', 'password']);

        if ($twoFactor->isEnabled()) {
            return $this->startTwoFactor($request, $twoFactor, $credentials['email'], $credentials['password']);
        }

        if (! Auth::guard('admin')->attempt($credentials)) {
            throw $this->invalidCredentials();
        }

        // セッションIDは attempt() が作り直し済み（中身は残るので会員のログインも残る）。CSRFトークンも作り直す
        $request->session()->regenerateToken();

        Log::debug('AdminAuthController@login end');

        // url.intended は会員のログインの戻り先として使われているため、ここでは使わない
        return redirect()->route('admin.home');
    }

    /**
     * パスワードを確かめ、確認コードを送って、確認コードの入力ページへ進める（まだログインしない）
     *
     * @throws ValidationException 資格情報が正しくないとき
     */
    private function startTwoFactor(AdminLoginRequest $request, AdminTwoFactorService $twoFactor, string $email, #[\SensitiveParameter] string $password): RedirectResponse
    {
        $start = $twoFactor->start($email, $password);

        return match ($start->result) {
            AdminTwoFactorStartResult::InvalidCredentials => throw $this->invalidCredentials(),
            AdminTwoFactorStartResult::Suspended => $this->backToLogin($email, 'suspended', trans('admin_two_factor.suspended', ['minutes' => $start->suspendedMinutes])),
            AdminTwoFactorStartResult::MailFailed => $this->backToLogin($email, 'mail failed', trans('admin_two_factor.mail_failed')),
            AdminTwoFactorStartResult::Sent => $this->toTwoFactorPage($request),
        };
    }

    /**
     * コードを送らずに、メールアドレスを残してログインページへ戻し、案内を出す
     *
     * @param  string  $logLabel  終了のログに添える理由
     */
    private function backToLogin(string $email, string $logLabel, string $status): RedirectResponse
    {
        Log::debug('AdminAuthController@login end ('.$logLabel.')');

        return redirect()->route('admin.login')
            ->withInput(['email' => $email])
            ->with('status', $status);
    }

    /**
     * 確認コードの入力ページへ進める
     */
    private function toTwoFactorPage(AdminLoginRequest $request): RedirectResponse
    {
        // ログイン前の状態を引き継がないよう、セッションIDを作り直す（中身は残る）
        $request->session()->regenerate();

        Log::debug('AdminAuthController@login end (two factor)');

        return redirect()->route('admin.two-factor');
    }

    /**
     * 資格情報が正しくないときの入力エラー（メールアドレスの欄に出す）
     */
    private function invalidCredentials(): ValidationException
    {
        return ValidationException::withMessages(['email' => [trans('auth.failed')]])
            ->redirectTo(route('admin.login'));
    }

    /**
     * ログアウト（Laravel標準どおり、セッション全体を破棄する）
     */
    public function logout(Request $request): RedirectResponse
    {
        Log::debug('AdminAuthController@logout start');

        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::debug('AdminAuthController@logout end');

        return redirect()->route('admin.login');
    }
}
