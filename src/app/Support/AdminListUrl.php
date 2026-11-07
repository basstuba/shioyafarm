<?php

namespace App\Support;

/**
 * 管理画面の一覧（注文・予約・会員）の「戻り先」の URL
 *
 * 一覧からの操作（会員の物理削除・注文や予約の取消）のあと（成功・失敗とも）、操作の前に見ていた検索条件・ページの一覧へ戻すために使う。
 * 戻り先は直前のページ（Referer またはセッションの前のURL）のパスとクエリだけを見て判定する。
 * パスが一覧・検索結果のときだけ、パス＋クエリの相対URLを返す（それ以外は一覧の全件）。
 * スキームとホストは使わない（プロキシの背後でスキームが違っても動く。リクエストの値で外部のURLへ移動させない）。
 */
final class AdminListUrl
{
    /**
     * 戻り先のURL（直前のページが一覧・検索結果のとき、検索条件とページ番号つきのパス＋クエリ。なければ一覧のパス）
     *
     * @param  string  $indexRoute  一覧のルート名
     * @param  string  $searchRoute  検索のルート名
     */
    public static function previousOrIndex(string $indexRoute, string $searchRoute): string
    {
        $index = route($indexRoute, absolute: false);
        $parts = parse_url(url()->previous($index));

        if (! is_array($parts) || ! in_array($parts['path'] ?? '', [$index, route($searchRoute, absolute: false)], true)) {
            return $index;
        }

        return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
