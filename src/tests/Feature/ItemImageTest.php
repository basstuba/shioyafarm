<?php

namespace Tests\Feature;

use App\Models\Item;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * item-image コンポーネントのテスト
 */
class ItemImageTest extends TestCase
{
    private function makeItem(bool $reservedOnly): Item
    {
        return new Item([
            'name' => 'じゃがいも',
            'weight' => 2,
            'img_url' => 'image/product/potato-card.webp',
            'is_reserved_only' => $reservedOnly,
        ]);
    }

    /**
     * 高さを渡さないと200px固定（商品一覧の見た目）
     */
    #[Test]
    public function 既定では高さ200pxで出る(): void
    {
        $html = Blade::render('<x-item-image :item="$item" />', ['item' => $this->makeItem(false)]);

        $this->assertStringContainsString('h-[200px]', $html);
        $this->assertStringContainsString('alt="じゃがいも 2kg"', $html);
    }

    /**
     * 比率を渡すと200px固定にならない
     */
    #[Test]
    public function 比率を渡すと高さ固定をやめる(): void
    {
        $html = Blade::render('<x-item-image :item="$item" img-class="aspect-[460/300]" />', ['item' => $this->makeItem(false)]);

        $this->assertStringContainsString('aspect-[460/300]', $html);
        $this->assertStringNotContainsString('h-[200px]', $html);
    }

    /**
     * 予約限定の商品はバッジを出し、badge を false にすると出さない
     */
    #[Test]
    public function 予約限定のバッジは指定で消せる(): void
    {
        $item = $this->makeItem(true);

        $this->assertStringContainsString('予約限定', Blade::render('<x-item-image :item="$item" />', ['item' => $item]));
        $this->assertStringNotContainsString('予約限定', Blade::render('<x-item-image :item="$item" :badge="false" />', ['item' => $item]));
    }
}
