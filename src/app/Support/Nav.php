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
                // 'url'   => '#news',
                'url' => '#news',
                'icon'  => 'image/icon/notice-header-icon.webp',
            ],
            [
                'label' => 'しおや農園について',
                // 'url'   => '#about',
                'url' => '#about',
                'icon'  => 'image/icon/team-header-icon.webp',
            ],
            [
                'label' => 'オンラインショップ',
                // 'url'   => route('shop.products'),
                'url' => '#', // ルートを設定するまでの仮実装
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
                // 'url'   => route('top.index') . '#news',
                'url' => route('top.index') . '#news',
                'icon'  => 'image/icon/notice-header-icon.webp',
            ],
            [
                'label' => 'しおや農園について',
                // 'url'   => route('top.index') . '#about',
                'url' => route('top.index') . '#about',
                'icon'  => 'image/icon/team-header-icon.webp',
            ],
            [
                'label' => 'ログイン',
                // 'url'   => route('login'),
                'url' => '#', // ルートを設定するまでの仮実装
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
                // 'url'   => route('top.index') . '#news',
                'url' => route('top.index') . '#news',
                'icon'  => 'image/icon/notice-header-icon.webp',
            ],
            [
                'label' => 'しおや農園について',
                // 'url'   => route('top.index') . '#about',
                'url' => route('top.index') . '#about',
                'icon'  => 'image/icon/team-header-icon.webp',
            ],
            [
                'label' => 'カート',
                // 'url'   => route('shop.cart'),
                'url' => '#', // ルートを設定するまでの仮実装
                'icon'  => 'image/icon/cart-header-icon.webp',
            ],
            [
                'label' => 'マイページ',
                // 'url'   => route('mypage'),
                'url' => '#', // ルートを設定するまでの仮実装
                'icon'  => 'image/icon/id-card-header-icon.webp',
            ],
            [
                'label' => 'ログアウト',
                // 'url'   => route('logout'),
                'url' => '#', // ルートを設定するまでの仮実装
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
                // 'url'   => route('top.index') . '#news',
                'url' => route('top.index') . '#news',
                'icon'  => 'image/icon/notice-header-icon.webp',
            ],
            [
                'label' => 'しおや農園について',
                // 'url'   => route('top.index') . '#about',
                'url' => route('top.index') . '#about',
                'icon'  => 'image/icon/team-header-icon.webp',
            ],
            [
                'label' => 'マイページ',
                // 'url'   => route('mypage'),
                'url' => '#', // ルートを設定するまでの仮実装
                'icon'  => 'image/icon/id-card-header-icon.webp',
            ],
            [
                'label' => 'ログアウト',
                // 'url'   => route('logout'),
                'url' => '#', // ルートを設定するまでの仮実装
                'method'=> 'post', // ← logout用
                'icon'  => 'image/icon/logout-icon.webp',
            ],
        ];
    }
}
