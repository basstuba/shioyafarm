<?php

namespace App\View\Composers;

use App\Support\Nav;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class HeaderComposer
{
    /**
     * ビューにデータをバインドする
     */
    public function compose(View $view): void
    {
        // 先に一致したパターンが使われるため、具体的なパターンを先に書く
        $navMap = [
            // トップページ
            'top.*' => Nav::top(),

            // 送料案内・利用規約・プライバシーポリシー・特商法に基づく表記
            'info.*' => Nav::top(),

            // ショップ（カート・購入手続き）
            'shop.cart' => Nav::shopCart(),
            'shop.checkout.*' => Nav::shopCart(),

            // ショップ全般（ログイン済/未ログイン）
            'shop.*' => Auth::check() ? Nav::shopAuth() : Nav::shopGuest(),
        ];

        $nav = collect($navMap)
            ->first(fn ($_, $pattern) => request()->routeIs($pattern), []);

        $view->with('navItems', $nav);
    }
}
