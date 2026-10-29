<?php

namespace App\Livewire\Concerns;

/**
 * カートページ・購入ページ・予約ページの数量の入力欄で共通の、入力値の整え方とエラーメッセージ
 */
trait WithQuantityInput
{
    /**
     * 数量の入力値を検証用に整える（文字列は前後の空白を除き、文字列・整数以外は未入力として扱う）
     */
    private function quantityInput(mixed $quantity): int|string|null
    {
        return is_string($quantity) ? trim($quantity) : (is_int($quantity) ? $quantity : null);
    }

    /**
     * 数量のエラーメッセージに「（数量はN個のままです）」を付ける（変更前の数量が無いときは付けない）
     */
    private function keptQuantityMessage(string $message, ?int $current): string
    {
        return $current === null ? $message : "{$message}（数量は{$current}個のままです）";
    }
}
