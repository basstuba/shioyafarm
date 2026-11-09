<?php

namespace Tests\Feature;

use App\Livewire\AdminItemDetail;
use App\Models\Item;
use App\Models\Reservation;
use App\Services\ReservationLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Constraints\SeeInOrder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の掲載商品一覧の詳細モーダル（Livewire の AdminItemDetail）のテスト
 */
class AdminItemDetailTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 商品の詳細を開く（一覧の「詳細」ボタンと同じ、open-item-detail のイベント）
     */
    private function openFor(Item $item): Testable
    {
        return Livewire::test(AdminItemDetail::class)->dispatch('open-item-detail', id: $item->id);
    }

    /**
     * 通常の商品
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeNormal(array $overrides = []): Item
    {
        return Item::factory()->create([
            'name' => 'じゃがいも', 'weight' => 5, 'price' => 1320, 'img_url' => 'image/product/potato-card.webp',
            'detail' => "ほくほくです。\n煮物にどうぞ。", 'recommend' => true, 'is_reserved_only' => false, 'reservation_limit' => null,
            ...$overrides,
        ]);
    }

    /**
     * 開く前は、モーダルを出さない
     */
    #[Test]
    public function 開く前はモーダルを表示しない(): void
    {
        $this->loginAsAdmin();

        Livewire::test(AdminItemDetail::class)
            ->assertSet('showDetail', false)
            ->assertDontSee('商品紹介文');
    }

    /**
     * 8項目が、決められた順に出る
     */
    #[Test]
    public function 詳細は八項目が決められた順に表示される(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeNormal();

        $component = $this->openFor($item)
            ->assertSet('showDetail', true)
            ->assertSet('detailId', $item->id);

        $html = $component->html();
        $this->assertThat([
            '商品名', 'じゃがいも',
            '商品重量（kg）', '5',
            '商品金額（円・税込）', '1,320',
            '商品画像', 'storage/image/product/potato-card.webp',
            '商品紹介文', "ほくほくです。\n煮物にどうぞ。",
            'おすすめ商品掲載', '掲載',
            '通常/予約限定', '通常',
            '予約可能数', '無',
            '閉じる',
        ], new SeeInOrder($html));
        $this->assertSame(8, substr_count($html, '<dt '));
        // 画像は予約限定のバッジを付けず、幅いっぱいで縦横比を保つ（高さは自動。固定の高さ・幅で切り抜かない）。alt は商品名と重さ
        $this->assertStringContainsString('alt="じゃがいも 5kg"', $html);
        $this->assertStringContainsString('class="h-auto w-full object-cover"', $html);
        $this->assertStringNotContainsString('h-[50px]', $html);
        $this->assertStringNotContainsString('w-[85px]', $html);
        $this->assertStringNotContainsString('予約限定</span>', $html);
        // 紹介文は改行をそのまま表示し（nl2br ではなく CSS）、長いときは枠の中をスクロールする。閉じる・背景・Esc
        $this->assertStringContainsString('whitespace-pre-wrap', $html);
        $this->assertStringContainsString('overflow-y-auto', $html);
        $this->assertStringNotContainsString('<br', $html);
        $this->assertStringContainsString('$wire.showDetail = false', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window', $html);
    }

    /**
     * おすすめ商品掲載は「掲載 / 未掲載」
     */
    #[Test]
    public function おすすめは掲載と未掲載で表示される(): void
    {
        $this->loginAsAdmin();

        $this->assertThat(['おすすめ商品掲載', '掲載', '通常/予約限定'], new SeeInOrder($this->openFor($this->makeNormal(['recommend' => true]))->html()));
        $html = $this->openFor($this->makeNormal(['recommend' => false]))->html();
        $this->assertThat(['おすすめ商品掲載', '未掲載', '通常/予約限定'], new SeeInOrder($html));
    }

    /**
     * 予約限定で上限がある商品は「上限（残り◯）」で、残りは公開側の予約ページと同じ数（取り消した予約は数えない）
     */
    #[Test]
    public function 予約限定で上限ありは残りつきで表示される(): void
    {
        $this->loginAsAdmin();
        $item = Item::factory()->create(['name' => '北の恵みセット', 'weight' => 10, 'price' => 1450, 'is_reserved_only' => true, 'reservation_limit' => 200]);
        Reservation::factory()->create(['item_id' => $item->id, 'quantity' => 30]);
        Reservation::factory()->create(['item_id' => $item->id, 'quantity' => 20]);
        Reservation::factory()->create(['item_id' => $item->id, 'quantity' => 99, 'canceled_at' => '2026-11-05 10:00:00']);

        $html = $this->openFor($item)->html();

        $this->assertSame(150, app(ReservationLimitService::class)->remaining($item));
        $this->assertThat(['通常/予約限定', '予約限定', '予約可能数', '200（残り150）'], new SeeInOrder($html));
    }

    /**
     * 予約限定で上限に達していれば残り0
     */
    #[Test]
    public function 上限に達していれば残りは0になる(): void
    {
        $this->loginAsAdmin();
        $item = Item::factory()->create(['is_reserved_only' => true, 'reservation_limit' => 10]);
        Reservation::factory()->create(['item_id' => $item->id, 'quantity' => 12]);

        $this->assertStringContainsString('10（残り0）', $this->openFor($item)->html());
    }

    /**
     * 予約限定で上限がない商品は「上限なし」
     */
    #[Test]
    public function 予約限定で上限なしは上限なしと表示される(): void
    {
        $this->loginAsAdmin();
        $item = Item::factory()->create(['is_reserved_only' => true, 'reservation_limit' => null]);

        $html = $this->openFor($item)->html();

        $this->assertThat(['予約限定', '予約可能数', '上限なし'], new SeeInOrder($html));
        $this->assertStringNotContainsString('残り', $html);
    }

    /**
     * 通常の商品は、上限が入っていても予約可能数は「無」
     */
    #[Test]
    public function 通常の商品は予約可能数が無になる(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeNormal(['reservation_limit' => 50]);

        $html = $this->openFor($item)->html();

        $this->assertThat(['予約可能数', '無'], new SeeInOrder($html));
        $this->assertStringNotContainsString('残り', $html);
    }

    /**
     * HTML の記号は文字のまま表示される
     */
    #[Test]
    public function html記号はエスケープされる(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeNormal(['name' => '<b>x</b>', 'detail' => '<script>alert(1)</script>']);

        $html = $this->openFor($item)->html();

        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
    }

    /**
     * 存在しない商品・削除済みの商品は何も開かない
     */
    #[Test]
    public function 存在しないか削除済みの商品は何も開かない(): void
    {
        $this->loginAsAdmin();
        $deleted = $this->makeNormal(['detail' => '削除済みの紹介文']);
        $deleted->delete();

        Livewire::test(AdminItemDetail::class)
            ->dispatch('open-item-detail', id: 999999)
            ->assertSet('showDetail', false)
            ->assertSet('detailId', null);
        $this->openFor($deleted)
            ->assertSet('showDetail', false)
            ->assertSet('detailId', null)
            ->assertDontSee('削除済みの紹介文');
    }

    /**
     * 別の商品を開くと、前の内容は残らない
     */
    #[Test]
    public function 別の商品を開くと前の内容は残らない(): void
    {
        $this->loginAsAdmin();
        $first = $this->makeNormal(['detail' => '前の紹介文']);
        $second = $this->makeNormal(['detail' => '次の紹介文']);

        $this->openFor($first)
            ->dispatch('open-item-detail', id: $second->id)
            ->assertSee('次の紹介文')
            ->assertDontSee('前の紹介文');
    }

    /**
     * 閉じると詳細は表示されなくなる
     */
    #[Test]
    public function 閉じると表示されなくなる(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeNormal(['detail' => '紹介文です']);

        $this->openFor($item)
            ->assertSee('紹介文です')
            ->set('showDetail', false)
            ->assertDontSee('紹介文です');
    }

    /**
     * 管理者としてログインしていないと、開けない（403）
     */
    #[Test]
    public function 管理者でないと開けない(): void
    {
        $item = $this->makeNormal(['detail' => '紹介文です']);

        $this->loginAsMember();
        Livewire::test(AdminItemDetail::class)
            ->dispatch('open-item-detail', id: $item->id)
            ->assertForbidden();

        Livewire::test(AdminItemDetail::class)->assertDontSee('紹介文です');
    }

    /**
     * 開いている商品のIDは、ブラウザから書き換えられない
     */
    #[Test]
    public function idは書き換えられない(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeNormal();
        $other = $this->makeNormal();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->openFor($item)->set('detailId', $other->id);
    }
}
