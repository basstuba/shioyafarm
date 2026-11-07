<?php

namespace App\Support;

/**
 * 会員一覧の「戻り先」の URL
 *
 * 会員の物理削除のあと（成功・失敗とも）、削除の前に見ていた検索条件・ページの一覧へ戻すために使う。判定は AdminListUrl と共通。
 */
final class AdminUserListUrl
{
    /**
     * 戻り先のURL（直前のページが会員一覧・検索結果のとき、検索条件とページ番号つきのパス＋クエリ。なければ会員一覧のパス）
     */
    public static function previousOrIndex(): string
    {
        return AdminListUrl::previousOrIndex('admin.users.index', 'admin.users.search');
    }
}
