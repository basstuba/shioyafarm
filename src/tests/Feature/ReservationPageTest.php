<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Reservation;
use App\Services\Payment\BankTransferIntentData;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\Concerns\ReservationTestHelpers;
use Tests\TestCase;

/**
 * 予約ページ（GET /reservation/{item}）のテスト
 */
class ReservationPageTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;
    use ReservationTestHelpers;

    /**
     * 未ログインはログインページへ移動し、ログインすると予約ページへ戻る（AC-1, S-1）
     */
    #[Test]
    public function 未ログインはログインページへ移動しログイン後に予約ページへ戻る(): void
    {
        $item = $this->makeReservable();
        $user = $this->makeUser();

        $this->get(route('shop.reservation.index', $item))->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('shop.reservation.index', $item));
    }

    /**
     * 商品（画像・商品名・単価・数量1・小計）と、小計・送料・合計、会員登録の内容が入ったお届け先が表示される（AC-2, S-2）
     */
    #[Test]
    public function 商品と金額と初期値が表示される(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable();
        $shippingFee = config('shipping.fees.100.北海道');

        $response = $this->actingAs($user)->get(route('shop.reservation.index', $item));

        $response->assertOk()
            ->assertViewIs('shop.reservation')
            ->assertViewHas('itemId', $item->id)
            ->assertSeeLivewire('reservation-form')
            ->assertSee('<title>予約 | Shioya Farm</title>', false)
            ->assertSee('ご予約内容')
            ->assertSee('storage/image/product/potato-card.webp', false)
            ->assertSee('北の恵みセット 10kg')
            ->assertSee('1,450円(税込)')
            ->assertSee('name="quantity"', false)
            ->assertSee('value="1"', false)
            ->assertSeeInOrder(['小計', '1,450円', '送料', number_format($shippingFee).'円', '合計', number_format(1450 + $shippingFee).'円'])
            ->assertSee('value="塩谷太郎"', false)
            ->assertSee('value="09012345678"', false)
            ->assertSee('value="user_'.$user->id.'"', false)
            ->assertSee('確認用お届け先住所')
            ->assertSee('北海道札幌市中央区1-1-1')
            ->assertSee('＋新しい住所を追加')
            ->assertSee('お支払い方法を選択してください')
            ->assertSee('ご予約内容をご確認のうえ、よろしければ「予約を確定する」ボタンを押してください')
            ->assertSee('予約を確定する')
            ->assertSee('商品一覧に戻る')
            ->assertSee('action="'.route('shop.reservation.payment').'"', false)
            ->assertSee('name="item_id" value="'.$item->id.'"', false)
            ->assertSee('name="order_token"', false)
            ->assertDontSee('代引手数料');
        $this->assertSame(2980, 1450 + $shippingFee);
    }

    /**
     * 支払方法はクレジットカードと銀行振込の2つだけで、代金引換は出ない（AC-5, S-2）
     */
    #[Test]
    public function 支払方法はカードと銀行振込の2つだけ(): void
    {
        $this->makeAreas();
        $item = $this->makeReservable();

        $this->actingAs($this->makeUser())
            ->get(route('shop.reservation.index', $item))
            ->assertSee('クレジットカード')
            ->assertSee('Visa / Master / JCB')
            ->assertSee('銀行振込')
            ->assertSee('銀行口座にお振込み')
            ->assertSee('value="card"', false)
            ->assertSee('value="customer_balance"', false)
            ->assertDontSee('value="cod"', false)
            ->assertDontSee('代金引換');
    }

    /**
     * 予約限定でない商品・存在しない商品・形式が不正なIDは404（AC-3, S-3）
     */
    #[Test]
    public function 予約限定でない商品と存在しない商品は404(): void
    {
        $normal = $this->makeItem('人参', 2, 418);
        $user = $this->makeUser();

        $this->actingAs($user)->get(route('shop.reservation.index', $normal))->assertNotFound();
        $this->get(route('shop.reservation.index', 9999))->assertNotFound();
        $this->get('/reservation/0')->assertNotFound();
        $this->get('/reservation/abc')->assertNotFound();
        $this->get('/reservation/99999999999999999999')->assertNotFound();
    }

    /**
     * ヘッダーはショップのナビ（マイページ・ログアウト）になる
     */
    #[Test]
    public function ヘッダーにマイページが表示される(): void
    {
        $this->makeAreas();
        $item = $this->makeReservable();

        $this->actingAs($this->makeUser())
            ->get(route('shop.reservation.index', $item))
            ->assertOk()
            ->assertSee('マイページ');
    }

    /**
     * 予約可能数に達した商品は、商品詳細へ戻して受付終了を案内する（AC-9, S-13）
     */
    #[Test]
    public function 予約可能数に達した商品は商品詳細へ戻される(): void
    {
        $item = $this->makeReservable(limit: 3);
        $this->makeReservedBy($item, 1);
        $this->makeReservedBy($item, 2);

        $this->actingAs($this->makeUser())
            ->get(route('shop.reservation.index', $item))
            ->assertRedirect(route('shop.items.show', $item))
            ->assertSessionHas(ReservationService::CLOSED_FLASH_KEY, 'この商品の予約受付は終了しました');
    }

    /**
     * 予約可能数を超えて予約されている商品（保存時に再確認しないため起こりうる）も受付終了として扱う
     */
    #[Test]
    public function 予約可能数を超えて予約されていても受付終了になる(): void
    {
        $item = $this->makeReservable(limit: 3);
        $this->makeReservedBy($item, 5);

        $this->actingAs($this->makeUser())
            ->get(route('shop.reservation.index', $item))
            ->assertRedirect(route('shop.items.show', $item));
    }

    /**
     * 残りがあるうち、および予約可能数が未設定の商品は予約ページが開く
     */
    #[Test]
    public function 残りがあるか制限なしなら予約ページが開く(): void
    {
        $this->makeAreas();
        $limited = $this->makeReservable(limit: 3);
        $this->makeReservedBy($limited, 2);
        $unlimited = $this->makeReservable(limit: null, name: '制限なしセット');
        $this->makeReservedBy($unlimited, 1000);
        $user = $this->makeUser();

        $this->actingAs($user)->get(route('shop.reservation.index', $limited))->assertOk()->assertViewIs('shop.reservation');
        $this->get(route('shop.reservation.index', $unlimited))->assertOk()->assertViewIs('shop.reservation');
    }

    /**
     * カートは参照も変更もしない（AC-14）
     */
    #[Test]
    public function カートは参照も変更もしない(): void
    {
        $this->makeAreas();
        $item = $this->makeReservable();
        $carrot = $this->makeItem('人参', 2, 418);

        $this->actingAs($this->makeUser())
            ->withSession(['cart' => [$carrot->id => 1]])
            ->get(route('shop.reservation.index', $item))
            ->assertOk()
            ->assertDontSee('人参');

        $this->assertSame([$carrot->id => 1], session('cart'));
    }

    /**
     * 他の会員の予約は、予約ページの表示に影響しない（残数は商品ごとの合計）
     */
    #[Test]
    public function 予約の残数は商品ごとに数える(): void
    {
        $this->makeAreas();
        $item = $this->makeReservable(limit: 3);
        $other = $this->makeReservable(limit: 3, name: '別のセット');
        $this->makeReservedBy($other, 3);
        Reservation::factory()->create(['item_id' => $item->id, 'quantity' => 1]);

        $this->actingAs($this->makeUser())
            ->get(route('shop.reservation.index', $item))
            ->assertOk()
            ->assertSee('max="2"', false);
    }

    /**
     * Stripe の決済ページの「戻る」（?restore=1）は、復元の合図をフラッシュに置き換えてURLから外す
     */
    #[Test]
    public function restoreは合図をフラッシュに置き換えてリダイレクトする(): void
    {
        $item = $this->makeReservable();

        $this->actingAs($this->makeUser())
            ->get(route('shop.reservation.index', ['item' => $item, 'restore' => 1]))
            ->assertRedirect(route('shop.reservation.index', $item))
            ->assertSessionHas(ReservationService::RESTORE_FLASH_KEY, true);
    }

    /**
     * 「戻る」で戻った直後の1回だけ、保存待ちの予約の入力が復元され、次の訪問では会員登録の内容に戻る（AC-11, S-16, S-17）
     */
    #[Test]
    public function 戻った直後の1回だけ入力が復元される(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance);

        $this->actingAs($user)
            ->withSession([ReservationService::RESTORE_FLASH_KEY => true])
            ->get(route('shop.reservation.index', $item))
            ->assertSee('value="山田花子"', false)
            ->assertSee('value="08011112222"', false);

        // 次の訪問（フラッシュなし）は会員登録の内容
        $this->flushSession();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance);
        $this->get(route('shop.reservation.index', $item))
            ->assertSee('value="塩谷太郎"', false)
            ->assertDontSee('value="山田花子"', false);
    }

    /**
     * 購入の保存待ちの注文と、予約の保存待ちは別のセッションのキーに持ち、互いに干渉しない
     */
    #[Test]
    public function 購入の保存待ちの注文は予約ページに影響しない(): void
    {
        $this->makeAreas();
        [$user, $items] = $this->makeCartScenario();
        $item = $this->makeReservable();
        $this->putPending($user, $items, PaymentMethod::Card, 'cs_purchase');

        $this->actingAs($user)
            ->withSession([ReservationService::RESTORE_FLASH_KEY => true])
            ->get(route('shop.reservation.index', $item))
            ->assertOk()
            ->assertSee('value="塩谷太郎"', false)
            ->assertDontSee('value="山田花子"', false);

        $this->assertNotNull(session('pending_order'));
        $this->assertNull(session('pending_reservation'));
    }

    /**
     * 作った時点で支払い済みだった銀行振込の予約が保存待ちに残っていれば、予約ページを開いたときに保存してサンクスページへ進む
     */
    #[Test]
    public function 支払い済みの銀行振込の保存待ちは予約ページを開いたときに保存される(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable();
        // 振込先の案内がなく PaymentIntent だけある注文（作った時点で支払い済み）
        $order = $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance, null, 'pi_paid');
        session(['pending_reservation' => [...$order->toArray(), 'bank_transfer' => null]]);

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveBankTransferIntent')
                ->once()
                ->with('pi_paid')
                ->andReturn(new BankTransferIntentData('pi_paid', 'succeeded', 2980, null));
        });

        $this->actingAs($user)
            ->get(route('shop.reservation.index', $item))
            ->assertRedirect(route('shop.reservation.thanks'))
            ->assertSessionHas(ReservationService::THANKS_FLASH_KEY, true);

        $this->assertSame(1, Reservation::count());
        $this->assertNull(session('pending_reservation'));
    }
}
