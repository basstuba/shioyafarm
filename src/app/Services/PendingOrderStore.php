<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;

/**
 * 保存待ちの注文をセッションに出し入れする
 *
 * セッションのキー `pending_order` に配列で保存する。
 */
class PendingOrderStore
{
    /** セッションのキー */
    private const string SESSION_KEY = 'pending_order';

    public function __construct(
        private readonly Session $session,
    ) {}

    /**
     * 保存待ちの注文を取得する（無い・形式が正しくないときは null）
     */
    public function get(): ?PendingOrder
    {
        $stored = $this->session->get(self::SESSION_KEY);

        /** @var array<string, mixed>|null $stored */
        return is_array($stored) ? PendingOrder::fromArray($stored) : null;
    }

    /**
     * 保存待ちの注文を保存する（前の注文は上書きする）
     */
    public function put(PendingOrder $order): void
    {
        $this->session->put(self::SESSION_KEY, $order->toArray());
    }

    /**
     * 保存待ちの注文を消す
     */
    public function forget(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
