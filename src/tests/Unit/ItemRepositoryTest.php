<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Repositories\ItemRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 商品リポジトリのテスト
 */
class ItemRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 全商品をID順に取得する
     */
    #[Test]
    public function 全商品を_i_d順に取得する(): void
    {
        $items = Item::factory()->count(3)->create();

        $result = (new ItemRepository)->getAllItems();

        $this->assertSame($items->pluck('id')->all(), $result->pluck('id')->all());
    }

    /**
     * 商品がないときは空を返す
     */
    #[Test]
    public function 商品がないときは空を返す(): void
    {
        $this->assertTrue((new ItemRepository)->getAllItems()->isEmpty());
    }

    /**
     * IDで商品を1件取得する
     */
    #[Test]
    public function i_dで商品を取得する(): void
    {
        $item = Item::factory()->create(['name' => 'じゃがいも']);

        $this->assertSame($item->id, (new ItemRepository)->findById($item->id)->id);
    }

    /**
     * 存在しないIDではModelNotFoundExceptionになる
     */
    #[Test]
    public function 存在しない_i_dでは例外になる(): void
    {
        $this->expectException(ModelNotFoundException::class);

        (new ItemRepository)->findById(99999);
    }

    /**
     * IDの配列で商品をID順にまとめて取得し、存在しないIDは含めない
     */
    #[Test]
    public function idの配列で商品をまとめて取得する(): void
    {
        $items = Item::factory()->count(3)->create();

        $result = (new ItemRepository)->findByIds([$items[2]->id, $items[0]->id, 99999]);

        $this->assertSame([$items[0]->id, $items[2]->id], $result->pluck('id')->all());
    }

    /**
     * 空の配列を渡すと空を返す
     */
    #[Test]
    public function idの配列が空のときは空を返す(): void
    {
        Item::factory()->create();

        $this->assertTrue((new ItemRepository)->findByIds([])->isEmpty());
    }
}
