<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 送料案内ページ（/shipping-fee）の配送料金一覧表のテスト。PCは12地域を1つの表、SPは3地域ずつの表4つ
 */
class ShippingFeePageTest extends TestCase
{
    /**
     * 配送料金一覧表の金額のセルを、行（サイズ）→列（地域）の順に取り出す
     *
     * @return list<string>
     */
    private function feeCells(string $html): array
    {
        preg_match_all('/<td class="bg-white[^"]*">([\d,]+)<\/td>/', $html, $matches);

        return $matches[1];
    }

    /**
     * 指定した地域の金額を、サイズごとに地域の順で並べた期待値を作る
     *
     * @param  list<string>  $areaNames
     * @return list<string>
     */
    private function expectedFees(array $areaNames): array
    {
        $expected = [];
        foreach (config('shipping.fees') as $fees) {
            foreach ($areaNames as $area) {
                $expected[] = number_format($fees[$area]);
            }
        }

        return $expected;
    }

    /**
     * 全地域の名前と金額が表示される
     */
    #[Test]
    public function 全地域の料金を表示する(): void
    {
        $response = $this->get(route('info.shipping-fee'))->assertOk();

        foreach (config('shipping.areas') as $area => $prefectures) {
            $response->assertSee($area);
        }
        foreach (config('shipping.fees') as $size => $fees) {
            $response->assertSee($size.'サイズ');
        }
        // 北海道の60サイズ
        $response->assertSee(number_format(config('shipping.fees.60.北海道')));
    }

    /**
     * PC用の表は12地域が1つの表に並び、SP用は3地域ずつの表4つに分かれる（地域と金額の列が対応している）
     */
    #[Test]
    public function p_cは1つの表で_s_pは3地域ずつ4つの表に分かれる(): void
    {
        $html = $this->get(route('info.shipping-fee'))->assertOk()->getContent();

        // PC用（hidden md:block）の表
        $this->assertSame(1, preg_match('/<div class="mb-15 hidden overflow-x-auto md:block">(.*?)<\/div>\s*<div class="mb-15 flex flex-col gap-8 md:hidden">(.*?)<\/section>/s', $html, $parts));
        $areaNames = array_keys(config('shipping.areas'));
        $this->assertSame(1, substr_count($parts[1], '<table'));
        $this->assertSame($this->expectedFees($areaNames), $this->feeCells($parts[1]));

        // SP用（md:hidden）の表：北海道・北東北・南東北／関東・信越・北陸／中部・関西・中国／四国・九州・沖縄
        $this->assertSame(4, substr_count($parts[2], '<table'));
        $tables = array_values(array_filter(preg_split('/(?=<table)/', $parts[2]), fn ($t) => str_starts_with($t, '<table')));
        foreach (array_chunk($areaNames, 3) as $index => $chunk) {
            $this->assertSame($this->expectedFees($chunk), $this->feeCells($tables[$index]));
            $this->assertSame(3, preg_match_all('/<th scope="col"[^>]*>(?!お届け先)/', $tables[$index]));
            foreach ($chunk as $area) {
                $this->assertStringContainsString('>'.$area.'</th>', $tables[$index]);
            }
        }
    }

    /**
     * SP用の表は固定幅（横スクロールが出ない）で、サイズ区分一覧表は1つの表のまま
     */
    #[Test]
    public function s_p用の表は固定幅でサイズ区分一覧表は1つの表(): void
    {
        $html = $this->get(route('info.shipping-fee'))->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'table-fixed'));
        // 配送料金一覧表のPC用1 + SP用4 + サイズ区分一覧表1
        $this->assertSame(6, substr_count($html, '<table'));
    }
}
