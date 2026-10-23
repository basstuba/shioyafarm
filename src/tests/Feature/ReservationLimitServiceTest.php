<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Reservation;
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
}
