<?php

use App\Http\Middleware\NoStore;
use App\Http\Middleware\ThrottlePasswordReset;
use App\Http\Requests\MailRequest;
use App\Http\Responses\ExpiredPageResponder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // パスワードを変えたとき、ほかの端末のセッションをログアウト状態にする（Auth::logoutOtherDevices の前提）
        $middleware->web(append: [AuthenticateSession::class, ThrottlePasswordReset::class]);

        // メール本文の先頭の字下げ（全角スペースを含む）を消さないよう、管理画面のメール本文の項目（MailRequest::BODY）だけ前後の空白を取り除かない（項目名で除外する。他のフォームの項目には影響しない）。
        // メールタイトルは他の入力と同じく取り除く
        $middleware->trimStrings(except: [MailRequest::BODY]);

        // 応答をブラウザに保存させない（管理者のログインが必要なページ）
        $middleware->alias(['no-store' => NoStore::class]);

        // 管理者としてログイン済みの人が /admin/login を開いたときは、管理画面のトップへ（それ以外は従来どおり /）
        $middleware->redirectUsersTo(fn (Request $request) => $request->routeIs('admin.*') ? route('admin.home') : '/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ログイン・管理者のログアウトでCSRFトークンが古いとき（419）は、案内つきで元のページへ戻す（それ以外は従来どおり）
        $exceptions->render(fn (HttpException $e, Request $request) => (new ExpiredPageResponder)($e, $request));

        // 管理者としてログインしていない人が管理画面を開いたときは、管理者用ログインページへ（会員の /login ではない）。
        // redirect()->guest() を使わない（ログイン後の戻り先 url.intended は会員のログインが使うため、ここでは保存しない）
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($e->guards() !== ['admin'] || $request->expectsJson()) {
                return null;
            }

            return redirect()->route('admin.login');
        });

        // 「注文を確定する」の同じセッションの先のリクエストが終わらず、待ちきれなかったとき（セッションの排他。500にしない）。
        // セッションを開始する前の例外のため、セッションやログイン状態を使うレイアウトは使わず、簡単な案内だけを返す
        $exceptions->render(function (LockTimeoutException $e, Request $request) {
            if (! $request->routeIs('shop.checkout.payment')) {
                return null;
            }

            return response('<!DOCTYPE html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ご注文を処理しています | Shioya Farm</title></head><body><p>ただいまご注文を処理しています。しばらく待ってから、購入ページを開き直してください。</p></body></html>', 503);
        });
    })->create();
