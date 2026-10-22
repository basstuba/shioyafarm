<?php

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 「注文を確定する」の同じセッションの先のリクエストが終わらず、待ちきれなかったとき（セッションの排他。500にしない）。
        // セッションを開始する前の例外のため、セッションやログイン状態を使うレイアウトは使わず、簡単な案内だけを返す
        $exceptions->render(function (LockTimeoutException $e, Request $request) {
            if (! $request->routeIs('shop.checkout.payment')) {
                return null;
            }

            return response('<!DOCTYPE html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ご注文を処理しています | Shioya Farm</title></head><body><p>ただいまご注文を処理しています。しばらく待ってから、購入ページを開き直してください。</p></body></html>', 503);
        });
    })->create();
