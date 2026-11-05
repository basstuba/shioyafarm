<?php

namespace Tests\Unit;

use App\Enums\AdminListType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 管理画面の一覧の種類（AdminListType）のテスト
 */
class AdminListTypeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * POST /admin/export の type の値は変わらない
     */
    #[Test]
    public function エクスポートの種類の値は変わらない(): void
    {
        $this->assertSame('purchase', AdminListType::Purchase->value);
        $this->assertSame('reservation', AdminListType::Reservation->value);
    }

    /**
     * 種類ごとの語・ルート・イベント名・テーブルの組み合わせ
     */
    #[Test]
    public function 種類ごとの語とルートとイベントとテーブルがそろっている(): void
    {
        $this->assertSame(['注文', 'admin.purchase.index', 'admin.purchase.search', 'admin.purchase.update', 'open-purchase-detail', 'purchase_histories'], $this->describe(AdminListType::Purchase));
        $this->assertSame(['予約', 'admin.reservations.index', 'admin.reservations.search', 'admin.reservations.update', 'open-reservation-detail', 'reservations'], $this->describe(AdminListType::Reservation));
    }

    /**
     * ルート名は実在し、テーブルも実在する
     */
    #[Test]
    public function ルートとテーブルは実在する(): void
    {
        foreach (AdminListType::cases() as $type) {
            foreach ([$type->listRoute(), $type->searchRoute(), $type->updateRoute()] as $name) {
                $this->assertTrue(Route::has($name), $name);
            }
            $this->assertTrue(Schema::hasTable($type->table()), $type->table());
        }
    }

    /**
     * @return list<string>
     */
    private function describe(AdminListType $type): array
    {
        return [$type->subject(), $type->listRoute(), $type->searchRoute(), $type->updateRoute(), $type->detailEvent(), $type->table()];
    }
}
