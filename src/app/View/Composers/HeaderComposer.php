<?php

namespace App\View\Composers;

use App\Support\Nav;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HeaderComposer
{
    /**
     * ビューにデータをバインドする
     */
    public function compose(View $view): void
    {
        $loggedIn = Auth::check();

        // 先に一致したパターンが使われるため、具体的なパターンを先に書く
        $navMap = [
            // トップページ（ログイン中は「マイページ」を出す）
            'top.*' => $loggedIn ? Nav::topAuth() : Nav::top(),

            // 送料案内・利用規約・プライバシーポリシー・特商法に基づく表記（ログイン中は「マイページ」を出す）
            'info.*' => $loggedIn ? Nav::topAuth() : Nav::top(),

            // ショップ（カート・購入手続き）
            'shop.cart' => Nav::shopCart(),
            'shop.checkout.*' => Nav::shopCart(),

            // ショップ全般（ログイン済/未ログイン）
            'shop.*' => $loggedIn ? Nav::shopAuth() : Nav::shopGuest(),
        ];

        $nav = collect($navMap)
            ->first(fn ($_, $pattern) => request()->routeIs($pattern), []);

        $view->with('navItems', $nav);
    }
}
