<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Notice;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
     * お知らせは作成日の新しい順に最新5件だけ表示される（更新日は順番に関係しない）
     */
    #[Test]
    public function お知らせは新しい順に5件表示される(): void
    {
        foreach (range(1, 6) as $day) {
            Notice::factory()->create([
                'title' => "お知らせ{$day}",
                'created_at' => now()->subDays($day),
                // 更新日は作成日と逆の順（更新日の順で並べると、期待の並びにならない）
                'updated_at' => now()->subDays(10 - $day),
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
     * 作成日が同じ日時のお知らせは、新しく作った順（IDの大きい順）に並ぶ
     */
    #[Test]
    public function 作成日が同じお知らせはid降順に並ぶ(): void
    {
        $at = now()->subDay();
        $first = Notice::factory()->create(['title' => '先に作った', 'created_at' => $at]);
        $second = Notice::factory()->create(['title' => '後に作った', 'created_at' => $at]);

        $response = $this->get(route('top.index'));

        $response->assertViewHas('notices', fn ($notices) => $notices->pluck('id')->all() === [$second->id, $first->id]);
    }

    /**
     * 作成日が空（null）のお知らせがあっても、500にならずにトップが表示され、日付は空になる
     */
    #[Test]
    public function 作成日が空のお知らせがあってもトップが表示される(): void
    {
        $notice = Notice::factory()->create(['title' => '作成日のないお知らせ']);
        DB::table('notices')->where('id', $notice->id)->update(['created_at' => null]);

        $this->get(route('top.index'))
            ->assertOk()
            ->assertSee('作成日のないお知らせ')
            ->assertSee('datetime=""', false);
    }

    /**
     * お知らせの日付は作成日（YYYY-MM-DD。datetime 属性も）で、修正して更新日が変わっても、日付も順番も変わらない
     */
    #[Test]
    public function お知らせの日付は作成日で修正しても変わらない(): void
    {
        $old = Notice::factory()->create(['title' => '古いお知らせ', 'created_at' => '2026-10-01 09:00:00', 'updated_at' => '2026-10-01 09:00:00']);
        Notice::factory()->create(['title' => '新しいお知らせ', 'created_at' => '2026-11-01 09:00:00', 'updated_at' => '2026-11-01 09:00:00']);

        $this->travelTo('2026-11-10 12:00:00');
        $old->update(['title' => '古いお知らせ（修正）']);
        $this->assertSame('2026-11-10', $old->fresh()->updated_at->format('Y-m-d'));

        $response = $this->get(route('top.index'));

        $response->assertViewHas('notices', fn ($notices) => $notices->pluck('title')->all() === ['新しいお知らせ', '古いお知らせ（修正）']);
        $response->assertSee('<time class="shrink-0 text-sm md:text-base" datetime="2026-10-01">', false);
        $response->assertSee('2026-10-01');
        $response->assertSee('2026-11-01');
        $response->assertDontSee('2026-11-10');
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
     * おすすめ商品はおすすめのものだけを最大3件表示する（どの3件かはランダム）
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
            return $items->count() === 3
                && $items->pluck('id')->unique()->count() === 3
                && $items->pluck('id')->diff($recommendItems->pluck('id'))->isEmpty();
        });
        $response->assertDontSee('おすすめでない商品');
    }

    /**
     * おすすめ商品が3件に満たないときは、あるだけ表示する
     */
    #[Test]
    public function おすすめ商品が3件に満たないときはあるだけ表示する(): void
    {
        Item::factory()->create(['name' => 'おすすめでない商品', 'recommend' => false]);
        $recommendItems = Item::factory()->count(2)->create(['recommend' => true]);

        $this->get(route('top.index'))
            ->assertViewHas('recommendItems', fn ($items) => $items->count() === 2 && $items->pluck('id')->diff($recommendItems->pluck('id'))->isEmpty());
    }

    /**
     * おすすめ商品はランダムな順で取得する（ID順の固定ではない）
     */
    #[Test]
    public function おすすめ商品はランダムな順で取得する(): void
    {
        Item::factory()->count(4)->create(['recommend' => true]);
        DB::enableQueryLog();

        app(ItemRepositoryInterface::class)->getRecommendItems(3);

        $sql = strtolower(collect(DB::getQueryLog())->pluck('query')->implode(' '));
        $this->assertStringContainsString('rand', $sql);
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
