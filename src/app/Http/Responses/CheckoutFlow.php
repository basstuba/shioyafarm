<?php

namespace App\Http\Responses;

use App\Services\PurchaseService;
use App\Services\ReservationService;

/**
 * 購入・予約の手続きで、応答の違い（ルート名・フラッシュのキー・文言・戻り先）をまとめた設定
 *
 * CheckoutResponder に渡す。購入（shop.checkout.*）と予約（shop.reservation.*）の違いは、ここだけで表す。
 */
final readonly class CheckoutFlow
{
    public function __construct(
        /** ルート名の共通部分（`{routePrefix}.index` / `.thanks` / `.bank-transfer` がある） */
        public string $routePrefix,
        /** サンクスページを「保存した直後の1回だけ」表示する合図（セッションのフラッシュのキー） */
        public string $thanksFlashKey,
        /** 購入・予約ページで保存待ちの入力を復元する合図（セッションのフラッシュのキー） */
        public string $restoreFlashKey,
        /** 完了ページのビュー名 */
        public string $thanksView,
        /** メッセージ中の呼び方（「ご注文」「ご予約」） */
        public string $orderNoun,
        /** 確定ボタンの表示名（「注文を確定する」「予約を確定する」） */
        public string $confirmLabel,
        /** 購入・予約ページが商品IDを必須とするか（必須のときは、保存待ちの注文がなければトップページへ戻す） */
        public bool $itemRequired,
    ) {}

    /**
     * 購入（カートの中身、または商品1個からの購入）
     */
    public static function purchase(): self
    {
        return new self(
            'shop.checkout',
            PurchaseService::THANKS_FLASH_KEY,
            PurchaseService::RESTORE_FLASH_KEY,
            'shop.purchase_thanks',
            'ご注文',
            '注文を確定する',
            false,
        );
    }

    /**
     * 予約（予約限定の商品1つ）
     */
    public static function reservation(): self
    {
        return new self(
            'shop.reservation',
            ReservationService::THANKS_FLASH_KEY,
            ReservationService::RESTORE_FLASH_KEY,
            'shop.reservation_thanks',
            'ご予約',
            '予約を確定する',
            true,
        );
    }

    /**
     * このフローのルート名を返す（例：`route('index')` → `shop.checkout.index`）
     */
    public function route(string $name): string
    {
        return $this->routePrefix.'.'.$name;
    }
}
