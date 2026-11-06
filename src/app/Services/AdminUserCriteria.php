<?php

namespace App\Services;

use App\Support\SearchName;
use InvalidArgumentException;

/**
 * 管理画面の会員一覧の検索条件（リポジトリにそのまま渡す値）
 *
 * 退会済みかどうかは null のとき絞り込まない。名前は空文字のとき絞り込まない。
 */
final readonly class AdminUserCriteria
{
    /** 検索欄の状態の値：退会していない会員 */
    public const string STATUS_MEMBER = 'member';

    /** 検索欄の状態の値：退会済みの会員 */
    public const string STATUS_WITHDRAWN = 'withdrawn';

    public function __construct(
        public ?bool $withdrawn,
        public string $name,
    ) {}

    /**
     * 検索欄の値（状態は 'member'・'withdrawn'・空、名前）から作る
     *
     * 名前は、半角・全角のスペースを除く（注文一覧・予約一覧と同じ）。
     * 状態が決められた値でないとき、名前が正しい UTF-8 でないときは InvalidArgumentException になる
     * （黙って絞り込みを外すと全件が表示されるため。呼び出す前に入力の検証で弾くこと）。
     *
     * @param  array{status?: string, name?: string}  $conditions
     */
    public static function fromConditions(array $conditions): self
    {
        $status = $conditions['status'] ?? '';

        return new self(
            withdrawn: match ($status) {
                '' => null,
                self::STATUS_MEMBER => false,
                self::STATUS_WITHDRAWN => true,
                default => throw new InvalidArgumentException('状態が決められた値ではありません。'),
            },
            name: SearchName::normalize($conditions['name'] ?? ''),
        );
    }
}
