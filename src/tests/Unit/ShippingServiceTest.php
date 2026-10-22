<?php

namespace Tests\Unit;

use App\Services\ShippingService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 送料計算サービスのテスト（料金は config/shipping.php から組み立てる）
 */
class ShippingServiceTest extends TestCase
{
    private ShippingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ShippingService;
    }

    /**
     * 箱サイズと地域から、設定どおりの送料を返す
     */
    private function fee(int $size, string $area): int
    {
        return config("shipping.fees.{$size}.{$area}");
    }

    /**
     * 重量から箱サイズへの境界値（max_weight 以下のうち最小のサイズ）
     *
     * @return array<string, array{int|float, int}>
     */
    public static function 重量とサイズの境界値(): array
    {
        return [
            '2kgは60サイズ' => [2, 60],
            '2.1kgは80サイズ' => [2.1, 80],
            '5kgは80サイズ' => [5, 80],
            '5.5kgは100サイズ' => [5.5, 100],
            '10kgは100サイズ' => [10, 100],
            '11kgは120サイズ' => [11, 120],
            '15kgは120サイズ' => [15, 120],
            '20kgは140サイズ' => [20, 140],
            '25kgは160サイズ' => [25, 160],
            '30kgは180サイズ' => [30, 180],
        ];
    }

    #[Test]
    #[DataProvider('重量とサイズの境界値')]
    public function 重量から箱サイズを選ぶ(int|float $weight, int $expectedSize): void
    {
        $this->assertSame($expectedSize, $this->service->sizeFor($weight));
    }

    /**
     * 北海道の11kgは120サイズで1,850円（シナリオ S-2）
     */
    #[Test]
    public function 北海道の11kgは1850円(): void
    {
        $this->assertSame(1850, $this->service->calculate('北海道', 11));
        $this->assertSame($this->fee(120, '北海道'), $this->service->calculate('北海道', 11));
    }

    /**
     * 同じ11kgでも東京都（関東）は2,370円（シナリオ S-7）
     */
    #[Test]
    public function 東京都の11kgは2370円(): void
    {
        $this->assertSame(2370, $this->service->calculate('東京都', 11));
    }

    /**
     * 沖縄県の2kgは60サイズで2,340円（シナリオ S-9）
     */
    #[Test]
    public function 沖縄県の2kgは2340円(): void
    {
        $this->assertSame(2340, $this->service->calculate('沖縄県', 2));
    }

    /**
     * 北海道の2kgは60サイズで940円（シナリオ S-4）
     */
    #[Test]
    public function 北海道の2kgは940円(): void
    {
        $this->assertSame(940, $this->service->calculate('北海道', 2));
    }

    /**
     * 2kgを少しでも超えると80サイズになる
     */
    #[Test]
    public function 二キロを超えると次のサイズになる(): void
    {
        $this->assertSame($this->fee(60, '北海道'), $this->service->calculate('北海道', 2));
        $this->assertSame($this->fee(80, '北海道'), $this->service->calculate('北海道', 2.5));
        $this->assertSame($this->fee(80, '北海道'), $this->service->calculate('北海道', 3));
    }

    /**
     * 30kgちょうどは1個口（180サイズ）
     */
    #[Test]
    public function 三十キロちょうどは一個口(): void
    {
        $this->assertSame($this->fee(180, '北海道'), $this->service->calculate('北海道', 30));
    }

    /**
     * 30kgを超えると2個口：1個目30kg（180サイズ）+ 残り。35kg北海道は 3,060 + 1,230 = 4,290円（シナリオ S-8）
     */
    #[Test]
    public function 三十キロを超えると二個口になる(): void
    {
        $this->assertSame(4290, $this->service->calculate('北海道', 35));
        $this->assertSame($this->fee(180, '北海道') + $this->fee(80, '北海道'), $this->service->calculate('北海道', 35));
        // 31kgは 180サイズ(30kg) + 60サイズ(1kg)
        $this->assertSame($this->fee(180, '北海道') + $this->fee(60, '北海道'), $this->service->calculate('北海道', 31));
    }

    /**
     * 60kgを超える場合も30kgずつの個口に分けて合計する
     */
    #[Test]
    public function 六十キロを超えると三個口になる(): void
    {
        $this->assertSame($this->fee(180, '関東') * 2 + $this->fee(60, '関東'), $this->service->calculate('東京都', 61));
    }

    /**
     * 重量が0のときは送料もかからない
     */
    #[Test]
    public function 重量が0なら送料は0円(): void
    {
        $this->assertSame(0, $this->service->calculate('北海道', 0));
    }

    /**
     * 全ての都道府県が、設定上のいずれかの地域の料金で計算できる
     */
    #[Test]
    public function 全ての都道府県で送料を計算できる(): void
    {
        foreach (config('shipping.prefecture_to_area') as $prefecture => $area) {
            $this->assertSame($this->fee(120, $area), $this->service->calculate($prefecture, 11), $prefecture);
        }
        $this->assertCount(47, config('shipping.prefecture_to_area'));
    }

    /**
     * 地域の分かれ目の代表（北東北・南東北・信越・北陸・中部・関西・中国・四国・九州）
     *
     * @return array<string, array{string, string}>
     */
    public static function 都道府県と地域(): array
    {
        return [
            '青森県は北東北' => ['青森県', '北東北'],
            '宮城県は南東北' => ['宮城県', '南東北'],
            '長野県は信越' => ['長野県', '信越'],
            '石川県は北陸' => ['石川県', '北陸'],
            '愛知県は中部' => ['愛知県', '中部'],
            '大阪府は関西' => ['大阪府', '関西'],
            '広島県は中国' => ['広島県', '中国'],
            '愛媛県は四国' => ['愛媛県', '四国'],
            '福岡県は九州' => ['福岡県', '九州'],
        ];
    }

    #[Test]
    #[DataProvider('都道府県と地域')]
    public function 都道府県の地域の料金で計算する(string $prefecture, string $area): void
    {
        $this->assertSame($this->fee(100, $area), $this->service->calculate($prefecture, 10));
    }

    /**
     * 地域に対応していない都道府県は例外
     */
    #[Test]
    public function 地域がない都道府県は例外(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->calculate('存在県', 5);
    }
}
