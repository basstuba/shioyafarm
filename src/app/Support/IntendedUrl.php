<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Str;

/**
 * ログイン・会員登録の後に戻るページ
 *
 * ヘッダーのリンクからログインページなどを開いたとき、直前に見ていたページを
 * セッションの url.intended に保存する。Fortifyはログイン・会員登録の後に
 * url.intended へ移動し、無いときは config('fortify.home')（商品一覧）へ移動する。
 */
class IntendedUrl
{
    /**
     * 戻り先にしてよいページのルート名
     *
     * ログイン・会員登録ページ自身や管理画面に戻らないよう、公開ページだけにする。
     *
     * @var array<int, string>
     */
    private const array ALLOWED_ROUTES = ['top.*', 'info.*', 'shop.*'];

    /**
     * 直前に見ていたページを、ログイン・会員登録の後に戻るページとして保存する
     *
     * 直前のページが戻り先にできないとき（ログインページで入力エラーになって戻ったときなど）は、
     * すでに保存されている戻り先をそのまま残す。
     */
    public static function rememberPrevious(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        // 直前のルート名（previousRoute）はセッションのインターフェースに無く、Storeだけが持つ
        $session = $request->session();
        if (! $session instanceof Store) {
            return;
        }

        // RefererではなくセッションのURLを使い、外部サイトのURLが入らないようにする
        $previousUrl = $session->previousUrl();
        $previousRoute = $session->previousRoute();

        if ($previousUrl === null || $previousRoute === null || ! Str::is(self::ALLOWED_ROUTES, $previousRoute)) {
            return;
        }

        $session->put('url.intended', $previousUrl);
    }
}
