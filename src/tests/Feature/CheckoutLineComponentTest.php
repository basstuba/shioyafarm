<?php

namespace Tests\Feature;

use App\Models\Item;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * checkout-line コンポーネント（購入・予約ページの商品の行）のテスト
 */
class CheckoutLineComponentTest extends TestCase
{
    private function item(): Item
    {
        return new Item(['name' => '黄金桃', 'weight' => 3, 'price' => 4500, 'image' => 'peach.webp']);
    }

    /**
     * 商品名・単価・金額と、数量の input の属性（id・name・value・min・max・wire:change）が出る
     */
    #[Test]
    public function 商品の情報と数量の入力欄の属性が出る(): void
    {
        $html = Blade::render(
            '<x-checkout-line :item="$item" :amount="9000" input-id="reservation-quantity" name="quantity" :value="2" :min="1" :max="10" change="updateQuantity($event.target.value)" />',
            ['item' => $this->item()],
        );

        $this->assertStringContainsString('黄金桃 3kg', $html);
        $this->assertStringContainsString('4,500円(税込)', $html);
        $this->assertStringContainsString('9,000円', $html);
        $this->assertMatchesRegularExpression('#<input[^>]*id="reservation-quantity"[^>]*name="quantity"[^>]*type="number"[^>]*min="1"[^>]*max="10"[^>]*value="2"#s', $html);
        $this->assertStringContainsString('wire:change="updateQuantity($event.target.value)"', $html);
        $this->assertStringContainsString('<label class="sr-only" for="reservation-quantity">黄金桃 3kg の数量</label>', $html);
        // 式に商品IDを埋め込んでも、そのまま wire:change に渡る
        $withId = Blade::render(
            '<x-checkout-line :item="$item" :amount="1" input-id="q" name="q" :value="1" :min="1" :max="2" change="updateQuantity({{ 7 }}, $event.target.value)" />',
            ['item' => $this->item()],
        );
        $this->assertStringContainsString('wire:change="updateQuantity(7, $event.target.value)"', $withId);
    }

    /**
     * 既定は div で、tag と wire:key などの属性は行の要素に付く
     */
    #[Test]
    public function tagと属性は行の要素に付く(): void
    {
        $div = Blade::render('<x-checkout-line :item="$item" :amount="1" input-id="q" name="q" :value="1" :min="1" :max="2" change="x" />', ['item' => $this->item()]);
        $li = Blade::render('<x-checkout-line tag="li" wire:key="purchase-line-5" :item="$item" :amount="1" input-id="q" name="q" :value="1" :min="1" :max="2" change="x" />', ['item' => $this->item()]);

        $this->assertMatchesRegularExpression('#^\s*<div class="rounded-\[10px\]#', $div);
        $this->assertMatchesRegularExpression('#^\s*<li class="rounded-\[10px\][^>]*wire:key="purchase-line-5"#', $li);
        $this->assertStringContainsString('</li>', $li);
        // wire:key は数量の input には付かない
        $this->assertSame(1, substr_count($li, 'purchase-line-5'));
    }

    /**
     * error を渡すと枠が赤で aria-invalid が付き、渡さないと付かない
     */
    #[Test]
    public function errorで枠が赤になりaria_invalidが付く(): void
    {
        $base = '<x-checkout-line :item="$item" :amount="1" input-id="q" name="q" :value="1" :min="1" :max="2" change="x" :error="%s" />';
        $ok = Blade::render(sprintf($base, 'false'), ['item' => $this->item()]);
        $ng = Blade::render(sprintf($base, 'true'), ['item' => $this->item()]);

        $this->assertStringNotContainsString('aria-invalid', $ok);
        $this->assertStringContainsString('border-line', $ok);
        $this->assertStringNotContainsString('border-danger', $ok);
        $this->assertStringContainsString('aria-invalid="true"', $ng);
        $this->assertStringContainsString('border-danger', $ng);
        $this->assertStringNotContainsString('border-line', $ng);
    }

    /**
     * スロット hidden は行の先頭、messages は行の下に出る
     */
    #[Test]
    public function スロットのhiddenとエラー文が出る(): void
    {
        $html = Blade::render(
            '<x-checkout-line :item="$item" :amount="1" input-id="q" name="q" :value="1" :min="1" :max="2" change="x">'
            .'<x-slot:hidden><input type="hidden" name="items[0][item_id]" value="5"></x-slot:hidden>'
            .'<x-slot:messages><p role="alert">数量が不正です</p></x-slot:messages>'
            .'</x-checkout-line>',
            ['item' => $this->item()],
        );

        $this->assertStringContainsString('<input type="hidden" name="items[0][item_id]" value="5">', $html);
        $this->assertStringContainsString('<p role="alert">数量が不正です</p>', $html);
        $this->assertGreaterThan(strpos($html, 'item_id'), strpos($html, 'type="number"'));
        $this->assertGreaterThan(strpos($html, 'type="number"'), strpos($html, '数量が不正です'));

        $none = Blade::render('<x-checkout-line :item="$item" :amount="1" input-id="q" name="q" :value="1" :min="1" :max="2" change="x" />', ['item' => $this->item()]);
        $this->assertStringNotContainsString('type="hidden"', $none);
        $this->assertStringNotContainsString('role="alert"', $none);
    }
}
