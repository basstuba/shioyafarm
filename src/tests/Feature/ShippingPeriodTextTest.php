<?php

namespace Tests\Feature;

use App\Mail\ReservationThanksMail;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 発送の時期の文言が、config('shipping') の1か所から画面・メールに差し込まれることのテスト
 */
class ShippingPeriodTextTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 設定の初期値は、画面に出していた文言のとおり
     */
    #[Test]
    public function 設定の初期値(): void
    {
        $this->assertSame('3〜5営業日', config('shipping.ship_days_text'));
        $this->assertSame('9月中', config('shipping.reservation_ship_period_text'));
    }

    /**
     * 特定商取引法のページ・商品一覧の案内・購入完了ページの「3〜5営業日」は設定の値になる
     */
    #[Test]
    public function 発送までの日数は設定の値が画面に出る(): void
    {
        config(['shipping.ship_days_text' => '1〜2営業日']);

        $this->get(route('info.commercial-transaction'))
            ->assertOk()
            ->assertSee('お支払い確定後1〜2営業日以内に発送いたします。')
            ->assertSee('代金引換はご注文確定後1〜2営業日以内に発送いたします。')
            ->assertDontSee('3〜5営業日');

        $this->get(route('shop.items.index'))
            ->assertOk()
            ->assertSee('お支払いの確定後、1〜2営業日以内に発送いたします');
    }

    /**
     * 予約完了の画面・メールの「9月中」は設定の値になる
     */
    #[Test]
    public function 予約の発送の時期は設定の値が画面とメールに出る(): void
    {
        config(['shipping.reservation_ship_period_text' => '10月中']);

        $this->get(route('shop.items.index'))
            ->assertOk()
            ->assertSee('北の恵みセットは10月中の発送となります。');

        $this->view('shop.reservation_thanks')->assertSee('商品は10月中に発送いたします。');

        $mail = new ReservationThanksMail(Reservation::factory()->create());
        $mail->assertSeeInHtml('商品は10月中に発送いたします。');
        $mail->assertSeeInText('商品は10月中に発送いたします。');
        $mail->assertDontSeeInText('9月中');
    }
}
