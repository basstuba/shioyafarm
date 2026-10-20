<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use App\Services\ItemService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 商品サービスのテスト
 */
class ItemServiceTest extends TestCase
{
    /**
     * 品目ごとにまとめ、品目は最小IDの順、品目内は重さの昇順に並べる
     */
    #[Test]
    public function 品目ごとにまとめて並べ替える(): void
    {
        $items = new Collection([
            new Item(['name' => '玉ねぎ', 'weight' => 10]),
            new Item(['name' => 'じゃがいも', 'weight' => 5]),
            new Item(['name' => '玉ねぎ', 'weight' => 2]),
            new Item(['name' => 'じゃがいも', 'weight' => 2]),
        ]);
        $repository = Mockery::mock(ItemRepositoryInterface::class);
        $repository->shouldReceive('getAllItems')->once()->andReturn($items);

        $groups = (new ItemService($repository))->getItemsGroupedByName();

        $this->assertSame(['玉ねぎ', 'じゃがいも'], $groups->keys()->all());
        $this->assertSame([2, 10], $groups['玉ねぎ']->pluck('weight')->all());
        $this->assertSame([2, 5], $groups['じゃがいも']->pluck('weight')->all());
    }

    /**
     * 商品が0件のときは空のコレクションを返す
     */
    #[Test]
    public function 商品がないときは空を返す(): void
    {
        $repository = Mockery::mock(ItemRepositoryInterface::class);
        $repository->shouldReceive('getAllItems')->once()->andReturn(new Collection);

        $groups = (new ItemService($repository))->getItemsGroupedByName();

        $this->assertTrue($groups->isEmpty());
    }

    /**
     * 商品をIDで1件取得する
     */
    #[Test]
    public function 商品を_i_dで取得する(): void
    {
        $item = new Item(['name' => 'じゃがいも']);
        $repository = Mockery::mock(ItemRepositoryInterface::class);
        $repository->shouldReceive('findById')->with(3)->once()->andReturn($item);

        $this->assertSame($item, (new ItemService($repository))->getItem(3));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
