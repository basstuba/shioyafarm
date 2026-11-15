<?php

namespace App\Support;

/**
 * ページ送りに出すページ番号の選び方（管理画面の admin/partials/pager・マイページの livewire/partials/pager で共通）
 */
final class PageNumbers
{
    /**
     * 現在のページの前後2つと、最初・最後のページの番号を昇順で返す
     *
     * 間が空くところ（番号が連続しない箇所）に「…」を出すのは、呼び出し側のビューが行う。
     *
     * @return list<int>
     */
    public static function visible(int $current, int $last): array
    {
        return array_values(array_filter(
            range(1, $last),
            fn (int $number): bool => $number === 1 || $number === $last || abs($number - $current) <= 2,
        ));
    }
}
