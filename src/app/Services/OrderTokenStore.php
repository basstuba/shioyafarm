<?php

namespace App\Services;

use App\Enums\OrderTokenStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * 注文トークン（「注文を確定する」を1回しか受け付けないための、1回限りのトークン）
 *
 * 購入ページを開くたびに発行し、「注文を確定する」（POST /payment）の排他ロックの中で使用済みにする。
 * セッションに持つと、同時に届いたリクエストの書き込みで上書きされるため、サーバー側（キャッシュ）に会員単位で持つ。
 * 複数のタブで購入ページを開いても、タブごとに別のトークンになる。
 * 予約は別の接頭辞（RESERVATION_KEY_PREFIX）で持つ。予約用の接頭辞は AppServiceProvider で ReservationService に渡している。
 */
class OrderTokenStore
{
    /** 購入のトークンのキャッシュのキーの接頭辞 */
    public const string PURCHASE_KEY_PREFIX = 'purchase-order-token';

    /** 予約のトークンのキャッシュのキーの接頭辞 */
    public const string RESERVATION_KEY_PREFIX = 'reservation-order-token';

    public function __construct(
        private readonly string $keyPrefix = self::PURCHASE_KEY_PREFIX,
    ) {}

    /**
     * 新しいトークンを発行する
     */
    public function issue(int $userId): string
    {
        $token = Str::random(40);
        $this->put($userId, $token, OrderTokenStatus::Issued);

        return $token;
    }

    /**
     * トークンの状態を取得する（その会員のものでない・有効期限切れ・不正な値は null）
     */
    public function status(int $userId, string $token): ?OrderTokenStatus
    {
        $stored = Cache::get($this->key($userId, $token));

        return is_string($stored) ? OrderTokenStatus::tryFrom($stored) : null;
    }

    /**
     * トークンを使用済みにする
     */
    public function markUsed(int $userId, string $token): void
    {
        $this->put($userId, $token, OrderTokenStatus::Used);
    }

    /**
     * トークンを「使用済みで、購入データを保存した」状態にする
     */
    public function markCompleted(int $userId, string $token): void
    {
        $this->put($userId, $token, OrderTokenStatus::Completed);
    }

    private function put(int $userId, string $token, OrderTokenStatus $status): void
    {
        Cache::put($this->key($userId, $token), $status->value, now()->addMinutes((int) config('purchase.order_token_ttl_minutes')));
    }

    /**
     * キャッシュのキー（トークンは利用者から送られてくる値なので、そのままキーにせず、ハッシュにする）
     */
    private function key(int $userId, string $token): string
    {
        return $this->keyPrefix.':'.$userId.':'.hash('sha256', $token);
    }
}
