<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * パスワード再設定の送信（再設定メールの送信・新しいパスワードの設定）の回数制限
 *
 * Fortifyのルートにはlimiterを設定する項目がないため、web グループに追加し、ルート名で対象を絞る。
 * 上限は FortifyServiceProvider の RateLimiter::for('password-reset') で決める。
 */
class ThrottlePasswordReset
{
    /** 回数制限の対象のルート名 */
    public const array ROUTES = ['password.email', 'password.update'];

    /**
     * 対象のルートだけ、回数制限をかける
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs(...self::ROUTES)) {
            return $next($request);
        }

        return app(ThrottleRequests::class)->handle($request, $next, 'password-reset');
    }
}
