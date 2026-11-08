<?php

namespace App\Support;

use App\Enums\MailRecipientType;

/**
 * 管理画面のメール作成の宛先の指定（宛先の種類とID）
 *
 * メール作成ページの送信フォーム（hidden の type・id）とルートのパラメーターから、同じ判定で作る。
 * メールアドレスは持たない（宛先のメールアドレスは、種類とIDからサーバーで引き直す）。
 */
final readonly class MailTarget
{
    public function __construct(
        public MailRecipientType $type,
        public int $id,
    ) {}

    /**
     * 入力値から作る（種類が文字列で user・inquiry のどちらかでなければ、IDが1以上の整数でなければ null）
     */
    public static function fromInput(mixed $type, mixed $id): ?self
    {
        $recipientType = self::typeFromInput($type);
        $recipientId = is_string($id) || is_int($id) ? filter_var($id, FILTER_VALIDATE_INT) : false;

        return $recipientType === null || $recipientId === false || $recipientId < 1 ? null : new self($recipientType, $recipientId);
    }

    /**
     * 入力値から宛先の種類だけを取り出す（文字列で user・inquiry のどちらかでなければ null）
     */
    public static function typeFromInput(mixed $type): ?MailRecipientType
    {
        return is_string($type) ? MailRecipientType::tryFrom($type) : null;
    }

    /**
     * メール作成ページのURL（相対パス）
     *
     * 戻り先（来た一覧）が使えるときは、クエリの back に付ける。入力エラー・送信失敗・ページの有効期限切れで作成ページに戻したあとに
     * 再読み込みしても、戻り先を失わないため。使えない戻り先（その種類の一覧・検索結果でないURL）は付けない。
     */
    public function createUrl(mixed $back = null): string
    {
        $back = AdminListUrl::tryResolve($back, $this->type->indexRoute(), $this->type->searchRoute());

        return route($this->type->createRoute(), [$this->type->routeParameter() => $this->id] + ($back === null ? [] : ['back' => $back]), false);
    }

    /**
     * 戻り先の一覧のURL（候補のうち、その種類の一覧・検索結果として最初に使えるもの。なければ一覧の1ページ目）
     *
     * 候補は優先する順に渡す（外部のURL・ほかの種類の一覧・不正な値は採用しない）。
     */
    public function listUrl(mixed ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $url = AdminListUrl::tryResolve($candidate, $this->type->indexRoute(), $this->type->searchRoute());

            if ($url !== null) {
                return $url;
            }
        }

        return route($this->type->indexRoute(), absolute: false);
    }
}
