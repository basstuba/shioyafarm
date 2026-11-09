<?php

namespace Tests\Feature;

use App\Enums\CartAddResult;
use App\Models\Admin;
use App\Models\Item;
use App\Models\Purchase_history;
use App\Models\Purchase_history_item;
use App\Models\Reservation;
use App\Services\AdminItemService;
use App\Services\CartService;
use App\Services\PurchaseService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\Concerns\ReservationTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の掲載商品の論理削除（DELETE admin.items.delete）と、削除した商品が公開側から消えることのテスト
 */
class AdminItemDeleteTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;
    use ReservationTestHelpers;

    /**
     * 管理者としてログインする
     */
    private function loginAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    /**
     * 管理画面を通さずに商品を削除する（公開側の確認で、会員のログインを切り替えずに済ませる）
     */
    private function removeItem(Item $item): void
    {
        $this->assertTrue(app(AdminItemService::class)->delete($item->id));
    }

    /**
     * 管理者として、商品の削除を送る
     */
    private function deleteItem(mixed $id): TestResponse
    {
        return $this->delete(route('admin.items.delete'), ['id' => $id]);
    }

    /**
     * 削除すると論理削除（deleted_at が入る）になり、一覧に戻って緑の案内が出る。ほかの商品は残る
     */
    #[Test]
    public function 商品が論理削除され一覧に戻って案内が出る(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeItem('削除する商品', 2, 330);
        $other = $this->makeItem('残る商品', 5, 795);

        $this->deleteItem($target->id)
            ->assertRedirect(route('admin.items.index'))
            ->assertSessionHas('list_status', '商品を削除しました。')
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($target);
        $this->assertDatabaseHas('items', ['id' => $target->id]);
        $this->assertNotSoftDeleted($other);

        $this->get(route('admin.items.index'))
            ->assertSee('商品を削除しました。')
            ->assertSee('text-accent-hover', false)
            ->assertSee('残る商品')
            ->assertDontSee('削除する商品');
    }

    /**
     * 削除済みの商品の削除は何も変えず、一覧に戻って赤字の案内が出る（削除日時も変わらない）
     */
    #[Test]
    public function 削除済みの商品は何も変えずに見つからない案内になる(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeItem('じゃがいも', 2, 330);
        $this->deleteItem($item->id);
        $deletedAt = Item::withTrashed()->find($item->id)->deleted_at;
        $this->travel(5)->minutes();

        $this->deleteItem($item->id)
            ->assertRedirect(route('admin.items.index'))
            ->assertSessionMissing('list_status')
            ->assertSessionHasErrors(['delete' => '商品が見つかりません。']);

        $this->assertEquals($deletedAt, Item::withTrashed()->find($item->id)->deleted_at);
        $this->get(route('admin.items.index'))
            ->assertSee('商品が見つかりません。')
            ->assertSee('text-danger', false);
    }

    /**
     * 存在しない商品も同じ扱い
     */
    #[Test]
    public function 存在しない商品は見つからない案内になる(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeItem('じゃがいも', 2, 330);

        $this->deleteItem(999999)
            ->assertRedirect(route('admin.items.index'))
            ->assertSessionHasErrors(['delete' => '商品が見つかりません。']);

        $this->assertNotSoftDeleted($item);
    }

    /**
     * IDが空・数字でない・0以下のときも、何も変えずに一覧に戻って同じ案内になる
     */
    #[Test]
    public function idの形が不正なときも見つからない案内になる(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeItem('じゃがいも', 2, 330);

        foreach ([null, '', 'abc', '1.5', '0', '-1', ['1'], '99999999999999999999'] as $id) {
            $this->from(route('admin.items.index'))
                ->deleteItem($id)
                ->assertRedirect(route('admin.items.index'))
                ->assertSessionHasErrors(['id' => '商品が見つかりません。']);
        }
        $this->from(route('admin.items.index'))->delete(route('admin.items.delete'))
            ->assertRedirect(route('admin.items.index'))
            ->assertSessionHasErrors(['id' => '商品が見つかりません。']);

        $this->assertNotSoftDeleted($item);
        $this->get(route('admin.items.index'))->assertSee('商品が見つかりません。');
    }

    /**
     * 処理の最初と最後にデバッグログを出す
     */
    #[Test]
    public function 処理の最初と最後にログを出す(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeItem('じゃがいも', 2, 330);
        Log::spy();

        $this->deleteItem($item->id);

        Log::shouldHaveReceived('debug')->with('AdminItemController@delete start')->once();
        Log::shouldHaveReceived('debug')->with('AdminItemController@delete end')->once();
    }

    /**
     * 論理削除なので、商品の行は残り、過去の注文・予約の記録も消えない（外部キーの cascade も働かない）
     */
    #[Test]
    public function 過去の注文と予約の記録は残る(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeItem('じゃがいも', 2, 330);
        $purchase = Purchase_history::factory()->create();
        $line = Purchase_history_item::factory()->create(['purchase_history_id' => $purchase->id, 'item_id' => $item->id, 'quantity' => 3, 'price' => 330, 'subtotal' => 990]);
        $reservation = Reservation::factory()->create(['item_id' => $this->makeReservable()->id]);

        $this->deleteItem($line->item_id)->assertSessionHas('list_status');
        $this->deleteItem($reservation->item_id)->assertSessionHas('list_status');

        $this->assertDatabaseHas('purchase_histories', ['id' => $purchase->id]);
        $this->assertDatabaseHas('purchase_history_items', ['id' => $line->id, 'item_name' => 'じゃがいも', 'item_weight' => 2]);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'item_name' => '北の恵みセット', 'item_weight' => 10]);
        // 関連から削除済みの商品を引ける
        $this->assertTrue($line->fresh()->item->trashed());
        $this->assertTrue($reservation->fresh()->item->trashed());
    }

    /**
     * 削除した商品は、公開側の商品一覧・トップのおすすめに出ない
     */
    #[Test]
    public function 削除した商品は商品一覧とおすすめに出ない(): void
    {
        $gone = $this->makeItem('消える商品', 2, 330);
        $gone->update(['recommend' => true]);
        $stay = $this->makeItem('残る商品', 5, 795);
        $stay->update(['recommend' => true]);
        $this->get(route('top.index'))->assertSee('消える商品');
        $this->get(route('shop.items.index'))->assertSee('消える商品');

        $this->removeItem($gone);

        $this->get(route('top.index'))->assertOk()->assertDontSee('消える商品')->assertSee('残る商品');
        $this->get(route('shop.items.index'))->assertOk()->assertDontSee('消える商品')->assertSee('残る商品');
    }

    /**
     * 削除した商品の詳細・購入ページ・予約ページを直接開くと404になる
     */
    #[Test]
    public function 削除した商品の詳細と購入と予約のページは404になる(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $normal = $this->makeItem('じゃがいも', 2, 330);
        $reserved = $this->makeReservable();
        $this->actingAs($user)->get(route('shop.items.show', $normal))->assertOk();
        $this->get(route('shop.checkout.index', $normal))->assertOk();
        $this->get(route('shop.reservation.index', $reserved))->assertOk();

        $this->removeItem($normal);
        $this->removeItem($reserved);

        $this->get(route('shop.items.show', $normal->id))->assertNotFound();
        $this->get(route('shop.checkout.index', $normal->id))->assertNotFound();
        $this->get(route('shop.reservation.index', $reserved->id))->assertNotFound();
    }

    /**
     * カートに入っていた商品はカートから消え、小計にも含まれない
     */
    #[Test]
    public function カートに入っていた商品はカートから消え合計に含まれない(): void
    {
        $user = $this->makeUser();
        $gone = $this->makeItem('消える商品', 2, 330);
        $stay = $this->makeItem('残る商品', 5, 795);
        $cart = [$gone->id => 2, $stay->id => 1];
        $this->actingAs($user)->withSession(['cart' => $cart])->get(route('shop.cart'))->assertSee('1,455円');

        $this->removeItem($gone);

        $this->actingAs($user)->withSession(['cart' => $cart])
            ->get(route('shop.cart'))
            ->assertOk()
            ->assertDontSee('消える商品')
            ->assertSee('残る商品 5kg')
            ->assertSee('795円')
            ->assertDontSee('1,455円');
        $this->assertSame([$stay->id => 1], session('cart'));
        $this->assertSame(CartAddResult::Rejected, app(CartService::class)->addItem($gone->id));
    }

    /**
     * 商品を物理削除（phpMyAdmin などで行を消す）しようとしても、注文・予約があれば外部キーで拒否され、履歴は残る
     */
    #[Test]
    public function 注文や予約のある商品は物理削除できない(): void
    {
        $line = Purchase_history_item::factory()->create();
        $reservation = Reservation::factory()->create();

        foreach ([$line->item_id, $reservation->item_id] as $itemId) {
            try {
                Item::withTrashed()->whereKey($itemId)->forceDelete();
                $this->fail('注文・予約のある商品を物理削除できてしまった');
            } catch (QueryException) {
                // 外部キー（restrict）で拒否される
            }
        }

        $this->assertDatabaseHas('items', ['id' => $line->item_id]);
        $this->assertDatabaseHas('items', ['id' => $reservation->item_id]);
        $this->assertDatabaseHas('purchase_history_items', ['id' => $line->id]);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
    }

    /**
     * 注文・予約のない商品は、物理削除できる
     */
    #[Test]
    public function 注文も予約もない商品は物理削除できる(): void
    {
        $item = $this->makeItem('じゃがいも', 2, 330);

        $item->forceDelete();

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    /**
     * 削除した商品は、購入・予約の確定でも受け付けない（保存待ちの注文もできない）
     */
    #[Test]
    public function 削除した商品は購入も予約もできない(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $normal = $this->makeItem('じゃがいも', 2, 330);
        $reserved = $this->makeReservable();
        $this->removeItem($normal);
        $this->removeItem($reserved);
        $this->actingAs($user);

        $this->post(route('shop.checkout.payment'), $this->paymentData($user, [$normal->id => 1]))
            ->assertSessionHasErrors('items.0.item_id');
        $this->post(route('shop.reservation.payment'), $this->reservationData($user, $reserved))
            ->assertSessionHasErrors('item_id');

        $this->assertNull(session('pending_order'));
        $this->assertNull(session('pending_reservation'));
        $this->assertSame(0, Reservation::count());
        $this->assertSame(0, Purchase_history_item::count());
        $this->assertNull(app(PurchaseService::class)->getPendingOrder($user));
    }
}
