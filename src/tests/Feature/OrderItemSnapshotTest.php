<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Livewire\AdminPurchaseDetail;
use App\Livewire\AdminReservationDetail;
use App\Livewire\MyPagePurchaseHistory;
use App\Livewire\MyPageReservationHistory;
use App\Mail\PurchaseThanksMail;
use App\Mail\ReservationThanksMail;
use App\Models\Admin;
use App\Models\Item;
use App\Models\Purchase_history;
use App\Models\Purchase_history_item;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Payment\CheckoutSessionData;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\PendingOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Mockery\MockInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\Concerns\ReservationTestHelpers;
use Tests\TestCase;

/**
 * 過去の注文・予約の保護（注文した時点の商品名・重さの保存と、保存した値での表示）のテスト
 *
 * 商品を書き換えたり削除したりしても、マイページ・管理画面・エクスポート・サンクスメールの表示は変わらない。
 */
class OrderItemSnapshotTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;
    use ReservationTestHelpers;

    /**
     * 商品の名前・重さを書き換え、論理削除する（注文・予約の表示が変わらないことを確かめる前に呼ぶ）
     */
    private function rewriteAndDelete(Item $item): void
    {
        $item->update(['name' => '書き換えた商品', 'weight' => 99]);
        $item->delete();
    }

    /**
     * 管理者としてログインする
     */
    private function loginAsAdmin(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
    }

    /**
     * 購入（じゃがいも 2kg × 3。330円）を1件作る
     *
     * @return array{User, Purchase_history, Item}
     */
    private function makeHistory(): array
    {
        $user = User::factory()->create();
        $item = $this->makeItem('じゃがいも', 2, 330);
        $purchase = Purchase_history::factory()->create([
            'user_id' => $user->id, 'payment_method' => 'card', 'shipping_fee' => 940, 'cod_fee' => 0, 'total_price' => 990 + 940,
        ]);
        Purchase_history_item::factory()->create(['purchase_history_id' => $purchase->id, 'item_id' => $item->id, 'quantity' => 3, 'price' => 330, 'subtotal' => 990]);

        return [$user, $purchase, $item];
    }

    /**
     * 予約（北の恵みセット 10kg × 1。1,450円）を1件作る
     *
     * @return array{User, Reservation, Item}
     */
    private function makeBooking(): array
    {
        $user = User::factory()->create();
        $item = $this->makeReservable();
        $reservation = Reservation::factory()->create(['user_id' => $user->id, 'item_id' => $item->id]);

        return [$user, $reservation, $item];
    }

    /**
     * ダウンロードされた .xlsx のシートを読み込む
     */
    private function sheet(TestResponse $response): Worksheet
    {
        $file = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $file);

        return IOFactory::load($file->getFile()->getPathname())->getActiveSheet();
    }

    /**
     * 代金引換の購入を確定すると、購入明細に注文した時点の商品名と重さが保存される
     */
    #[Test]
    public function 購入の保存で注文した時点の商品名と重さが保存される(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('ensureCustomer');
            $mock->shouldNotReceive('createCheckoutSession');
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1], ['payment_method' => 'cod']))
            ->assertRedirect(route('shop.checkout.thanks'));

        $rows = Purchase_history_item::orderBy('item_id')->get();
        $this->assertSame(['じゃがいも', '玉ねぎ'], $rows->pluck('item_name')->all());
        $this->assertSame([2, 5], $rows->pluck('item_weight')->all());
        $this->assertSame('じゃがいも 2kg', $rows[0]->itemLabel());
    }

    /**
     * 予約の保存で、予約した時点の商品名と重さが保存される
     */
    #[Test]
    public function 予約の保存で予約した時点の商品名と重さが保存される(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable();
        $this->putPendingReservation($user, $item, PaymentMethod::Card, 'cs_snapshot');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->with('cs_snapshot')->andReturn(new CheckoutSessionData('cs_snapshot', null, 'complete', 'paid'));
        });

        $this->actingAs($user)->get(route('shop.reservation.success', ['session_id' => 'cs_snapshot']))->assertOk();

        $reservation = Reservation::sole();
        $this->assertSame('北の恵みセット', $reservation->item_name);
        $this->assertSame(10, $reservation->item_weight);
        $this->assertSame('北の恵みセット 10kg', $reservation->itemLabel());
    }

    /**
     * マイページの購入履歴（一覧・詳細）は、商品を書き換えて削除しても、注文した時点の商品名・重さのまま
     */
    #[Test]
    public function マイページの購入履歴は注文した時点の商品名と重さで表示される(): void
    {
        [$user, $purchase, $item] = $this->makeHistory();
        $this->rewriteAndDelete($item);

        Livewire::actingAs($user)
            ->test(MyPagePurchaseHistory::class)
            ->assertSee('じゃがいも 2kg')
            ->assertDontSee('書き換えた商品')
            ->call('openDetail', $purchase->id)
            ->assertSee('じゃがいも 2kg')
            ->assertSee('330円(税込)')
            ->assertSee('990円')
            ->assertDontSee('書き換えた商品');
    }

    /**
     * マイページの予約履歴（一覧・詳細）も同じ
     */
    #[Test]
    public function マイページの予約履歴は予約した時点の商品名と重さで表示される(): void
    {
        [$user, $reservation, $item] = $this->makeBooking();
        $this->rewriteAndDelete($item);

        Livewire::actingAs($user)
            ->test(MyPageReservationHistory::class)
            ->assertSee('北の恵みセット 10kg')
            ->assertDontSee('書き換えた商品')
            ->call('openDetail', $reservation->id)
            ->assertSee('北の恵みセット 10kg')
            ->assertSee('1,450円(税込)')
            ->assertDontSee('書き換えた商品');
    }

    /**
     * 管理画面の注文・予約の詳細モーダルも同じ
     */
    #[Test]
    public function 管理画面の詳細は注文した時点の商品名と重さで表示される(): void
    {
        [, $purchase, $purchasedItem] = $this->makeHistory();
        [, $reservation, $reservedItem] = $this->makeBooking();
        $this->rewriteAndDelete($purchasedItem);
        $this->rewriteAndDelete($reservedItem);
        $this->loginAsAdmin();

        Livewire::test(AdminPurchaseDetail::class)
            ->dispatch('open-purchase-detail', id: $purchase->id)
            ->assertSee('じゃがいも 2kg')
            ->assertSee('990円')
            ->assertDontSee('書き換えた商品');
        Livewire::test(AdminReservationDetail::class)
            ->dispatch('open-reservation-detail', id: $reservation->id)
            ->assertSee('北の恵みセット 10kg')
            ->assertSee('1,450円')
            ->assertDontSee('書き換えた商品');
    }

    /**
     * エクスポートは、削除した商品・書き換えた商品でも、注文した時点の商品名と重さで出力される
     */
    #[Test]
    public function エクスポートは注文した時点の商品名と重さで出力される(): void
    {
        [, , $purchasedItem] = $this->makeHistory();
        [, , $reservedItem] = $this->makeBooking();
        $this->rewriteAndDelete($purchasedItem);
        $this->rewriteAndDelete($reservedItem);
        $this->loginAsAdmin();

        $purchaseRow = $this->sheet($this->post(route('admin.export'), ['type' => 'purchase']))->toArray(null, false, false, false)[1];
        $reservationRow = $this->sheet($this->post(route('admin.export'), ['type' => 'reservation']))->toArray(null, false, false, false)[1];

        $this->assertSame(['じゃがいも', '2kg', 3], [$purchaseRow[3], $purchaseRow[4], $purchaseRow[5]]);
        $this->assertSame(['北の恵みセット', '10kg', 1], [$reservationRow[3], $reservationRow[4], $reservationRow[5]]);
    }

    /**
     * サンクスメールは、商品を書き換えても、注文・予約した時点の商品名と重さで組み立てられる
     */
    #[Test]
    public function サンクスメールは注文した時点の商品名と重さで組み立てられる(): void
    {
        Mail::fake();
        [, $purchase, $purchasedItem] = $this->makeHistory();
        [, $reservation, $reservedItem] = $this->makeBooking();
        $this->rewriteAndDelete($purchasedItem);
        $this->rewriteAndDelete($reservedItem);

        $purchaseMail = new PurchaseThanksMail($purchase->fresh());
        $reservationMail = new ReservationThanksMail($reservation->fresh());

        $purchaseMail->assertSeeInHtml('じゃがいも 2kg × 3');
        $purchaseMail->assertDontSeeInHtml('書き換えた商品');
        $reservationMail->assertSeeInHtml('北の恵みセット 10kg');
        $reservationMail->assertDontSeeInHtml('書き換えた商品');
    }

    /**
     * 履歴の表示は、商品の行を読み込まない（商品の削除・書き換えに左右されないため）
     */
    #[Test]
    public function 履歴の表示は商品の行を読まない(): void
    {
        [$user, $purchase] = $this->makeHistory();
        DB::enableQueryLog();

        Livewire::actingAs($user)->test(MyPagePurchaseHistory::class)->call('openDetail', $purchase->id);

        $itemQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "items"'));
        $this->assertCount(0, $itemQueries);
    }

    /**
     * 保存待ちの注文のうち、商品の重さを持たない古い形式のもの（商品名の保存を始める前にセッションへ入ったもの）は、使えない注文として扱う
     */
    #[Test]
    public function 古い形式の保存待ちの注文は使えない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $order = $this->putPending($user, $items, PaymentMethod::Cod);
        $old = $order->toArray();
        $old['items'] = [['item_id' => $items['potato']->id, 'name' => 'じゃがいも 2kg', 'quantity' => 2, 'price' => 330, 'subtotal' => 660]];

        $this->assertNull(PendingOrder::fromArray($old));
    }

    /**
     * マイグレーションは、既存の注文明細・予約に、商品の今の値（削除済みの商品を含む）を入れて NOT NULL にする
     */
    #[Test]
    public function マイグレーションは既存の行を商品の今の値で埋める(): void
    {
        [, $purchase, $purchasedItem] = $this->makeHistory();
        [, $reservation, $reservedItem] = $this->makeBooking();
        $reservedItem->delete();
        $purchaseMigration = require database_path('migrations/2026_11_08_100100_add_item_name_weight_to_purchase_history_items_table.php');
        $reservationMigration = require database_path('migrations/2026_11_08_100200_add_item_name_weight_to_reservations_table.php');

        $purchaseMigration->down();
        $reservationMigration->down();
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('purchase_history_items', 'item_name'));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('reservations', 'item_weight'));
        $purchaseMigration->up();
        $reservationMigration->up();

        $this->assertDatabaseHas('purchase_history_items', ['purchase_history_id' => $purchase->id, 'item_name' => 'じゃがいも', 'item_weight' => 2]);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'item_name' => '北の恵みセット', 'item_weight' => 10]);
        // 空のまま追加はできない（NOT NULL）
        $this->assertFalse($this->canInsertWithoutSnapshot($purchase, $purchasedItem));
    }

    /**
     * item_name・item_weight なしで注文明細を追加できるか（NOT NULL なので追加できない）
     */
    private function canInsertWithoutSnapshot(Purchase_history $purchase, Item $item): bool
    {
        try {
            DB::table('purchase_history_items')->insert([
                'purchase_history_id' => $purchase->id, 'item_id' => $item->id, 'quantity' => 1, 'price' => 330, 'subtotal' => 330,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (QueryException) {
            return false;
        }

        return true;
    }
}
