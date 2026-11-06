<?php

namespace App\Support;

/**
 * 会員一覧の「戻り先」の URL
 *
 * 会員の物理削除のあと（成功・失敗とも）、削除の前に見ていた検索条件・ページの一覧へ戻すために使う。
 * 戻り先は直前のページ（Referer またはセッションの前のURL）のパスとクエリだけを見て判定する。
 * パスが会員一覧・会員の検索結果のときだけ、パス＋クエリの相対URLを返す（それ以外は会員一覧の全件）。
 * スキームとホストは使わない（プロキシの背後でスキームが違っても動く。リクエストの値で外部のURLへ移動させない）。
 */
final class AdminUserListUrl
{
    /**
     * 戻り先のURL（直前のページが会員一覧・検索結果のとき、検索条件とページ番号つきのパス＋クエリ。なければ会員一覧のパス）
     */
    public static function previousOrIndex(): string
    {
        $index = route('admin.users.index', absolute: false);
        $parts = parse_url(url()->previous($index));

        if (! is_array($parts) || ! in_array($parts['path'] ?? '', [$index, route('admin.users.search', absolute: false)], true)) {
            return $index;
        }

        return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
