<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 応答をブラウザに保存させない（Cache-Control: no-store）
 *
 * 管理者のログインが必要なページに付ける。ログアウトしたあとにブラウザの戻るボタンで、管理画面の内容が表示されないようにする。
 */
class NoStore
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
