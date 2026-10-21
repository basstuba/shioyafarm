<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * 送料計算サービス
 *
 * 料金は config/shipping.php から取得する（都道府県 → 地域、合計重量 → 箱サイズ、地域 × サイズ → 送料）。
 * 購入・予約のどちらからも使う。
 */
class ShippingService
{
    /**
     * 送料を計算する
     *
     * 合計重量が最大サイズの上限（30kg）を超える場合は、30kgずつの個口に分けて、それぞれの送料を合計する
     * （例：35kg → 180サイズ（30kg）＋ 80サイズ（5kg））。
     *
     * @param  string  $prefecture  届け先の都道府県
     * @param  int|float  $totalWeight  商品の合計重量（kg）
     *
     * @throws InvalidArgumentException 都道府県が地域に対応していないとき
     */
    public function calculate(string $prefecture, int|float $totalWeight): int
    {
        $area = $this->areaOf($prefecture);
        $maxWeight = $this->maxWeight();

        $fee = 0;
        $remaining = $totalWeight;
        while ($remaining > $maxWeight) {
            $fee += $this->feeFor($this->sizeFor($maxWeight), $area);
            $remaining -= $maxWeight;
        }

        if ($remaining > 0) {
            $fee += $this->feeFor($this->sizeFor($remaining), $area);
        }

        return $fee;
    }

    /**
     * 1個口の重量から箱サイズを求める（max_weight が重量以上になる最初のサイズ）
     */
    public function sizeFor(int|float $weight): int
    {
        /** @var array<int, array{max_weight: int, label: string}> $sizes */
        $sizes = config('shipping.sizes');
        ksort($sizes);

        foreach ($sizes as $size => $definition) {
            if ($definition['max_weight'] >= $weight) {
                return $size;
            }
        }

        // 最大サイズの上限を超える重量は、呼び出し側で個口に分けてから渡す
        return array_key_last($sizes);
    }

    /**
     * 都道府県から地域を求める
     *
     * @throws InvalidArgumentException 地域に対応していない都道府県のとき
     */
    private function areaOf(string $prefecture): string
    {
        /** @var array<string, string> $map */
        $map = config('shipping.prefecture_to_area');

        return $map[$prefecture] ?? throw new InvalidArgumentException("送料の地域が見つかりません：{$prefecture}");
    }

    /**
     * 箱サイズ × 地域の送料
     */
    private function feeFor(int $size, string $area): int
    {
        /** @var int $fee */
        $fee = config("shipping.fees.{$size}.{$area}");

        return $fee;
    }

    /**
     * 最大サイズの重量の上限（kg）
     */
    private function maxWeight(): int
    {
        /** @var array<int, array{max_weight: int, label: string}> $sizes */
        $sizes = config('shipping.sizes');

        return max(array_column($sizes, 'max_weight'));
    }
}
