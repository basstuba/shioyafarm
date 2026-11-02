<?php

namespace Tests\Feature;

use App\Services\OrderSummary;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * order-summary（購入・予約ページ共通の金額のカード）のテスト
 */
class OrderSummaryPartialTest extends TestCase
{
    /**
     * 小計・送料・合計が出て、代引手数料が 0 円のときは行を出さない。上の余白は渡したクラスになる
     */
    #[Test]
    public function 代引手数料がないときは行を出さない(): void
    {
        $html = View::make('livewire.partials.order-summary', [
            'summary' => new OrderSummary(9000, 6, 1000, 0),
            'topClass' => 'md:mt-[19px]',
        ])->render();

        $this->assertStringContainsString('<dt>小計</dt><dd>9,000円</dd>', $html);
        $this->assertStringContainsString('<dt>送料</dt><dd>1,000円</dd>', $html);
        $this->assertStringContainsString('<dt>合計</dt><dd>10,000円</dd>', $html);
        $this->assertStringNotContainsString('代引手数料', $html);
        $this->assertStringContainsString('md:mt-[19px]', $html);
    }

    /**
     * 代引手数料があるときは行が増え、合計に含まれる
     */
    #[Test]
    public function 代引手数料があるときは行が出る(): void
    {
        $html = View::make('livewire.partials.order-summary', [
            'summary' => new OrderSummary(9000, 6, 1000, 330),
            'topClass' => 'md:mt-[17px]',
        ])->render();

        $this->assertStringContainsString('<dt>代引手数料</dt><dd>330円</dd>', $html);
        $this->assertStringContainsString('<dt>合計</dt><dd>10,330円</dd>', $html);
        $this->assertStringContainsString('md:mt-[17px]', $html);
    }
}
