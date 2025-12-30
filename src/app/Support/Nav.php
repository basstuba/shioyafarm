<?php

namespace App\Support;

class Nav
{
    /**
     * トップページ用ナビ
     */
    public static function top(): array
    {
        return [
            [
                'label' => 'お知らせ',
                'url'   => '#news',
                'icon'  => 'image/icon/notice-header-icon.webp',
            ],
            [
                'label' => 'しおや農園について',
                'url'   => '#about',
                'icon'  => 'image/icon/team-header-icon.webp',
            ],
            [
                'label' => 'オンラインショップ',
                'url'   => route('shop.products'),
                'icon'  => 'image/icon/cart-header-icon.webp',
            ],
        ];
    }

    /**
     * ショップ（未ログイン）
     */
    public static function shopGuest(): array
    {
        return [
            [
                'label' => 'お知らせ',
                'url'   => route('top') . '#news',
                'icon'  => 'image/icon/notice-header-icon.webp',
            ],
            [
                'label' => 'しおや農園について',
                'url'   => route('top') . '#about',
                'icon'  => 'image/icon/team-header-icon.webp',
            ],
            [
                'label' => 'ログイン',
                'url'   => route('login'),
                'icon'  => 'image/icon/login-header-icon.webp',
            ],
        ];
    }

    /**
     * ショップ（ログイン中）
     */
    public static function shopAuth(): array
    {
        return [
            [
                'label' => 'お知らせ',
                'url'   => route('top') . '#news',
                'icon'  => 'image/icon/notice-header-icon.webp',
            ],
            [
                'label' => 'しおや農園について',
                'url'   => route('top') . '#about',
                'icon'  => 'image/icon/team-header-icon.webp',
            ],
            [
                'label' => 'カート',
                'url'   => route('shop.cart'),
                'icon'  => 'image/icon/cart-header-icon.webp',
            ],
            [
                'label' => 'マイページ',
                'url'   => route('mypage'),
                'icon'  => 'image/icon/id-card-header-icon.webp',
            ],
            [
                'label' => 'ログアウト',
                'url'   => route('logout'),
                'method'=> 'post', // ← logout用
                'icon'  => 'image/icon/logout-icon.webp',
            ],
        ];
    }

    /**
     * ショップ（カート・購入手続き）
     */
    public static function shopCart(): array
    {
        return [
            [
                'label' => 'お知らせ',
                'url'   => route('top') . '#news',
                'icon'  => 'image/icon/notice-header-icon.webp',
            ],
            [
                'label' => 'しおや農園について',
                'url'   => route('top') . '#about',
                'icon'  => 'image/icon/team-header-icon.webp',
            ],
            [
                'label' => 'マイページ',
                'url'   => route('mypage'),
                'icon'  => 'image/icon/id-card-header-icon.webp',
            ],
            [
                'label' => 'ログアウト',
                'url'   => route('logout'),
                'method'=> 'post', // ← logout用
                'icon'  => 'image/icon/logout-icon.webp',
            ],
        ];
    }
}
