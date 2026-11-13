<?php

namespace App\Support;

/**
 * 管理画面のメニューのリンク
 *
 * サイドメニュー（layouts/admin/sidebar）と、管理画面トップのカード（admin/index）で共有する。
 * リンクは route() で書く。active は、そのリンクを表示中とみなすパス（お知らせ一覧は検索・修正ページでも、お知らせ作成は作成ページだけ）。
 */
class AdminNav
{
    /**
     * トップページのリンク（サイドメニューの先頭）
     *
     * linkClass は、ホバー・フォーカスで出す下線の色（表の見出しと同じ admin-head）。
     *
     * @return array{label: string, url: string, active: list<string>, linkClass: string}
     */
    public static function home(): array
    {
        return ['label' => 'トップページ', 'url' => route('admin.home'), 'active' => ['admin'], 'linkClass' => 'decoration-admin-head'];
    }

    /**
     * リンクのグループ（各種一覧・作成登録）
     *
     * title の色（titleClass）と、リンクの下線の色（linkClass。管理画面トップのカード内のリンクとサイドメニューのリンクで共有）は @theme の色。active は、そのリンクを表示中とみなすパス（Request::is のパターン）。
     *
     * @return list<array{title: string, titleClass: string, linkClass: string, links: list<array{label: string, url: string, active: list<string>}>}>
     */
    public static function groups(): array
    {
        return [
            [
                'title' => '各種一覧',
                'titleClass' => 'text-accent-hover',
                'linkClass' => 'decoration-accent-hover',
                'links' => [
                    ['label' => '注文一覧', 'url' => route('admin.purchase.index'), 'active' => ['admin/purchase', 'admin/purchase/*']],
                    ['label' => '予約一覧', 'url' => route('admin.reservations.index'), 'active' => ['admin/reservations', 'admin/reservations/*']],
                    ['label' => '会員一覧', 'url' => route('admin.users.index'), 'active' => ['admin/users', 'admin/users/*']],
                    ['label' => 'お問い合わせ一覧', 'url' => route('admin.inquiries.index'), 'active' => ['admin/inquiries', 'admin/inquiries/*']],
                    ['label' => 'お知らせ一覧', 'url' => route('admin.notices.index'), 'active' => ['admin/notices', 'admin/notices/search', 'admin/notices/edit/*']],
                    ['label' => '掲載商品一覧', 'url' => route('admin.items.index'), 'active' => ['admin/items', 'admin/items/edit/*']],
                    ['label' => '資材一覧', 'url' => route('admin.materials.index'), 'active' => ['admin/materials']],
                ],
            ],
            [
                'title' => '作成・登録',
                'titleClass' => 'text-primary-text',
                'linkClass' => 'decoration-primary-text',
                'links' => [
                    ['label' => 'お知らせ作成', 'url' => route('admin.notices.create'), 'active' => ['admin/notices/create']],
                    ['label' => '掲載商品登録', 'url' => route('admin.items.create'), 'active' => ['admin/items/create']],
                ],
            ],
        ];
    }

    /**
     * 表示中のページのリンクかどうか
     *
     * @param  array{label: string, url: string, active: list<string>}  $link
     */
    public static function isCurrent(array $link): bool
    {
        return request()->is(...$link['active']);
    }
}
