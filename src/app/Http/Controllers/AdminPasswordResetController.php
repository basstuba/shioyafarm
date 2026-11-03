<?php

namespace App\Http\Controllers;

use App\Enums\AdminPasswordResetResult;
use App\Http\Requests\AdminNewPasswordRequest;
use App\Http\Requests\AdminPasswordResetLinkRequest;
use App\Services\AdminPasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * 管理者のパスワード再設定（再設定メールの送信ページ・送信、新しいパスワードの設定ページ・設定）
 *
 * 会員の再設定（Fortify）とは別の仕組み（パスワードブローカー admins）で動く。
 */
class AdminPasswordResetController extends Controller
{
    /**
     * 再設定メールの送信ページ
     */
    public function create(): View
    {
        Log::debug('AdminPasswordResetController@create start');

        $view = view('auth.admin.forgot-password');

        Log::debug('AdminPasswordResetController@create end');

        return $view;
    }

    /**
     * 再設定メールを送る
     *
     * 管理者でないメールアドレスや、1分以内の再送信でも、送れたときと同じ案内にする（管理者かどうかを漏らさない）。
     * メールアドレスは小文字にそろえて探す（会員の再設定と同じ）。
     */
    public function store(AdminPasswordResetLinkRequest $request, AdminPasswordResetService $service): RedirectResponse
    {
        Log::debug('AdminPasswordResetController@store start');

        $service->sendResetLink($request->string('email')->lower()->toString());

        Log::debug('AdminPasswordResetController@store end');

        return redirect()->route('admin.password.request')->with('status', trans('passwords.sent'));
    }

    /**
     * 新しいパスワードの設定ページ（メールのリンクの ?email= の値を引き継ぐ）
     */
    public function edit(Request $request, string $token): View
    {
        Log::debug('AdminPasswordResetController@edit start');

        $email = $request->query('email');

        $view = view('auth.admin.reset-password', [
            'token' => $token,
            'email' => is_string($email) ? $email : '',
        ]);

        Log::debug('AdminPasswordResetController@edit end');

        return $view;
    }

    /**
     * 新しいパスワードを設定する
     *
     * @throws ValidationException パスワードが規則に合わないとき
     */
    public function update(AdminNewPasswordRequest $request, AdminPasswordResetService $service): RedirectResponse
    {
        Log::debug('AdminPasswordResetController@update start');

        $confirmation = $request->input('password_confirmation');

        $result = $service->reset(
            $request->string('email')->lower()->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
            is_string($confirmation) ? $confirmation : null,
        );

        Log::debug('AdminPasswordResetController@update end');

        return match ($result) {
            AdminPasswordResetResult::Reset => redirect()->route('admin.login')->with('status', trans('passwords.reset')),
            AdminPasswordResetResult::InvalidLink => back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => trans('passwords.token')]),
        };
    }
}
