<?php

namespace Tests\Unit;

use App\Enums\PaymentMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * PaymentMethod（支払方法）の、取消の確認に出す返金の案内のテスト
 */
class PaymentMethodTest extends TestCase
{
    /**
     * カードは、返金をStripeの管理画面で行う案内を出す
     */
    #[Test]
    public function カードは返金の案内を出す(): void
    {
        $this->assertSame('返金はStripeの管理画面で行ってください。', PaymentMethod::Card->refundNote());
    }

    /**
     * 銀行振込は、入金済みの場合の案内を出す
     */
    #[Test]
    public function 銀行振込は入金済みの場合の案内を出す(): void
    {
        $this->assertSame('入金済みの場合、返金はStripeの管理画面で行ってください。', PaymentMethod::CustomerBalance->refundNote());
    }

    /**
     * 代金引換は、Stripeを通さないため案内しない
     */
    #[Test]
    public function 代金引換は案内しない(): void
    {
        $this->assertNull(PaymentMethod::Cod->refundNote());
    }
}
