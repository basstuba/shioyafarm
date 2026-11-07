<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Item;
use App\Models\Reservation;
use App\Repositories\Interfaces\ReservationRepositoryInterface;
use App\Services\ReservationLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 予約可能数（残数・受付終了・数量の上限）のテスト
 */
class ReservationLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 予約限定の商品を作る
     */
    private function item(?int $limit): Item
    {
        return Item::factory()->create(['is_reserved_only' => true, 'reservation_limit' => $limit]);
    }

    /**
     * 残数は、予約可能数から予約済みの数量の合計を引いた数。数量の上限は10と残数の小さい方
     */
    #[Test]
    public function 残数と数量の上限(): void
    {
        $service = app(ReservationLimitService::class);
        $item = $this->item(200);

        $this->assertSame(200, $service->remaining($item));
        $this->assertSame(10, $service->maxQuantity($item));
        $this->assertFalse($service->isClosed($item));

        Reservation::factory()->create(['item_id' => $item->id, 'quantity' => 195]);
        Reservation::factory()->create(['item_id' => $item->id, 'quantity' => 2]);

        $this->assertSame(3, $service->remaining($item));
        $this->assertSame(3, $service->maxQuantity($item));
        $this->assertFalse($service->isClosed($item));
    }

    /**
     * 予約可能数に達したら受付終了。超えていても残数は0（マイナスにしない）
     */
    #[Test]
    public function 予約可能数に達すると受付終了(): void
    {
        $service = app(ReservationLimitService::class);
        $item = $this->item(3);

        Reservation::factory()->create(['item_id' => $item->id, 'quantity' => 3]);
        $this->assertSame(0, $service->remaining($item));
        $this->assertTrue($service->isClosed($item));

        Reservation::factory()->create(['item_id' => $item->id, 'quantity' => 2]);
        $this->assertSame(0, $service->remaining($item));
        $this->assertTrue($service->isClosed($item));
    }

    /**
     * 予約可能数が未設定（null）の商品は制限なし。残数は null、上限は10、受付終了にならない
     */
    #[Test]
    public function 予約可能数が未設定なら制限なし(): void
    {
        $service = app(ReservationLimitService::class);
        $item = $this->item(null);
        Reservation::factory()->create(['item_id' => $item->id, 'quantity' => 1000]);

        $this->assertNull($service->remaining($item));
        $this->assertSame(10, $service->maxQuantity($item));
        $this->assertFalse($service->isClosed($item));
    }

    /**
     * 残数は商品ごとに数える
     */
    #[Test]
    public function 残数は商品ごとに数える(): void
    {
        $service = app(ReservationLimitService::class);
        $item = $this->item(5);
        $other = $this->item(5);
        Reservation::factory()->create(['item_id' => $other->id, 'quantity' => 5]);

        $this->assertSame(5, $service->remaining($item));
        $this->assertTrue($service->isClosed($other));
    }

    /**
     * 残数を取得済みのときは、集計せずに残数から受付終了と数量の上限を求める
     */
    #[Test]
    public function 残数から受付終了と数量の上限を求める(): void
    {
        $service = app(ReservationLimitService::class);

        $this->assertTrue($service->isClosedByRemaining(0));
        $this->assertFalse($service->isClosedByRemaining(1));
        $this->assertFalse($service->isClosedByRemaining(null));

        $this->assertSame(0, $service->maxQuantityByRemaining(0));
        $this->assertSame(3, $service->maxQuantityByRemaining(3));
        $this->assertSame(10, $service->maxQuantityByRemaining(10));
        $this->assertSame(10, $service->maxQuantityByRemaining(200));
        $this->assertSame(10, $service->maxQuantityByRemaining(null));
    }

    /**
     * 取消済みの予約の数量は、予約済みの合計に含めない。残数に戻る（AC-17）
     */
    #[Test]
    public function 取消済みの予約は予約可能数の残りに戻る(): void
    {
        $service = app(ReservationLimitService::class);
        $item = $this->item(10);
        Reservation::factory()->create(['item_id' => $item->id, 'quantity' => 4]);
        Reservation::factory()->create(['item_id' => $item->id, 'quantity' => 3, 'canceled_at' => '2026-11-05 10:00:00']);

        $this->assertSame(4, app(ReservationRepositoryInterface::class)->getReservedQuantity($item->id));
        $this->assertSame(6, $service->remaining($item));
        $this->assertSame(6, $service->maxQuantity($item));
    }

    /**
     * 予約可能数に達して受付を締め切っていた商品は、管理画面で予約を取り消すと、残りが戻って受け付けを再開する（AC-17）
     */
    #[Test]
    public function 管理画面で取り消すと締め切っていた商品が予約できるようになる(): void
    {
        $service = app(ReservationLimitService::class);
        $item = $this->item(5);
        $reservation = Reservation::factory()->create(['item_id' => $item->id, 'quantity' => 5, 'shipped' => false]);
        $this->assertTrue($service->isClosed($item));
        $this->assertSame(0, $service->remaining($item));
        $this->actingAs(Admin::factory()->create(), 'admin');

        $this->post(route('admin.reservations.cancel'), ['id' => $reservation->id])->assertSessionHas('list_status', '予約を取り消しました。クレジットカードの返金は、Stripeの管理画面で行ってください。');

        $this->assertFalse($service->isClosed($item));
        $this->assertSame(5, $service->remaining($item));
    }

    /**
     * 取り消しても、ほかの商品の予約可能数には影響しない
     */
    #[Test]
    public function 取消はほかの商品の残りに影響しない(): void
    {
        $service = app(ReservationLimitService::class);
        $a = $this->item(10);
        $b = $this->item(10);
        Reservation::factory()->create(['item_id' => $a->id, 'quantity' => 2, 'canceled_at' => '2026-11-05 10:00:00']);
        Reservation::factory()->create(['item_id' => $b->id, 'quantity' => 2]);

        $this->assertSame(10, $service->remaining($a));
        $this->assertSame(8, $service->remaining($b));
    }
}
