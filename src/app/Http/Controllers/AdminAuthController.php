<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * 管理者のログイン・ログアウト（ログインページの表示は routes/web.php から直接 view を返す）
 *
 * 会員（web ガード）とは別の admin ガードだけを扱う。
 */
class AdminAuthController extends Controller
{
    /**
     * ログイン
     *
     * @throws ValidationException 資格情報が正しくないとき
     */
    public function login(AdminLoginRequest $request): RedirectResponse
    {
        Log::debug('AdminAuthController@login start');

        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::guard('admin')->attempt($credentials)) {
            throw ValidationException::withMessages(['email' => [trans('auth.failed')]])
                ->redirectTo(route('admin.login'));
        }

        // セッションIDは attempt() が作り直し済み（中身は残るので会員のログインも残る）。CSRFトークンも作り直す
        $request->session()->regenerateToken();

        Log::debug('AdminAuthController@login end');

        // url.intended は会員のログインの戻り先として使われているため、ここでは使わない
        return redirect()->route('admin.home');
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
