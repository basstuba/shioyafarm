<?php

namespace App\Support;

/**
 * 商品一覧のご案内（PCは6列に各項目2列ぶん＝1行に3項目）で、最終行の項目を中央に寄せるクラス
 *
 * 最終行が3項目（割り切れる）なら何も付けず、2項目なら最初の項目を2列目から、1項目なら3列目から置く。
 * Tailwind はクラス名を静的に拾うため、文字列を連結せずクラス名をそのまま書く。
 */
final class LastRowCenter
{
    /**
     * PCで1行に並べる項目数
     */
    private const PER_ROW = 3;

    /**
     * 項目につけるクラス（最終行の最初の項目だけ。それ以外は null）
     *
     * @param  int  $position  項目の位置（1から数える）
     * @param  int  $total  項目の総数
     */
    public static function startClass(int $position, int $total): ?string
    {
        $lastRowCount = $total % self::PER_ROW;

        if ($lastRowCount === 0 || $position !== $total - $lastRowCount + 1) {
            return null;
        }

        return $lastRowCount === 2 ? 'lg:col-start-2' : 'lg:col-start-3';
    }
}
