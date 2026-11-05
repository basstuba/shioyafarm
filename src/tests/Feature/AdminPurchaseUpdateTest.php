<?php

namespace Tests\Feature;

use App\Livewire\MyPagePurchaseHistory;
use App\Models\Item;
use App\Repositories\Interfaces\PurchaseHistoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の注文の発送済みチェック（admin.purchase.update）のテスト
 */
class AdminPurchaseUpdateTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 発送済みを渡さずに作った注文は、DB の既定値で未発送になる（Factory を通さず、購入の保存の経路で作る）
     */
    #[Test]
    public function 購入の保存の経路で作った注文は未発送で始まる(): void
    {
        $user = $this->makeUser('山田', '太郎');
        $item = Item::factory()->create(['is_reserved_only' => false, 'price' => 330]);

        $created = app(PurchaseHistoryRepositoryInterface::class)->createWithItems([
            'user_id' => $user->id,
            'delivery_name' => '山田 太郎',
            'delivery_prefecture' => '北海道',
            'delivery_city' => '札幌市',
            'delivery_tell' => '09012345678',
            'payment_method' => 'card',
            'shipping_fee' => 940,
            'cod_fee' => 0,
            'total_price' => 1270,
        ], [
            ['item_id' => $item->id, 'quantity' => 1, 'price' => 330, 'subtotal' => 330],
        ]);

        $this->assertDatabaseHas('purchase_histories', ['id' => $created->id, 'shipped' => 0]);
        $this->assertFalse($created->fresh()->shipped);
    }

    /**
     * チェックを入れると発送済みになり、外すと未発送に戻る。JSON を返す
     */
    #[Test]
    public function チェックを入れると発送済みになり外すと未発送に戻る(): void
    {
        $this->loginAsAdmin();
        $order = $this->makeOrder();

        $this->postJson(route('admin.purchase.update'), ['id' => $order->id, 'shipped' => true])
            ->assertOk()
            ->assertExactJson(['id' => $order->id, 'shipped' => true]);
        $this->assertTrue($order->fresh()->shipped);

        $this->postJson(route('admin.purchase.update'), ['id' => $order->id, 'shipped' => false])
            ->assertOk()
            ->assertExactJson(['id' => $order->id, 'shipped' => false]);
        $this->assertFalse($order->fresh()->shipped);
    }

    /**
     * 更新するのは指定した注文だけで、他の注文は変わらない
     */
    #[Test]
    public function 他の注文は変わらない(): void
    {
        $this->loginAsAdmin();
        [$target, $other] = $this->makeOrders(2);

        $this->postJson(route('admin.purchase.update'), ['id' => $target->id, 'shipped' => true])->assertOk();

        $this->assertTrue($target->fresh()->shipped);
        $this->assertFalse($other->fresh()->shipped);
    }

    /**
     * 存在しない注文はエラーになり、他の注文は変わらない
     */
    #[Test]
    public function 存在しない注文はエラーで他の注文は変わらない(): void
    {
        $this->loginAsAdmin();
        $order = $this->makeOrder();

        $this->postJson(route('admin.purchase.update'), ['id' => $order->id + 100, 'shipped' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id')
            ->assertJsonPath('message', '注文が見つかりません。');

        $this->assertFalse($order->fresh()->shipped);
    }

    /**
     * 入力の誤りは、リダイレクトではなく JSON（422）で返す
     */
    #[Test]
    public function 入力の誤りはjsonの422で返る(): void
    {
        $this->loginAsAdmin();
        $order = $this->makeOrder();

        $this->postJson(route('admin.purchase.update'), ['shipped' => true])->assertStatus(422)->assertJsonValidationErrors('id');
        $this->postJson(route('admin.purchase.update'), ['id' => $order->id])->assertStatus(422)->assertJsonValidationErrors('shipped');
        $this->postJson(route('admin.purchase.update'), ['id' => $order->id, 'shipped' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('shipped');
        $this->postJson(route('admin.purchase.update'), ['id' => 'abc', 'shipped' => true])->assertStatus(422)->assertJsonValidationErrors('id');
        // fetch が付ける Accept: application/json のヘッダーでも、標準の422のJSONになる
        $this->post(route('admin.purchase.update'), ['id' => 0, 'shipped' => true], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id');

        $this->assertFalse($order->fresh()->shipped);
    }

    /**
     * 未ログイン・会員としてのログインでは変更できない
     */
    #[Test]
    public function 未ログインと会員は発送済みを変更できない(): void
    {
        $order = $this->makeOrder();

        $this->post(route('admin.purchase.update'), ['id' => $order->id, 'shipped' => true])->assertRedirect(route('admin.login'));
        $this->postJson(route('admin.purchase.update'), ['id' => $order->id, 'shipped' => true])->assertUnauthorized();
        $this->assertFalse($order->fresh()->shipped);

        $this->loginAsMember();
        $this->post(route('admin.purchase.update'), ['id' => $order->id, 'shipped' => true])->assertRedirect(route('admin.login'));
        $this->assertFalse($order->fresh()->shipped);
    }

    /**
     * CSRFトークンがないとき（419）は変更されない（テストでは検証がスキップされるため、有効にして確認する）
     */
    #[Test]
    public function csrfトークンがないと変更できない(): void
    {
        $this->app['env'] = 'local';
        $this->loginAsAdmin();
        $order = $this->makeOrder();

        $this->postJson(route('admin.purchase.update'), ['id' => $order->id, 'shipped' => true])->assertStatus(419);

        $this->assertFalse($order->fresh()->shipped);
    }

    /**
     * 発送済みにしても、メールは送られず、マイページの購入履歴に発送の表示は出ない
     */
    #[Test]
    public function 発送済みにしてもメールは送られずマイページに発送の表示は出ない(): void
    {
        Mail::fake();
        $user = $this->makeUser('山田', '太郎');
        $order = $this->makeOrder($user);

        $this->loginAsAdmin();
        $this->postJson(route('admin.purchase.update'), ['id' => $order->id, 'shipped' => true])->assertOk();

        Auth::guard('admin')->logout();
        Auth::shouldUse('web');
        $this->actingAs($user)
            ->get(route('shop.mypage.index'))
            ->assertOk()
            ->assertDontSee('発送');
        Livewire::test(MyPagePurchaseHistory::class)->assertDontSee('発送');
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }
}
