<?php

namespace App\Support;

/**
 * ヘッダーナビの項目
 *
 * 各項目の 'sp' => 'bar' は、SP（768px未満）でもヘッダーに出す印。
 * 印の無い項目は、SPでは「メニュー」ボタンで開くパネルに入る（PCではすべて横に並ぶ）。
 */
class Nav
{
    /**
     * SPでもヘッダーに出す項目かどうか
     *
     * 項目の 'sp' => 'bar' で判定する。この判定はここ1か所にまとめる。
     *
     * @param  array<string, mixed>  $item
     */
    public static function isSpBar(array $item): bool
    {
        return ($item['sp'] ?? null) === 'bar';
    }

    /**
     * ナビの項目を「SPでもヘッダーに出す項目」と「SPでメニューに入れる項目」に分ける
     *
     * 元の並び順は保つ。PCではどちらもヘッダーに並べるため、全項目は元の配列を使う。
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>} [ヘッダー用, メニュー用]
     */
    public static function split(array $items): array
    {
        $collection = collect($items);

        return [
            $collection->filter(fn (array $item) => self::isSpBar($item))->values()->all(),
            $collection->reject(fn (array $item) => self::isSpBar($item))->values()->all(),
        ];
    }

    /**
     * トップページ用ナビ
     *
     * @return array<int, array<string, mixed>>
     */
    public static function top(): array
    {
        return [
            [
                'label' => 'お知らせ',
                'url' => route('top.index').'#news',
                'icon' => 'image/icon/notice-header-icon.webp',
            ],
            [
                'label' => 'しおや農園について',
                'url' => route('top.index').'#about',
                'icon' => 'image/icon/team-header-icon.webp',
            ],
            [
                'label' => 'オンラインショップ',
                'url' => route('shop.items.index'),
                'icon' => 'image/icon/cart-header-icon.webp',
                'sp' => 'bar',
            ],
        ];
    }

    /**
     * トップページ用ナビ（ログイン中）
     *
     * ログアウトのリンクは出さない（docs/03-page-list.md）。
     *
     * @return array<int, array<string, mixed>>
     */
    public static function topAuth(): array
    {
        return [
            ...self::top(),
            self::mypageItem(),
        ];
    }

    /**
     * ショップ（未ログイン）
     *
     * @return array<int, array<string, mixed>>
     */
    public static function shopGuest(): array
    {
        return [
            [
                'label' => 'お知らせ',
                'url' => route('top.index').'#news',
                'icon' => 'image/icon/notice-header-icon.webp',
            ],
            [
                'label' => 'しおや農園について',
                'url' => route('top.index').'#about',
                'icon' => 'image/icon/team-header-icon.webp',
            ],
            [
                'label' => 'ログイン',
                'url' => route('login'),
                'icon' => 'image/icon/login-header-icon.webp',
                'sp' => 'bar',
            ],
        ];
    }

    /**
     * ショップ（ログイン中）
     *
     * @return array<int, array<string, mixed>>
     */
    public static function shopAuth(): array
    {
        return [
            [
                'label' => 'お知らせ',
                'url' => route('top.index').'#news',
                'icon' => 'image/icon/notice-header-icon.webp',
            ],
            [
                'label' => 'しおや農園について',
                'url' => route('top.index').'#about',
                'icon' => 'image/icon/team-header-icon.webp',
            ],
            [
                'label' => 'カート',
                // 'url'   => route('shop.cart'),
                'url' => '#', // ルートを設定するまでの仮実装
                'icon' => 'image/icon/cart-header-icon.webp',
                'sp' => 'bar',
            ],
            self::mypageItem(),
            [
                'label' => 'ログアウト',
                'url' => route('logout'),
                'method' => 'post', // ← logout用
                'icon' => 'image/icon/logout-icon.webp',
            ],
        ];
    }

    /**
     * ショップ（カート・購入手続き）
     *
     * @return array<int, array<string, mixed>>
     */
    public static function shopCart(): array
    {
        return [
            [
                'label' => 'お知らせ',
                'url' => route('top.index').'#news',
                'icon' => 'image/icon/notice-header-icon.webp',
            ],
            [
                'label' => 'しおや農園について',
                'url' => route('top.index').'#about',
                'icon' => 'image/icon/team-header-icon.webp',
            ],
            self::mypageItem(),
            [
                'label' => 'ログアウト',
                'url' => route('logout'),
                'method' => 'post', // ← logout用
                'icon' => 'image/icon/logout-icon.webp',
            ],
        ];
    }

    /**
     * ナビの「マイページ」項目（ログイン中のナビで共通）
     *
     * マイページのルートができたら route('mypage') に差し替え、マイページ上では非表示にする。
     *
     * @return array<string, string>
     */
    private static function mypageItem(): array
    {
        return [
            'label' => 'マイページ',
            // 'url'   => route('mypage'),
            'url' => '#', // ルートを設定するまでの仮実装
            'icon' => 'image/icon/id-card-header-icon.webp',
            'sp' => 'bar',
        ];
    }
}
