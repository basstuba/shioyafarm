<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortifyのルート（パスワード再設定の送信・会員登録の送信）の回数制限
 *
 * Fortifyのルートにはlimiterを設定する項目がないため、web グループに追加し、ルート名で対象を絞って、ルートごとのlimiterをかける。
 * 上限は FortifyServiceProvider の RateLimiter::for('password-reset')・RateLimiter::for('register') で決める。
 * 登録ページの表示（GET /register）は制限しない。
 */
class ThrottleFortifyRoutes
{
    /**
     * 回数制限の対象のルート名と、かけるlimiterの名前
     *
     * @var array<string, string>
     */
    public const array LIMITERS = [
        'password.email' => 'password-reset',
        'password.update' => 'password-reset',
        'register.store' => 'register',
    ];

    /**
     * 対象のルートだけ、回数制限をかける
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();
        $limiter = $routeName === null ? null : (self::LIMITERS[$routeName] ?? null);

        if ($limiter === null) {
            return $next($request);
        }

        return app(ThrottleRequests::class)->handle($request, $next, $limiter);
    }
}
