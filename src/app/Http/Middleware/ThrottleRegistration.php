<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * 会員登録の送信（POST /register）の回数制限
 *
 * Fortifyのルートにはlimiterを設定する項目がないため、ThrottlePasswordReset と同じく web グループに追加し、ルート名で対象を絞る。
 * 登録ページの表示（GET /register）は制限しない。上限は FortifyServiceProvider の RateLimiter::for('register') で決める。
 */
class ThrottleRegistration
{
    /** 回数制限の対象のルート名 */
    public const string ROUTE = 'register.store';

    /**
     * 対象のルートだけ、回数制限をかける
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs(self::ROUTE)) {
            return $next($request);
        }

        return app(ThrottleRequests::class)->handle($request, $next, 'register');
    }
}
