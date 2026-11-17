<?php

namespace Tests\Feature;

use App\Livewire\AdminPurchaseDetail;
use App\Livewire\AdminReservationDetail;
use App\Models\Inquiry;
use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use App\Models\Reservation;
use App\Models\Reservation_bank_transfer;
use App\Models\User;
use App\Services\Payment\PaymentGatewayInterface;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 公開デモ用のサンプルデータ（DemoSeeder）のテスト
 *
 * DatabaseSeeder（商品・都道府県・管理者などの初期データ）の後に DemoSeeder を実行した状態で確かめる。
 */
class DemoSeederTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
    }

    /**
     * 件数：会員は初期データの1人に10人が加わり、注文24件・予約6件・お問い合わせ5件
     */
    #[Test]
    public function 件数は会員10人_注文24件_予約6件_お問い合わせ5件(): void
    {
        $this->assertSame(1 + DemoSeeder::USER_COUNT, User::count());
        $this->assertSame(DemoSeeder::PURCHASE_COUNT, Purchase_history::count());
        $this->assertSame(DemoSeeder::RESERVATION_COUNT, Reservation::count());
        $this->assertSame(DemoSeeder::INQUIRY_COUNT, Inquiry::count());
        $this->assertSame(30, Purchase_history::count() + Reservation::count());
    }

    /**
     * 会員のメールアドレスは example.com だけ（30文字以内）で、パスワードは同じ既知の値
     */
    #[Test]
    public function 会員は架空のメールアドレスで同じパスワード(): void
    {
        $demoUsers = User::query()->where('email', '!=', 'test@test.com')->get();

        $this->assertCount(DemoSeeder::USER_COUNT, $demoUsers);
        foreach ($demoUsers as $user) {
            $this->assertStringEndsWith('@example.com', $user->email);
            $this->assertLessThanOrEqual(30, mb_strlen($user->email));
            $this->assertTrue(Hash::check(DemoSeeder::PASSWORD, $user->password));
        }
        $this->assertStringEndsWith('@example.com', Inquiry::query()->firstOrFail()->email);
        $this->assertSame(0, Inquiry::query()->where('email', 'not like', '%@example.com')->count());
    }

    /**
     * 支払方法・状態の分布：3つの支払方法、発送済み・未発送・取消済みがそれぞれある（取消済みは発送済みにならない）
     */
    #[Test]
    public function 支払方法と状態が混ざっている(): void
    {
        foreach (['card', 'customer_balance', 'cod'] as $method) {
            $this->assertGreaterThan(0, Purchase_history::where('payment_method', $method)->count(), $method);
        }
        $this->assertSame(0, Purchase_history::whereNotIn('payment_method', ['card', 'customer_balance', 'cod'])->count());
        $this->assertSame(['card', 'customer_balance'], Reservation::query()->distinct()->orderBy('payment_method')->pluck('payment_method')->all());

        $this->assertSame(13, Purchase_history::where('shipped', true)->count());
        $this->assertSame(7, Purchase_history::where('shipped', false)->whereNull('canceled_at')->count());
        $this->assertSame(4, Purchase_history::whereNotNull('canceled_at')->count());
        $this->assertSame(0, Purchase_history::where('shipped', true)->whereNotNull('canceled_at')->count());

        $this->assertSame(3, Reservation::where('shipped', true)->count());
        $this->assertSame(2, Reservation::where('shipped', false)->whereNull('canceled_at')->count());
        $this->assertSame(1, Reservation::whereNotNull('canceled_at')->count());
        $this->assertSame(0, Reservation::where('shipped', true)->whereNotNull('canceled_at')->count());

        $this->assertSame(2, Inquiry::where('addressed', true)->count());
        $this->assertSame(3, Inquiry::where('addressed', false)->count());
    }

    /**
     * 日付は過去2か月（60日）以内に分散し、未来はなく、取消日時は作成日時より後
     */
    #[Test]
    public function 日付は過去2か月に分散する(): void
    {
        $records = Purchase_history::all()->concat(Reservation::all());

        foreach ($records as $record) {
            $this->assertTrue($record->created_at->lessThanOrEqualTo(now()), '作成日時が未来');
            $this->assertTrue($record->created_at->greaterThanOrEqualTo(now()->subDays(60)), '作成日時が2か月より前');
            if ($record->canceled_at !== null) {
                $this->assertTrue($record->canceled_at->greaterThan($record->created_at));
                $this->assertTrue($record->canceled_at->lessThanOrEqualTo(now()));
            }
        }
        $days = $records->map(fn ($record): string => $record->created_at->toDateString())->unique();
        $this->assertGreaterThanOrEqual(25, $days->count());
        $this->assertTrue($records->min('created_at')->lessThan(now()->subDays(40)));
    }

    /**
     * 金額：明細の小計・送料・代引手数料と合計が合う（送料は ShippingService の計算、代引手数料は config('shipping.cod_fee')）
     */
    #[Test]
    public function 注文と予約の金額が合計と一致する(): void
    {
        foreach (Purchase_history::with('purchaseHistoryItems')->get() as $purchase) {
            $subtotal = $purchase->purchaseHistoryItems->sum('subtotal');
            foreach ($purchase->purchaseHistoryItems as $line) {
                $this->assertSame($line->price * $line->quantity, $line->subtotal);
                $this->assertNotSame('', $line->item_name);
            }
            $this->assertGreaterThan(0, $purchase->shipping_fee);
            $this->assertSame($purchase->payment_method === 'cod' ? config('shipping.cod_fee') : 0, $purchase->cod_fee);
            $this->assertSame($subtotal + $purchase->shipping_fee + $purchase->cod_fee, $purchase->total_price);
            $this->assertMatchesRegularExpression('/\A\d{10,11}\z/', $purchase->delivery_tell);
        }

        foreach (Reservation::with('item')->get() as $reservation) {
            $this->assertTrue($reservation->item->is_reserved_only);
            $this->assertSame($reservation->price * $reservation->quantity, $reservation->subtotal);
            $this->assertSame($reservation->subtotal + $reservation->shipping_fee, $reservation->total_price);
            $this->assertSame($reservation->item->name, $reservation->item_name);
        }
    }

    /**
     * 銀行振込：振込先の行は銀行振込の注文・予約にだけあり、PaymentIntent の ID はダミー（pi_demo_）。
     * 未発送・未取消の銀行振込は作らない（管理画面の取消で Stripe に問い合わせて失敗するため）
     */
    #[Test]
    public function 銀行振込の振込先はダミーで未発送の銀行振込はない(): void
    {
        $transfers = Purchase_bank_transfer::all();
        $this->assertCount(Purchase_history::where('payment_method', 'customer_balance')->count(), $transfers);
        $this->assertSame($transfers->pluck('purchase_history_id')->sort()->values()->all(), Purchase_history::where('payment_method', 'customer_balance')->pluck('id')->sort()->values()->all());
        foreach ($transfers as $transfer) {
            $this->assertStringStartsWith('pi_demo_', $transfer->stripe_payment_intent_id);
            $this->assertSame($transfer->purchaseHistory->total_price, $transfer->amount);
        }
        $this->assertSame(0, Purchase_history::where('payment_method', 'customer_balance')->where('shipped', false)->whereNull('canceled_at')->count());
        // Stripe の顧客残高で支払い済みだった注文（振込先の5項目が null）と、振込先のある注文の両方がある
        $this->assertSame(1, $transfers->whereNull('bank_name')->count());
        $this->assertGreaterThan(0, $transfers->whereNotNull('bank_name')->count());

        $reservationTransfers = Reservation_bank_transfer::all();
        $this->assertCount(Reservation::where('payment_method', 'customer_balance')->count(), $reservationTransfers);
        foreach ($reservationTransfers as $transfer) {
            $this->assertStringStartsWith('pi_demo_', $transfer->stripe_payment_intent_id);
        }
        $this->assertSame(0, Reservation::where('payment_method', 'customer_balance')->where('shipped', false)->whereNull('canceled_at')->count());

        $ids = $transfers->pluck('stripe_payment_intent_id')->concat($reservationTransfers->pluck('stripe_payment_intent_id'));
        $this->assertSame($ids->count(), $ids->unique()->count());
    }

    /**
     * 管理画面：注文・予約・会員・お問い合わせの一覧が表示できる
     */
    #[Test]
    public function 管理画面の一覧が表示できる(): void
    {
        $this->loginAsAdmin();

        $this->get(route('admin.purchase.index'))->assertOk()->assertSee('青木 一郎');
        $this->get(route('admin.reservations.index'))->assertOk()->assertSee('岡田 翔太');
        $this->get(route('admin.users.index'))->assertOk()->assertSee('青木');
        $this->get(route('admin.inquiries.index'))->assertOk()->assertSee('木村');
    }

    /**
     * 管理画面：すべての注文・予約の詳細モーダルが、エラーなく表示できる
     */
    #[Test]
    public function すべての注文と予約の詳細が表示できる(): void
    {
        $this->loginAsAdmin();

        foreach (Purchase_history::all() as $purchase) {
            Livewire::test(AdminPurchaseDetail::class)
                ->dispatch('open-purchase-detail', id: $purchase->id)
                ->assertSet('showDetail', true)
                ->assertSee($purchase->delivery_name);
        }
        foreach (Reservation::all() as $reservation) {
            Livewire::test(AdminReservationDetail::class)
                ->dispatch('open-reservation-detail', id: $reservation->id)
                ->assertSet('showDetail', true)
                ->assertSee($reservation->delivery_name);
        }
    }

    /**
     * 管理画面：未発送の注文・予約の取消と発送の更新が、Stripe を呼ばずにエラーなくできる
     */
    #[Test]
    public function 未発送の注文と予約は取消も発送もできる(): void
    {
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('retrieveBankTransferIntent');
            $mock->shouldNotReceive('cancelPaymentIntent');
        });
        $this->loginAsAdmin();

        $pendingOrders = Purchase_history::where('shipped', false)->whereNull('canceled_at')->get();
        $this->assertCount(7, $pendingOrders);
        $cancelOrder = $pendingOrders->firstWhere('payment_method', 'cod');
        $shipOrder = $pendingOrders->firstWhere('payment_method', 'card');
        $cancelReservation = Reservation::where('shipped', false)->whereNull('canceled_at')->firstOrFail();

        $this->post(route('admin.purchase.cancel'), ['id' => $cancelOrder->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($cancelOrder->fresh()->canceled_at);

        $this->postJson(route('admin.purchase.update'), ['id' => $shipOrder->id, 'shipped' => true])->assertOk();
        $this->assertTrue($shipOrder->fresh()->shipped);

        $this->post(route('admin.reservations.cancel'), ['id' => $cancelReservation->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($cancelReservation->fresh()->canceled_at);
    }

    /**
     * 管理画面の「未発送の注文」のチェックで、取消済みの注文は発送済みにできない（デモデータでも取消済みは取消済みのまま）
     */
    #[Test]
    public function 取消済みの注文は発送済みにできない(): void
    {
        $this->loginAsAdmin();
        $canceled = Purchase_history::whereNotNull('canceled_at')->firstOrFail();

        $this->postJson(route('admin.purchase.update'), ['id' => $canceled->id, 'shipped' => true])->assertStatus(409);
        $this->assertFalse($canceled->fresh()->shipped);
    }

    /**
     * 会員としてログインでき、マイページが表示できる（既知のパスワード）
     */
    #[Test]
    public function デモの会員はログインしてマイページを開ける(): void
    {
        $this->post(route('login.store'), ['email' => 'demo03@example.com', 'password' => DemoSeeder::PASSWORD])->assertRedirect();
        $this->assertAuthenticated();

        $this->get(route('shop.mypage.index'))->assertOk()->assertSee('上野');
    }

    /**
     * 二重に実行しても増えない
     */
    #[Test]
    public function 二重に実行しても増えない(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(1 + DemoSeeder::USER_COUNT, User::count());
        $this->assertSame(DemoSeeder::PURCHASE_COUNT, Purchase_history::count());
        $this->assertSame(DemoSeeder::RESERVATION_COUNT, Reservation::count());
    }

    /**
     * DatabaseSeeder からは呼ばない（`db:seed --class=DemoSeeder` で明示したときだけ。開発・E2E のデータにデモのデータを混ぜない）
     */
    #[Test]
    public function databaseseederからはdemoseederを呼ばない(): void
    {
        $source = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));
        $this->assertStringNotContainsString('DemoSeeder', $source);
    }

    /**
     * 本番のイメージは --no-dev（faker なし）で作るため、シーダーは faker・ファクトリを使わない
     */
    #[Test]
    public function シーダーはfakerとファクトリを使わない(): void
    {
        foreach (glob(database_path('seeders/*.php')) ?: [] as $file) {
            $source = (string) file_get_contents($file);
            // コメント（docblock・行コメント）は除いて確かめる
            $code = preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', $source);
            $this->assertDoesNotMatchRegularExpression('/\bfake\s*\(|::factory\s*\(|Faker\\\\/', (string) $code, basename($file));
        }
    }
}
