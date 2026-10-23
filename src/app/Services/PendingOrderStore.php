<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;

/**
 * 保存待ちの注文をセッションに出し入れする
 *
 * 購入はセッションのキー `pending_order` に、予約は `pending_reservation` に配列で保存する（互いに干渉しない）。
 * 予約用のキーは AppServiceProvider で ReservationService に渡している。
 */
class PendingOrderStore
{
    /** 購入の保存待ちの注文のセッションのキー */
    public const string PURCHASE_SESSION_KEY = 'pending_order';

    /** 予約の保存待ちの注文のセッションのキー */
    public const string RESERVATION_SESSION_KEY = 'pending_reservation';

    public function __construct(
        private readonly Session $session,
        private readonly string $sessionKey = self::PURCHASE_SESSION_KEY,
    ) {}

    /**
     * 保存待ちの注文を取得する（無い・形式が正しくないときは null）
     */
    public function get(): ?PendingOrder
    {
        $stored = $this->session->get($this->sessionKey);

        /** @var array<string, mixed>|null $stored */
        return is_array($stored) ? PendingOrder::fromArray($stored) : null;
    }

    /**
     * 保存待ちの注文を保存する（前の注文は上書きする）
     */
    public function put(PendingOrder $order): void
    {
        $this->session->put($this->sessionKey, $order->toArray());
    }

    /**
     * 保存待ちの注文を消す
     */
    public function forget(): void
    {
        $this->session->forget($this->sessionKey);
    }
}
