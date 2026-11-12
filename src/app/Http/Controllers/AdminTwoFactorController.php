<?php

namespace App\Http\Controllers;

use App\Enums\AdminTwoFactorSendResult;
use App\Enums\AdminTwoFactorVerifyResult;
use App\Http\Requests\AdminTwoFactorRequest;
use App\Services\AdminTwoFactorPending;
use App\Services\AdminTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 管理者の二段階認証（確認コードの入力ページの表示・コードの確認・コードの再送）
 *
 * パスワードを通った管理者がセッションにいないとき（直接開いた・5回失敗した後・二段階認証がオフのとき）は、管理者用ログインページへ戻す。
 */
class AdminTwoFactorController extends Controller
{
    /**
     * 確認コードの入力ページ
     */
    public function create(AdminTwoFactorService $service): View|RedirectResponse
    {
        Log::debug('AdminTwoFactorController@create start');

        $pending = $service->pendingAdmin();
        if ($pending->admin === null) {
            Log::debug('AdminTwoFactorController@create end (no pending)');

            return $this->backToLogin($pending);
        }

        $view = view('auth.admin.two-factor', ['expireMinutes' => $service->expireMinutes()]);

        Log::debug('AdminTwoFactorController@create end');

        return $view;
    }

    /**
     * 確認コードを確かめて、ログインを完了する
     *
     * @throws ValidationException コードが誤り・期限切れのとき
     */
    public function store(AdminTwoFactorRequest $request, AdminTwoFactorService $service): RedirectResponse
    {
        Log::debug('AdminTwoFactorController@store start');

        $pending = $service->pendingAdmin();
        $admin = $pending->admin;
        if ($admin === null) {
            Log::debug('AdminTwoFactorController@store end (no pending)');

            return $this->backToLogin($pending);
        }

        try {
            $result = $service->verify($admin, $request->string('code')->toString());
        } catch (ValidationException $e) {
            throw $e->redirectTo(route('admin.two-factor'));
        }

        if ($result !== AdminTwoFactorVerifyResult::Verified) {
            Log::debug('AdminTwoFactorController@store end (locked)');

            return redirect()->route('admin.login')->with('status', match ($result) {
                AdminTwoFactorVerifyResult::Suspended => trans('admin_two_factor.suspended', ['minutes' => $service->suspendedMinutes($admin)]),
                default => trans('admin_two_factor.locked', ['max' => $service->maxAttempts()]),
            });
        }

        // ログインするとセッションIDが作り直される（login() の中）。CSRFトークンも作り直す
        Auth::guard('admin')->login($admin);
        $request->session()->regenerateToken();

        Log::debug('AdminTwoFactorController@store end');

        // url.intended は会員のログインの戻り先として使われているため、ここでは使わない
        return redirect()->route('admin.home');
    }

    /**
     * 確認コードを再送する（前回の送信から決められた時間（1分）がたっていないときは送らない）
     */
    public function resend(AdminTwoFactorService $service): RedirectResponse
    {
        Log::debug('AdminTwoFactorController@resend start');

        $pending = $service->pendingAdmin();
        $admin = $pending->admin;
        if ($admin === null) {
            Log::debug('AdminTwoFactorController@resend end (no pending)');

            return $this->backToLogin($pending);
        }

        $result = $service->resend($admin);

        Log::debug('AdminTwoFactorController@resend end');

        return match ($result) {
            AdminTwoFactorSendResult::Sent => redirect()->route('admin.two-factor')->with('status', trans('admin_two_factor.resent')),
            AdminTwoFactorSendResult::TooSoon => redirect()->route('admin.two-factor')->with('status', trans('admin_two_factor.too_soon', ['minutes' => $service->resendMinutes()])),
            AdminTwoFactorSendResult::NoCode => redirect()->route('admin.login')->with('status', trans('admin_two_factor.code_invalidated')),
            AdminTwoFactorSendResult::MailFailed => redirect()->route('admin.login')->with('status', trans('admin_two_factor.mail_failed')),
        };
    }

    /**
     * 入力待ちを解いたときの戻り先（ログインページ）。パスワードが変わったためなら、その案内を付ける（もともと入力待ちがないときは付けない）
     */
    private function backToLogin(AdminTwoFactorPending $pending): RedirectResponse
    {
        $response = redirect()->route('admin.login');

        return $pending->passwordChanged ? $response->with('status', trans('admin_two_factor.password_changed')) : $response;
    }
}
