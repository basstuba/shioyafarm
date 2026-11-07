<?php

namespace Tests\Feature;

use App\Enums\AdminListType;
use App\Http\Controllers\AdminPurchaseController;
use App\Http\Controllers\AdminReservationController;
use App\Models\Purchase_history;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Payment\BankTransferInstructions;
use App\Services\Payment\BankTransferIntentData;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の注文・予約の取消（POST admin.purchase.cancel・admin.reservations.cancel）のテスト
 *
 * 注文と予約は同じ処理（AdminOrderCancelService）のため、データプロバイダーで両方に同じ確認をする。
 */
class AdminOrderCancelTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 注文・予約の両方で確かめるときのデータ
     *
     * @return array<string, array{AdminListType}>
     */
    public static function types(): array
    {
        return ['注文' => [AdminListType::Purchase], '予約' => [AdminListType::Reservation]];
    }

    /**
     * 注文または予約を作る（会員を指定しないときは新しい会員）
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeTarget(AdminListType $type, string $method = 'card', array $overrides = [], ?User $user = null): Purchase_history|Reservation
    {
        return match ($type) {
            AdminListType::Purchase => $this->makeOrder($user, method: $method, overrides: $overrides),
            AdminListType::Reservation => $this->makeAdminReservation($user, method: $method, overrides: $overrides),
        };
    }

    /**
     * 銀行振込の注文または予約を、振込先つきで作る（Stripe の PaymentIntent は $paymentIntentId）
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeBankTransferTarget(AdminListType $type, string $paymentIntentId = 'pi_test_cancel', bool $paid = false, array $overrides = [], ?User $user = null): Purchase_history|Reservation
    {
        $target = $this->makeTarget($type, 'customer_balance', $overrides, $user);
        match ($type) {
            AdminListType::Purchase => $this->attachPurchaseBankTransfer($target, $paid),
            AdminListType::Reservation => $this->attachReservationBankTransfer($target, $paid),
        };
        $target->{$type === AdminListType::Purchase ? 'purchaseBankTransfer' : 'reservationBankTransfer'}()->update(['stripe_payment_intent_id' => $paymentIntentId]);

        return $target;
    }

    /**
     * クレジットカードを取り消したときの案内
     */
    private function cardMessage(AdminListType $type): string
    {
        return $type->subject().'を取り消しました。クレジットカードの返金は、Stripeの管理画面で行ってください。';
    }

    /**
     * 最新の状態を取得する
     */
    private function reload(Purchase_history|Reservation $target): Purchase_history|Reservation
    {
        return $target::query()->findOrFail($target->id);
    }

    /**
     * 管理者として取消を送る
     */
    private function cancel(AdminListType $type, mixed $id): TestResponse
    {
        return $this->post(route($type->cancelRoute()), ['id' => $id]);
    }

    /**
     * Stripe を一切呼ばない状態にする（クレジットカード・代金引換の取消）
     */
    private function expectNoStripeCall(): void
    {
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            foreach (['retrieveBankTransferIntent', 'cancelPaymentIntent', 'createBankTransferIntent', 'retrieveCheckoutSession', 'expireCheckoutSession', 'ensureCustomer', 'createCheckoutSession'] as $method) {
                $mock->shouldNotReceive($method);
            }
        });
    }

    /**
     * PaymentIntent の状態を返す Stripe のモック（$cancelCalls は、取り消しの呼び出しの期待する回数）
     */
    private function mockIntent(string $id, string $status, int $cancelCalls, ?PaymentGatewayException $cancelError = null): void
    {
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($id, $status, $cancelCalls, $cancelError): void {
            $mock->shouldReceive('retrieveBankTransferIntent')->once()->with($id)->andReturn(new BankTransferIntentData($id, $status, 1270, null));
            $expectation = $mock->shouldReceive('cancelPaymentIntent')->with($id);
            if ($cancelCalls === 0) {
                $expectation->never();
            } elseif ($cancelError !== null) {
                $expectation->times($cancelCalls)->andThrow($cancelError);
            } else {
                $expectation->times($cancelCalls);
            }
        });
    }

    /**
     * 2ルートは決められたコントローラーが POST で受け、管理者の認証と no-store が付く
     */
    #[Test]
    public function ルートは決められたコントローラーが受け管理者の認証とno_storeを要する(): void
    {
        $routes = [
            'admin.purchase.cancel' => [AdminPurchaseController::class.'@cancel', '/admin/purchase/cancel'],
            'admin.reservations.cancel' => [AdminReservationController::class.'@cancel', '/admin/reservations/cancel'],
        ];

        foreach ($routes as $name => [$action, $uri]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertSame($action, $route->getActionName(), $name);
            $this->assertSame(['POST'], array_values(array_diff($route->methods(), ['OPTIONS'])), $name);
            $this->assertSame($uri, '/'.$route->uri(), $name);
            $this->assertContains('auth:admin', $route->gatherMiddleware(), $name);
            $this->assertContains('no-store', $route->gatherMiddleware(), $name);
        }
    }

    /**
     * 未発送のクレジットカード・代金引換は、Stripe を呼ばずに取消日時が入り、一覧に戻って案内が出る（AC-5）
     */
    #[Test]
    #[DataProvider('types')]
    public function カードと代金引換は_stripeを呼ばずに取り消せる(AdminListType $type): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 6, 10, 0, 0, 'Asia/Tokyo'));
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        // 予約は先払いのため代金引換はないが、処理は支払方法の値だけで決まるので、同じように確認する
        $methods = ['card', 'cod'];

        foreach ($methods as $method) {
            $target = $this->makeTarget($type, $method);

            $this->cancel($type, $target->id)
                ->assertRedirect(route($type->listRoute()))
                ->assertSessionHas('list_status', $method === 'card' ? $this->cardMessage($type) : $type->subject().'を取り消しました。')
                ->assertSessionHasNoErrors();

            $fresh = $this->reload($target);
            $this->assertSame('2026-11-06 10:00:00', $fresh->canceled_at?->format('Y-m-d H:i:s'), $method);
            $this->assertFalse($fresh->shipped);
        }
    }

    /**
     * クレジットカードは、取消のあとに返金の案内（Stripeの管理画面で行う）を出す。代金引換には出さない（AC-5）
     */
    #[Test]
    #[DataProvider('types')]
    public function カードの取消は返金の案内を出し代金引換は出さない(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $subject = $type->subject();
        $card = $this->makeTarget($type, 'card');
        $cod = $this->makeTarget($type, 'cod');

        $this->cancel($type, $card->id)->assertSessionHas('list_status', "{$subject}を取り消しました。クレジットカードの返金は、Stripeの管理画面で行ってください。");
        $this->cancel($type, $cod->id)->assertSessionHas('list_status', "{$subject}を取り消しました。");

        // 一覧にも緑の文字で出る
        $this->from(route($type->listRoute()))->followingRedirects()->post(route($type->cancelRoute()), ['id' => $this->makeTarget($type, 'card')->id])
            ->assertSee("{$subject}を取り消しました。クレジットカードの返金は、Stripeの管理画面で行ってください。");
    }

    /**
     * 銀行振込の入金処理中（processing）は、入金済みと同じく Stripe を変えずに取消済みにし、入金済みの案内を出す（AC-7）
     */
    #[Test]
    #[DataProvider('types')]
    public function 銀行振込の入金処理中はstripeを変えずに取り消し返金の案内を出す(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeBankTransferTarget($type, 'pi_processing_ok');
        $this->mockIntent('pi_processing_ok', 'processing', 0);

        $this->cancel($type, $target->id)
            ->assertSessionHas('list_status', $type->subject().'を取り消しました。入金済みのため、返金はStripeの管理画面で行ってください。')
            ->assertSessionHasNoErrors();

        $this->assertNotNull($this->reload($target)->canceled_at);
    }

    /**
     * 一部入金済み（振込の残りが PaymentIntent の金額より小さい。実際の Stripe では amount_received は 0 のまま）は、PaymentIntent を取り消したうえで、入金済みの案内を出す（AC-7）
     */
    #[Test]
    #[DataProvider('types')]
    public function 銀行振込の一部入金済みは支払いを取り消して返金の案内を出す(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeBankTransferTarget($type, 'pi_partial');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            // 金額 1270 のうち 500 円が入金済み（振込の残り 770 円）。amount_received には反映されない
            $mock->shouldReceive('retrieveBankTransferIntent')->once()->with('pi_partial')
                ->andReturn(new BankTransferIntentData('pi_partial', 'requires_action', 1270, new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 770), 770));
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_partial');
        });

        $this->cancel($type, $target->id)
            ->assertSessionHas('list_status', $type->subject().'を取り消しました。入金済みのため、返金はStripeの管理画面で行ってください。');

        $this->assertNotNull($this->reload($target)->canceled_at);
    }

    /**
     * 一部入金の判定は振込の残り（amountRemaining）だけで行う。全銀の振込先（instructions）を読めなくても判定できる
     */
    #[Test]
    #[DataProvider('types')]
    public function 振込先を読めなくても振込の残りだけで一部入金を判定できる(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $partial = $this->makeBankTransferTarget($type, 'pi_remaining_partial');
        $none = $this->makeBankTransferTarget($type, 'pi_remaining_none');
        $unknown = $this->makeBankTransferTarget($type, 'pi_remaining_unknown');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveBankTransferIntent')->with('pi_remaining_partial')->once()->andReturn(new BankTransferIntentData('pi_remaining_partial', 'requires_action', 1270, null, 770));
            $mock->shouldReceive('retrieveBankTransferIntent')->with('pi_remaining_none')->once()->andReturn(new BankTransferIntentData('pi_remaining_none', 'requires_action', 1270, null, 1270));
            $mock->shouldReceive('retrieveBankTransferIntent')->with('pi_remaining_unknown')->once()->andReturn(new BankTransferIntentData('pi_remaining_unknown', 'requires_action', 1270, null, null));
            $mock->shouldReceive('cancelPaymentIntent')->times(3);
        });

        $this->cancel($type, $partial->id)->assertSessionHas('list_status', $type->subject().'を取り消しました。入金済みのため、返金はStripeの管理画面で行ってください。');
        $this->cancel($type, $none->id)->assertSessionHas('list_status', $type->subject().'を取り消しました。');
        // 振込の残りが分からないとき（next_action が無い）は、一部入金とは判定しない
        $this->cancel($type, $unknown->id)->assertSessionHas('list_status', $type->subject().'を取り消しました。');
    }

    /**
     * 振込の残りが PaymentIntent の金額と同じ（未入金）ときは、取り消すだけで、入金済みの案内は出ない
     */
    #[Test]
    #[DataProvider('types')]
    public function 振込の残りが金額と同じなら未入金として取り消す(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeBankTransferTarget($type, 'pi_none');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveBankTransferIntent')->once()
                ->andReturn(new BankTransferIntentData('pi_none', 'requires_action', 1270, new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 1270), 1270));
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_none');
        });

        $this->cancel($type, $target->id)->assertSessionHas('list_status', $type->subject().'を取り消しました。');
    }

    /**
     * 再実行：前回、Stripe の取消だけ成功して保存に失敗した（Failed）あと、再度取り消すと、status は canceled になっている。
     * 取り消したあとの PaymentIntent には一部入金の手がかりが残らない（amount_received も 0）ため、入金があった場合の確認の案内を出す
     */
    #[Test]
    #[DataProvider('types')]
    public function 再実行ではcanceledなので入金があった場合の確認の案内を出す(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeBankTransferTarget($type, 'pi_retry');
        $calls = 0;
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use (&$calls): void {
            // 1回目：requires_action で一部入金（振込の残り 770 円）→ Stripe を取り消す（保存に失敗）。2回目：canceled（手がかりなし）→ 取り消さない
            $mock->shouldReceive('retrieveBankTransferIntent')->twice()->with('pi_retry')->andReturnUsing(function () use (&$calls): BankTransferIntentData {
                return ++$calls === 1
                    ? new BankTransferIntentData('pi_retry', 'requires_action', 1270, new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 770))
                    : new BankTransferIntentData('pi_retry', 'canceled', 1270, null);
            });
            $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_retry');
        });
        $fail = true;
        $target::saving(function () use (&$fail): void {
            if ($fail) {
                throw new QueryException('mysql', 'update `x` set `canceled_at` = ?', [1], new RuntimeException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction'));
            }
        });

        $this->cancel($type, $target->id)->assertSessionHasErrors(['cancel' => '取り消せませんでした。時間をおいて、もう一度お試しください。']);
        $this->assertNull($this->reload($target)->canceled_at);

        $fail = false;
        $this->cancel($type, $target->id)
            ->assertSessionHas('list_status', $type->subject().'を取り消しました。入金があった場合は、返金はStripeの管理画面で行ってください。')
            ->assertSessionHasNoErrors();
        $this->assertNotNull($this->reload($target)->canceled_at);
    }

    /**
     * 一覧にも、入金があった場合の確認の案内が緑の文字で出る
     */
    #[Test]
    #[DataProvider('types')]
    public function canceledの案内は一覧に緑の文字で出る(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeBankTransferTarget($type, 'pi_maybe');
        $this->mockIntent('pi_maybe', 'canceled', 0);
        $message = $type->subject().'を取り消しました。入金があった場合は、返金はStripeの管理画面で行ってください。';

        $html = $this->from(route($type->listRoute()))->followingRedirects()->post(route($type->cancelRoute()), ['id' => $target->id])->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#</form>\s*<p class="mt-3 w-full text-center text-base text-accent-hover" role="status">'.preg_quote($message, '#').'</p>#su', $html);
    }

    /**
     * 一部入金済みで PaymentIntent を取り消せなかったときは、取消済みにしない
     */
    #[Test]
    public function 一部入金済みで取り消せないと取消済みにならない(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeBankTransferTarget(AdminListType::Purchase, 'pi_partial_ng');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveBankTransferIntent')->once()
                ->andReturn(new BankTransferIntentData('pi_partial_ng', 'requires_action', 1270, new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 770), 770));
            $mock->shouldReceive('cancelPaymentIntent')->once()->andThrow(new PaymentGatewayException('失敗'));
        });

        $this->cancel(AdminListType::Purchase, $target->id)->assertSessionHasErrors(['cancel' => '取り消せませんでした。時間をおいて、もう一度お試しください。']);

        $this->assertNull($this->reload($target)->canceled_at);
    }

    /**
     * 銀行振込の未入金は、Stripe の PaymentIntent も取り消してから取消済みにする（AC-6）
     */
    #[Test]
    #[DataProvider('types')]
    public function 銀行振込の未入金はstripeの支払いも取り消す(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeBankTransferTarget($type, 'pi_unpaid');
        $this->mockIntent('pi_unpaid', 'requires_action', 1);

        $this->cancel($type, $target->id)
            ->assertRedirect(route($type->listRoute()))
            ->assertSessionHas('list_status', $type->subject().'を取り消しました。');

        $this->assertNotNull($this->reload($target)->canceled_at);
    }

    /**
     * 銀行振込の入金済み（succeeded）は、Stripe を変えずに取消済みにし、返金の案内を出す（AC-7）。顧客残高で支払い済みの振込先（口座の項目が null）も同じ
     */
    #[Test]
    #[DataProvider('types')]
    public function 銀行振込の入金済みはstripeを変えずに取り消し返金の案内を出す(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeBankTransferTarget($type, 'pi_paid', true);
        $this->mockIntent('pi_paid', 'succeeded', 0);

        $this->cancel($type, $target->id)
            ->assertRedirect(route($type->listRoute()))
            ->assertSessionHas('list_status', $type->subject().'を取り消しました。入金済みのため、返金はStripeの管理画面で行ってください。')
            ->assertSessionHasNoErrors();

        $this->assertNotNull($this->reload($target)->canceled_at);
    }

    /**
     * すでに Stripe 側で canceled の PaymentIntent は、取り消しを呼ばずに成功扱いで取消済みにする
     */
    #[Test]
    #[DataProvider('types')]
    public function すでにcanceledの支払いは成功扱いで取り消せる(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeBankTransferTarget($type, 'pi_already');
        $this->mockIntent('pi_already', 'canceled', 0);

        $this->cancel($type, $target->id)->assertSessionHas('list_status', $type->subject().'を取り消しました。入金があった場合は、返金はStripeの管理画面で行ってください。');

        $this->assertNotNull($this->reload($target)->canceled_at);
    }

    /**
     * 振込先の行がない銀行振込は、Stripe を呼ばずに取り消す
     */
    #[Test]
    #[DataProvider('types')]
    public function 振込先の行がない銀行振込はstripeを呼ばずに取り消す(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $target = $this->makeTarget($type, 'customer_balance');

        $this->cancel($type, $target->id)->assertSessionHas('list_status', $type->subject().'を取り消しました。');

        $this->assertNotNull($this->reload($target)->canceled_at);
    }

    /**
     * Stripe の状態を取得できなかったときは、取消済みにせず、赤字の案内を出してログに残す（AC-8）
     */
    #[Test]
    #[DataProvider('types')]
    public function stripeの状態を取得できないと取り消せない(AdminListType $type): void
    {
        Log::spy();
        $this->loginAsAdmin();
        $target = $this->makeBankTransferTarget($type, 'pi_down');
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveBankTransferIntent')->once()->andThrow(new PaymentGatewayException('通信エラー'));
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        $this->cancel($type, $target->id)
            ->assertRedirect(route($type->listRoute()))
            ->assertSessionHasErrors(['cancel' => '取り消せませんでした。時間をおいて、もう一度お試しください。'])
            ->assertSessionMissing('list_status');

        $this->assertNull($this->reload($target)->canceled_at);
        Log::shouldHaveReceived('error')->once();
    }

    /**
     * 支払いの取消に失敗したとき（取り消せない状態など）は、取消済みにしない（AC-8）
     */
    #[Test]
    #[DataProvider('types')]
    public function stripeの支払いを取り消せないと取消済みにならない(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeBankTransferTarget($type, 'pi_processing');
        $this->mockIntent('pi_processing', 'requires_action', 1, new PaymentGatewayException('取り消せない状態'));

        $this->cancel($type, $target->id)
            ->assertSessionHasErrors(['cancel' => '取り消せませんでした。時間をおいて、もう一度お試しください。']);

        $this->assertNull($this->reload($target)->canceled_at);
    }

    /**
     * 発送済みは、リクエストを直接送っても取り消せず、データは変わらない（AC-9）
     */
    #[Test]
    #[DataProvider('types')]
    public function 発送済みは取り消せない(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $target = $this->makeTarget($type, overrides: ['shipped' => true]);

        $this->cancel($type, $target->id)
            ->assertRedirect(route($type->listRoute()))
            ->assertSessionHasErrors(['cancel' => '発送済みのため取り消せません。'])
            ->assertSessionMissing('list_status');

        $fresh = $this->reload($target);
        $this->assertNull($fresh->canceled_at);
        $this->assertTrue($fresh->shipped);
    }

    /**
     * 発送済みの銀行振込は、Stripe に触れず取り消せない
     */
    #[Test]
    #[DataProvider('types')]
    public function 発送済みの銀行振込はstripeに触れず取り消せない(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $target = $this->makeBankTransferTarget($type, overrides: ['shipped' => true]);

        $this->cancel($type, $target->id)->assertSessionHasErrors(['cancel' => '発送済みのため取り消せません。']);

        $this->assertNull($this->reload($target)->canceled_at);
    }

    /**
     * 取消済みは、もう一度取り消せず、取消日時は変わらない（AC-9）
     */
    #[Test]
    #[DataProvider('types')]
    public function 取消済みは取り消せない(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $target = $this->makeBankTransferTarget($type, overrides: ['canceled_at' => '2026-11-01 09:00:00']);

        $this->cancel($type, $target->id)
            ->assertRedirect(route($type->listRoute()))
            ->assertSessionHasErrors(['cancel' => 'すでに取り消されています。'])
            ->assertSessionMissing('list_status');

        $this->assertSame('2026-11-01 09:00:00', $this->reload($target)->canceled_at?->format('Y-m-d H:i:s'));
    }

    /**
     * 存在しないIDは、取り消されず、画面は壊れない（AC-9）
     */
    #[Test]
    #[DataProvider('types')]
    public function 存在しないidは取り消せない(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $other = $this->makeTarget($type);

        $this->cancel($type, 999999)
            ->assertRedirect(route($type->listRoute()))
            ->assertSessionHasErrors(['cancel' => $type->subject().'が見つかりません。']);

        $this->assertNull($this->reload($other)->canceled_at);
    }

    /**
     * IDが不正（空・文字・配列）なときは、入力の誤りとして一覧に戻りエラーになる。何も取り消されない
     */
    #[Test]
    #[DataProvider('types')]
    public function idが不正なときはエラーになる(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $target = $this->makeTarget($type);
        $searchUrl = route($type->searchRoute(), ['shipped' => '0']);
        $subject = $type->subject();

        $this->from($searchUrl)->post(route($type->cancelRoute()))->assertRedirect($searchUrl)->assertSessionHasErrors(['id' => "取り消す{$subject}を指定してください。"]);
        $this->from($searchUrl)->cancel($type, 'abc')->assertRedirect($searchUrl)->assertSessionHasErrors(['id' => "取り消す{$subject}が正しくありません。"]);
        $this->from($searchUrl)->cancel($type, ['1'])->assertRedirect($searchUrl)->assertSessionHasErrors('id');

        $this->assertNull($this->reload($target)->canceled_at);
    }

    /**
     * 未ログイン・会員としてのログインでは取り消せず、管理者用のログインページへ移動する（AC-10）
     */
    #[Test]
    #[DataProvider('types')]
    public function 未ログインと会員では取り消せない(AdminListType $type): void
    {
        $this->expectNoStripeCall();
        $target = $this->makeTarget($type);

        $this->cancel($type, $target->id)->assertRedirect(route('admin.login'));

        $this->loginAsMember();
        $this->cancel($type, $target->id)->assertRedirect(route('admin.login'));

        $this->assertNull($this->reload($target)->canceled_at);
    }

    /**
     * 退会済みの会員の注文・予約も取り消せる（AC-18）
     */
    #[Test]
    #[DataProvider('types')]
    public function 退会済みの会員の注文と予約も取り消せる(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $user = $this->makeWithdrawnUser();
        $target = $this->makeTarget($type, user: $user);

        $this->cancel($type, $target->id)->assertSessionHas('list_status', $this->cardMessage($type));

        $this->assertNotNull($this->reload($target)->canceled_at);
    }

    /**
     * 取り消しても、ほかの注文・予約には影響しない。取り消しのメールは送られない（AC-21）
     */
    #[Test]
    #[DataProvider('types')]
    public function 取り消してもほかのデータは変わらずメールは送られない(AdminListType $type): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $target = $this->makeTarget($type);
        $other = $this->makeTarget($type);

        $this->cancel($type, $target->id);

        $this->assertNull($this->reload($other)->canceled_at);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    /**
     * 取消のあとは、取消の前に見ていた検索条件・ページの一覧に戻る（成功・失敗とも）（AC-5）
     */
    #[Test]
    #[DataProvider('types')]
    public function 取消のあとは取消の前に見ていた一覧へ戻る(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $target = $this->makeTarget($type);
        $shipped = $this->makeTarget($type, overrides: ['shipped' => true]);
        $searchUrl = route($type->searchRoute(), ['shipped' => '0', 'name' => '山田', 'page' => 2]);
        $pageUrl = route($type->listRoute(), ['page' => 3]);

        // 成功：検索結果
        $this->from($searchUrl)->cancel($type, $target->id)->assertRedirect($searchUrl)->assertSessionHas('list_status');
        // 失敗：ページ番号つきの一覧
        $this->from($pageUrl)->cancel($type, $shipped->id)->assertRedirect($pageUrl)->assertSessionHasErrors('cancel');
        $this->from($pageUrl)->cancel($type, 999999)->assertRedirect($pageUrl)->assertSessionHasErrors('cancel');
    }

    /**
     * 戻り先は一覧・検索結果のURLだけで、ほかのページ・外部のURLのときは一覧（全件）に戻る。スキームやホストは使わない
     */
    #[Test]
    #[DataProvider('types')]
    public function 戻り先が一覧以外のときは一覧に戻りホストは使わない(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $index = route($type->listRoute());

        foreach ([route('admin.users.index'), 'https://evil.example.com/admin/purchase', $index.'/cancel', route($type === AdminListType::Purchase ? 'admin.reservations.index' : 'admin.purchase.index')] as $referer) {
            $target = $this->makeTarget($type);
            $this->from($referer)->cancel($type, $target->id)->assertRedirect($index);
        }
        // 戻り先がないとき
        $target = $this->makeTarget($type);
        $this->cancel($type, $target->id)->assertRedirect($index);

        // スキームやホストが違っても（プロキシの背後など）、自分のホストの相対URLとして戻る。外部のホストには移動しない
        $path = route($type->searchRoute(), ['shipped' => '0', 'page' => 2], false);
        foreach (['https://localhost'.$path, 'https://evil.example.com'.$path] as $referer) {
            $target = $this->makeTarget($type);
            $response = $this->from($referer)->cancel($type, $target->id);
            $response->assertRedirect('http://localhost'.$path);
        }
    }

    /**
     * 成功の案内は検索欄の下に、背景・枠なしの緑の文字で1回だけ出る。layout の案内（flash の status）には出ない（AC-5）
     */
    #[Test]
    #[DataProvider('types')]
    public function 成功の案内は検索欄の下に緑の文字で出る(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $target = $this->makeTarget($type, 'cod');
        $message = $type->subject().'を取り消しました。';

        $this->from(route($type->listRoute()))->cancel($type, $target->id)->assertSessionHas('list_status')->assertSessionMissing('status');
        $html = $this->get(route($type->listRoute()))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, $message));
        $this->assertMatchesRegularExpression('#role="search".*?</form>\s*<p class="mt-3 w-full text-center text-base text-accent-hover" role="status">'.preg_quote($message, '#').'</p>#su', $html);
        // 次の表示では消える
        $this->assertStringNotContainsString($message, $this->get(route($type->listRoute()))->getContent());
    }

    /**
     * 失敗の案内は、赤字のエラーとして同じ場所に出る。取消のエラーでも、エクスポートは押せる（検索条件の誤りではないため）
     */
    #[Test]
    #[DataProvider('types')]
    public function 失敗の案内は赤字で出てエクスポートは押せる(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeTarget($type, overrides: ['shipped' => true]);

        $this->from(route($type->listRoute()))->cancel($type, $target->id);
        $html = $this->get(route($type->listRoute()))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#</form>\s*<ul class="mt-3 w-full text-center text-base text-danger" role="alert">\s*<li>発送済みのため取り消せません。</li>#su', $html);
        $this->assertStringNotContainsString('class="mt-3 w-full text-center text-base text-accent-hover"', $html);
        $this->assertMatchesRegularExpression('#type="submit"\s*>エクスポート</button>#u', $html);
    }

    /**
     * 取消のあとの一覧は、取り消した行が「取消済」になって残る
     */
    #[Test]
    #[DataProvider('types')]
    public function 取消のあとも一覧に残り取消済と表示される(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $target = $this->makeTarget($type);

        $this->followingRedirects()->from(route($type->listRoute()))->cancel($type, $target->id)
            ->assertOk()
            ->assertSee($type->subject().'を取り消しました。')
            ->assertSee('取消済');
    }

    /**
     * 最後の1件を取り消して、そのページがなくなったときは、戻り先の一覧が最終ページを表示する
     */
    #[Test]
    public function 最後の1件を取り消してページがなくなると最終ページが表示される(): void
    {
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        // 未発送の検索で、2ページ目の1件だけを取り消す（取り消すと未発送は10件になり、2ページ目がなくなる）
        $orders = $this->makeOrders(11);
        $pageUrl = route('admin.purchase.search', ['shipped' => '0', 'page' => 2]);

        $this->from($pageUrl)->followingRedirects()->post(route('admin.purchase.cancel'), ['id' => $orders[0]->id])
            ->assertOk()
            ->assertSee('注文を取り消しました。')
            ->assertViewHas('purchases', fn ($paginator): bool => $paginator->currentPage() === 1 && $paginator->total() === 10 && count($paginator->items()) === 10);
    }

    /**
     * CSRFトークンが古いとき、ログイン中なら見ていた一覧へ案内つきで戻り、取り消されない。赤字のエラーとして出る
     */
    #[Test]
    #[DataProvider('types')]
    public function トークンが古いと案内つきで一覧へ戻り取り消されない(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeTarget($type);
        $this->app['env'] = 'local';
        $searchUrl = route($type->searchRoute(), ['shipped' => '0']);

        $response = $this->withSession(['_token' => 'valid-token'])->from($searchUrl)
            ->post(route($type->cancelRoute()), ['_token' => 'stale-token', 'id' => $target->id]);

        $response->assertRedirect($searchUrl);
        $response->assertSessionHasErrors(['cancel' => 'ページの有効期限が切れました。もう一度取り消してください。']);
        $response->assertSessionMissing('status');
        $response->assertSessionMissing('list_status');
        $this->assertNull($this->reload($target)->canceled_at);

        $html = $this->get($searchUrl)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#role="alert">\s*<li>ページの有効期限が切れました。もう一度取り消してください。</li>#su', $html);
    }

    /**
     * CSRFトークンが古く、すでにログアウトしているときは、管理者用のログインページへ移動する
     */
    #[Test]
    #[DataProvider('types')]
    public function トークンが古く未ログインならログインページへ移動する(AdminListType $type): void
    {
        $target = $this->makeTarget($type);
        $this->app['env'] = 'local';

        $this->withSession(['_token' => 'valid-token'])
            ->post(route($type->cancelRoute()), ['_token' => 'stale-token', 'id' => $target->id])
            ->assertRedirect(route('admin.login'));

        $this->assertNull($this->reload($target)->canceled_at);
    }

    /**
     * 行のロックを取る取消の判定と取消日時の記録は、同じトランザクションの中で行う（Stripe の取消もその間に行う）
     */
    #[Test]
    #[DataProvider('types')]
    public function 判定と記録は同じトランザクションの中で行われる(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeBankTransferTarget($type, 'pi_tx');
        $outside = DB::transactionLevel();
        $levelAtStripe = null;
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use (&$levelAtStripe): void {
            $mock->shouldReceive('retrieveBankTransferIntent')->once()->andReturnUsing(function () use (&$levelAtStripe): BankTransferIntentData {
                $levelAtStripe = DB::transactionLevel();

                return new BankTransferIntentData('pi_tx', 'requires_action', 1270, null);
            });
            $mock->shouldReceive('cancelPaymentIntent')->once();
        });
        $levelAtSave = null;
        $target::saving(function () use (&$levelAtSave): void {
            $levelAtSave = DB::transactionLevel();
        });

        $this->cancel($type, $target->id)->assertSessionHas('list_status');

        $this->assertGreaterThan($outside, $levelAtStripe);
        $this->assertGreaterThan($outside, $levelAtSave);
    }

    /**
     * 取消日時を記録できなかったとき（例外）は、取消済みにならない
     */
    #[Test]
    public function 記録に失敗したときは取消済みにならない(): void
    {
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $target = $this->makeTarget(AdminListType::Purchase);
        Purchase_history::saving(function (): never {
            throw new RuntimeException('保存の失敗');
        });

        try {
            $this->withoutExceptionHandling()->cancel(AdminListType::Purchase, $target->id);
            $this->fail('例外が出るはず');
        } catch (RuntimeException $e) {
            $this->assertSame('保存の失敗', $e->getMessage());
        }

        $this->assertNull($this->reload($target)->canceled_at);
    }

    /**
     * 同時実行のエラー（ロック待ちのタイムアウトなど）のときは、500にせず、同じ一覧へ戻って案内し、ログに残す。取消済みにならない
     */
    #[Test]
    #[DataProvider('types')]
    public function データベースのエラーのときは案内を出して一覧へ戻る(AdminListType $type): void
    {
        Log::spy();
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $target = $this->makeTarget($type);
        $target::saving(function (): never {
            throw new QueryException('mysql', 'update `x` set `canceled_at` = ?', [1], new RuntimeException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction'));
        });
        $searchUrl = route($type->searchRoute(), ['shipped' => '0']);

        $this->from($searchUrl)->cancel($type, $target->id)
            ->assertRedirect($searchUrl)
            ->assertSessionHasErrors(['cancel' => '取り消せませんでした。時間をおいて、もう一度お試しください。']);

        $this->assertNull($this->reload($target)->canceled_at);
        Log::shouldHaveReceived('error')->once();
    }

    /**
     * 同時実行以外のデータベースのエラー（制約違反など）は、隠さずそのまま投げる
     */
    #[Test]
    public function 同時実行以外のデータベースのエラーはそのまま投げる(): void
    {
        Log::spy();
        $this->loginAsAdmin();
        $this->expectNoStripeCall();
        $target = $this->makeTarget(AdminListType::Reservation);
        Reservation::saving(function (): never {
            throw new QueryException('mysql', 'update `reservations` set `canceled_at` = ?', [1], new RuntimeException('Integrity constraint violation: 1451'));
        });

        try {
            $this->withoutExceptionHandling()->cancel(AdminListType::Reservation, $target->id);
            $this->fail('例外が出るはず');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Integrity constraint violation', $e->getMessage());
        }

        Log::shouldNotHaveReceived('error');
        $this->assertNull($this->reload($target)->canceled_at);
    }

    /**
     * canceled_at は一括代入できない（shipped と同じ扱い）
     */
    #[Test]
    public function 取消日時は一括代入できない(): void
    {
        foreach ([new Purchase_history, new Reservation] as $model) {
            $this->assertFalse($model->isFillable('canceled_at'), $model::class);
            $this->assertFalse($model->isFillable('shipped'), $model::class);
        }
    }

    /**
     * 取消日時は日時として扱われる
     */
    #[Test]
    public function 取消日時は日時にキャストされる(): void
    {
        $order = $this->makeOrder(overrides: ['canceled_at' => '2026-11-05 08:00:00']);
        $reservation = $this->makeAdminReservation(overrides: ['canceled_at' => '2026-11-05 08:00:00']);

        $this->assertSame('2026/11/05', $this->reload($order)->canceled_at?->format('Y/m/d'));
        $this->assertSame('2026/11/05', $this->reload($reservation)->canceled_at?->format('Y/m/d'));
        $this->assertNull($this->reload($this->makeOrder())->canceled_at);
    }

    /**
     * 取消の Service は Eloquent のモデルに依存しない（支払方法と PaymentIntent の ID だけをリポジトリから受け取る）
     */
    #[Test]
    public function 取消のserviceはモデルを使わない(): void
    {
        $source = (string) file_get_contents(app_path('Services/AdminOrderCancelService.php'));

        $this->assertStringNotContainsString('App\\Models', $source);
        $this->assertStringNotContainsString('instanceof', $source);
    }
}
