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
        return self::resolve(url()->previous(route($indexRoute, absolute: false)), $indexRoute, $searchRoute);
    }

    /**
     * 戻り先のURL（渡されたURLが一覧・検索結果のとき、検索条件とページ番号つきのパス＋クエリ。それ以外・不正な値は一覧のパス）
     *
     * メール作成ページの送信フォームのように、画面が持ち回った戻り先（利用者が書き換えられる値）を検証するときにも使う。
     *
     * @param  mixed  $url  直前のページのURL、または以前にこのメソッドが返した戻り先（文字列でないときは一覧）
     * @param  string  $indexRoute  一覧のルート名
     * @param  string  $searchRoute  検索のルート名
     */
    public static function resolve(mixed $url, string $indexRoute, string $searchRoute): string
    {
        return self::tryResolve($url, $indexRoute, $searchRoute) ?? route($indexRoute, absolute: false);
    }

    /**
     * 渡されたURLが一覧・検索結果のときだけ、検索条件とページ番号つきのパス＋クエリを返す（それ以外・不正な値は null）
     *
     * パスが一覧・検索結果のものだけを採用し、スキームとホストは捨てるため、外部のURLへは移動させない。制御文字（改行など）を含む値は採用しない。
     * 複数の候補（持ち回った戻り先・直前のページなど）から、最初に使えるものを選ぶときに使う。
     *
     * @param  string  $indexRoute  一覧のルート名
     * @param  string  $searchRoute  検索のルート名
     */
    public static function tryResolve(mixed $url, string $indexRoute, string $searchRoute): ?string
    {
        if (! is_string($url) || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array($parts['path'] ?? '', [route($indexRoute, absolute: false), route($searchRoute, absolute: false)], true)) {
            return null;
        }

        return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
