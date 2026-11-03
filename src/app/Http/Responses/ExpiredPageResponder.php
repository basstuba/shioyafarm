<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CSRFトークンの不一致（TokenMismatchException。描画の前に 419 の HttpException になる）のとき、ログイン・ログアウトだけは、案内つきで元のページへ戻す
 *
 * 同じブラウザで別の窓のログイン・ログアウトをしたときや、時間が経ったときにトークンが古くなる。
 * 対象は会員のログイン（login.store）・管理者のログイン（admin.login.store）・管理者のログアウト（admin.logout）だけで、
 * それ以外のリクエスト（JSONを期待するリクエスト、Livewireの更新を含む）は null を返して従来どおり419のページにする。
 * 案内は flash の status に入れ、各ページがフォームの上に表示する。
 */
class ExpiredPageResponder
{
    /** ログインのときの案内 */
    public const string LOGIN_MESSAGE = 'ページの有効期限が切れました。もう一度ログインしてください。';

    /** ログアウトのときの案内 */
    public const string LOGOUT_MESSAGE = 'ページの有効期限が切れました。もう一度ログアウトしてください。';

    /**
     * 戻す応答を作る（対象でなければ null）
     */
    public function __invoke(HttpException $e, Request $request): ?Response
    {
        // Laravel は TokenMismatchException を、描画の前に 419 の HttpException に変える
        if ($e->getStatusCode() !== 419 || $request->expectsJson() || $request->hasHeader('X-Livewire')) {
            return null;
        }

        return match ($request->route()?->getName()) {
            'login.store' => $this->backToLogin($request, 'login'),
            'admin.login.store' => $this->backToLogin($request, 'admin.login'),
            'admin.logout' => $this->backFromLogout(),
            default => null,
        };
    }

    /**
     * ログインページへ戻す（メールアドレスは残し、パスワードは残さない）
     */
    private function backToLogin(Request $request, string $route): Response
    {
        $email = $request->input('email');

        return redirect()->route($route)
            ->withInput(['email' => is_string($email) ? $email : ''])
            ->with('status', self::LOGIN_MESSAGE);
    }

    /**
     * 管理者がまだログイン中なら管理画面のトップへ、そうでなければログインページへ戻す
     */
    private function backFromLogout(): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        return redirect()->route('admin.home')->with('status', self::LOGOUT_MESSAGE);
    }
}
