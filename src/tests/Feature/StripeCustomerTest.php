<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Payment\BankTransferInstructions;
use App\Services\Payment\BankTransferIntentData;
use App\Services\Payment\CheckoutSessionData;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * 会員ごとの Stripe Customer の使い回し（users.stripe_customer_id）のテスト
 */
class StripeCustomerTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 銀行振込の PaymentIntent を返す
     */
    private function bankIntent(): BankTransferIntentData
    {
        return new BankTransferIntentData('pi_new', 'requires_action', 3723, new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 3723));
    }

    /**
     * 初回の決済：Customer を作って会員に保存し、その Customer で決済する（カードの Checkout でも customer に使う）
     */
    #[Test]
    public function 初回はcustomerを作成して保存しカードの決済にも使う(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->assertNull($user->stripe_customer_id);

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('ensureCustomer')->once()->with($user->email, null)->andReturn('cus_first');
            $mock->shouldReceive('createCheckoutSession')->once()
                ->withArgs(fn ($method, string $customerId) => $customerId === 'cus_first')
                ->andReturn(new CheckoutSessionData('cs_test_1', 'https://checkout.stripe.test/1', 'open', 'unpaid'));
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1]))
            ->assertRedirect('https://checkout.stripe.test/1');

        $this->assertSame('cus_first', $user->fresh()->stripe_customer_id);
    }

    /**
     * 2回目以降は、保存済みの Customer ID を使って ensureCustomer を呼び（再利用）、銀行振込でも同じ Customer で PaymentIntent を作る
     */
    #[Test]
    public function 二回目以降は保存済みのcustomerを再利用する(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $user->forceFill(['stripe_customer_id' => 'cus_saved'])->save();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('ensureCustomer')->once()->with($user->email, 'cus_saved')->andReturn('cus_saved');
            $mock->shouldReceive('createBankTransferIntent')->once()->with('cus_saved', 3723)->andReturn($this->bankIntent());
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['payment_method' => 'customer_balance']))
            ->assertRedirect(route('shop.checkout.bank-transfer'));

        $this->assertSame('cus_saved', $user->fresh()->stripe_customer_id);
    }

    /**
     * Stripe 側で削除されていて作り直した（別の ID が返ってきた）ときは、新しい ID を保存し直す
     */
    #[Test]
    public function 作り直したcustomerの_i_dを保存し直す(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $user->forceFill(['stripe_customer_id' => 'cus_deleted'])->save();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('ensureCustomer')->once()->with($user->email, 'cus_deleted')->andReturn('cus_recreated');
            $mock->shouldReceive('createBankTransferIntent')->once()->with('cus_recreated', 3723)->andReturn($this->bankIntent());
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['payment_method' => 'customer_balance']))
            ->assertRedirect(route('shop.checkout.bank-transfer'));

        $this->assertSame('cus_recreated', $user->fresh()->stripe_customer_id);
    }

    /**
     * Customer を用意できなかったときは、購入ページへ戻してエラーを表示し、保存済みの Customer ID は変えない
     */
    #[Test]
    public function customerを用意できなければエラーになる(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $user->forceFill(['stripe_customer_id' => 'cus_saved'])->save();

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('ensureCustomer')->once()->andThrow(new PaymentGatewayException('通信エラー'));
            $mock->shouldNotReceive('createCheckoutSession');
            $mock->shouldNotReceive('createBankTransferIntent');
        });

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1]))
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors('payment');

        $this->assertSame('cus_saved', $user->fresh()->stripe_customer_id);
    }

    /**
     * 代金引換では Stripe Customer を作らない
     */
    #[Test]
    public function 代金引換ではcustomerを作らない(): void
    {
        [$user, $items] = $this->makeCartScenario();
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('ensureCustomer');
        });

        $this->actingAs($user)
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1], ['payment_method' => 'cod']))
            ->assertRedirect(route('shop.checkout.thanks'));

        $this->assertNull($user->fresh()->stripe_customer_id);
    }

    /**
     * stripe_customer_id は fillable に含めない：一括代入（会員情報の更新・作成）では書き換えられない
     */
    #[Test]
    public function stripe_customer_idは一括代入で書き換えられない(): void
    {
        $this->assertNotContains('stripe_customer_id', (new User)->getFillable());

        $user = User::factory()->create();
        $user->update(['first_name' => '更新', 'stripe_customer_id' => 'cus_attacker']);
        $this->assertNull($user->fresh()->stripe_customer_id);
        $this->assertSame('更新', $user->fresh()->first_name);

        $created = User::create([
            'last_name' => '塩谷', 'first_name' => '太郎', 'prefecture' => '北海道', 'city' => '札幌市',
            'tell' => '09012345678', 'email' => 'mass@example.com', 'password' => 'password123',
            'stripe_customer_id' => 'cus_attacker',
        ]);
        $this->assertNull($created->fresh()->stripe_customer_id);
    }

    /**
     * 会員情報の配列・JSON に stripe_customer_id を含めない（hidden）
     */
    #[Test]
    public function stripe_customer_idは配列に出さない(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['stripe_customer_id' => 'cus_saved'])->save();

        $this->assertArrayNotHasKey('stripe_customer_id', $user->fresh()->toArray());
        $this->assertStringNotContainsString('cus_saved', $user->fresh()->toJson());
    }

    /**
     * stripe_customer_id は unique：別の会員に同じ Customer ID は保存できない
     */
    #[Test]
    public function stripe_customer_idは重複できない(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $a->forceFill(['stripe_customer_id' => 'cus_same'])->save();

        $this->expectException(UniqueConstraintViolationException::class);
        $b->forceFill(['stripe_customer_id' => 'cus_same'])->save();
    }
}
