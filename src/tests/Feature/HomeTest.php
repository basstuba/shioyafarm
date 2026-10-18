<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Notice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * トップページのテスト
 */
class HomeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * トップページが表示される
     */
    #[Test]
    public function トップページが表示される(): void
    {
        $response = $this->get(route('top.index'));

        $response->assertOk();
        $response->assertViewIs('index');
    }

    /**
     * お知らせは更新日の新しい順に最新5件だけ表示される
     */
    #[Test]
    public function お知らせは新しい順に5件表示される(): void
    {
        foreach (range(1, 6) as $day) {
            Notice::factory()->create([
                'title' => "お知らせ{$day}",
                'updated_at' => now()->subDays($day),
            ]);
        }

        $response = $this->get(route('top.index'));

        $response->assertViewHas('notices', function ($notices) {
            return $notices->pluck('title')->all() === ['お知らせ1', 'お知らせ2', 'お知らせ3', 'お知らせ4', 'お知らせ5'];
        });
        $response->assertSeeInOrder(['お知らせ1', 'お知らせ5']);
        $response->assertDontSee('お知らせ6');
    }

    /**
     * お知らせがないときはその旨を表示する
     */
    #[Test]
    public function お知らせがないときはメッセージを表示する(): void
    {
        $response = $this->get(route('top.index'));

        $response->assertSee('現在お知らせはありません。');
    }

    /**
     * おすすめ商品はおすすめのものだけをID順に3件表示する
     */
    #[Test]
    public function おすすめ商品は3件表示される(): void
    {
        Item::factory()->create(['name' => 'おすすめでない商品', 'recommend' => false]);
        $recommendItems = Item::factory()
            ->count(4)
            ->sequence(fn ($sequence) => ['name' => 'おすすめ商品'.($sequence->index + 1)])
            ->create(['recommend' => true]);

        $response = $this->get(route('top.index'));

        $response->assertViewHas('recommendItems', function ($items) use ($recommendItems) {
            return $items->pluck('id')->all() === $recommendItems->take(3)->pluck('id')->all();
        });
        $response->assertDontSee('おすすめでない商品');
        $response->assertDontSee('おすすめ商品4');
    }

    /**
     * 予約限定の商品にはバッジを表示する
     */
    #[Test]
    public function 予約限定の商品にはバッジを表示する(): void
    {
        Item::factory()->create(['recommend' => true, 'is_reserved_only' => false]);

        $this->get(route('top.index'))->assertDontSee('予約限定');

        Item::factory()->create(['recommend' => true, 'is_reserved_only' => true]);

        $this->get(route('top.index'))->assertSee('予約限定');
    }

    /**
     * 商品名・重さ・税込価格を表示する
     */
    #[Test]
    public function 商品の名前と重さと価格を表示する(): void
    {
        Item::factory()->create([
            'name' => 'じゃがいも',
            'weight' => 5,
            'price' => 1450,
            'recommend' => true,
        ]);

        $response = $this->get(route('top.index'));

        $response->assertSee('じゃがいも 5kg');
        $response->assertSee('1,450円(税込)');
    }
}
