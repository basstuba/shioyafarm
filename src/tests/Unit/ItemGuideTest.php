<?php

namespace Tests\Unit;

use App\Support\ItemGuide;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 商品一覧ページの「ご案内」の項目のテスト
 */
class ItemGuideTest extends TestCase
{
    /**
     * 項目の見出しと順番
     */
    #[Test]
    public function 項目は5つで順番が決まっている(): void
    {
        $titles = array_column(ItemGuide::sections(), 'title');

        $this->assertSame(['送料について', '支払方法について', 'お届けについて', '保存方法', '返品について'], $titles);
    }

    /**
     * 代引手数料と発送時期は設定の値を差し込む
     */
    #[Test]
    public function 手数料と発送時期は設定から取る(): void
    {
        config(['shipping.cod_fee' => 440, 'shipping.ship_days_text' => '10営業日', 'shipping.reservation_ship_period_text' => '12月中']);

        $sections = ItemGuide::sections();

        $this->assertContains('代金引換の場合は、別途手数料440円（税込）がかかります。', $sections[1]['lines']);
        $this->assertContains('お支払いの確定後、10営業日以内に発送いたします（代金引換はご注文の確定後）。', $sections[2]['lines']);
        $this->assertContains('北の恵みセットは12月中の発送となります。', $sections[2]['lines']);
    }
}
