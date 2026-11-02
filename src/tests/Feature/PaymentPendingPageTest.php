<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * お支払いの確認ページ（shop.payment_pending）の表示テスト
 */
class PaymentPendingPageTest extends TestCase
{
    /**
     * 見出し・メッセージ・塗りの「再読み込み」ボタン（今のURLへのリンク）を出す（AC-4）
     */
    #[Test]
    public function お支払いの確認ページを表示する(): void
    {
        $this->view('shop.payment_pending', ['message' => 'お支払いの確認に時間がかかっています。'])
            ->assertSee('<title>お支払いの確認 | Shioya Farm</title>', false)
            ->assertSee('お支払いの確認')
            ->assertSee('role="alert">お支払いの確認に時間がかかっています。</p>', false)
            ->assertSee('bg-action', false)
            ->assertSee('h-11', false)
            ->assertSee('>再読み込み</a>', false);
    }
}
