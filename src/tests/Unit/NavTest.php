<?php

namespace Tests\Unit;

use App\Support\Nav;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ヘッダーナビ（Nav）の、SPでヘッダーに出す項目とメニューに入れる項目の振り分けのテスト
 *
 * 各ルートでの表示は HeaderNavTest で確認する。ここでは、Nav::shopCart()（shop.cart / shop.checkout.*）と、
 * 振り分け処理（Nav::split）を確認する。
 */
class NavTest extends TestCase
{
    /**
     * カート・購入手続きのナビは、マイページだけをSPのヘッダーに出し、残りをメニューに入れる
     */
    #[Test]
    public function カート用ナビはマイページだけがヘッダーに残りがメニューに入る(): void
    {
        [$bar, $menu] = Nav::split(Nav::shopCart());

        $this->assertSame(['マイページ'], array_column($bar, 'label'));
        $this->assertSame(['お知らせ', 'しおや農園について', 'ログアウト'], array_column($menu, 'label'));
    }

    /**
     * カート用ナビのログアウトはメニュー側にあり、POSTで送る
     */
    #[Test]
    public function カート用ナビのログアウトはメニュー側でポストで送る(): void
    {
        $logout = collect(Nav::shopCart())->firstWhere('label', 'ログアウト');

        $this->assertNotNull($logout);
        $this->assertFalse(Nav::isSpBar($logout));
        $this->assertSame('post', $logout['method']);
    }

    /**
     * 振り分けは、印（'sp' => 'bar'）のある項目をヘッダー用、それ以外をメニュー用にし、元の並び順を保つ
     */
    #[Test]
    public function 振り分けは印のある項目をヘッダー用それ以外をメニュー用にして並び順を保つ(): void
    {
        $items = [
            ['label' => 'A'],
            ['label' => 'B', 'sp' => 'bar'],
            ['label' => 'C', 'sp' => 'other'],
            ['label' => 'D', 'sp' => 'bar'],
        ];

        [$bar, $menu] = Nav::split($items);

        $this->assertSame(['B', 'D'], array_column($bar, 'label'));
        $this->assertSame(['A', 'C'], array_column($menu, 'label'));
        $this->assertTrue(Nav::isSpBar($items[1]));
        $this->assertFalse(Nav::isSpBar($items[0]));
    }

    /**
     * 項目が空のときは、ヘッダー用もメニュー用も空になる
     */
    #[Test]
    public function 項目が空のときはどちらも空になる(): void
    {
        $this->assertSame([[], []], Nav::split([]));
    }

    /**
     * マイページ用ナビは「マイページ」を出さず、SPのヘッダーにはオンラインショップだけを出す
     */
    #[Test]
    public function マイページ用ナビはマイページを出さずオンラインショップだけがヘッダーに残る(): void
    {
        [$bar, $menu] = Nav::split(Nav::mypage());

        $this->assertSame(['オンラインショップ'], array_column($bar, 'label'));
        $this->assertSame(['お知らせ', 'しおや農園について', 'ログアウト'], array_column($menu, 'label'));
        $this->assertNotContains('マイページ', array_column(Nav::mypage(), 'label'));
    }

    /**
     * マイページ用ナビのログアウトはメニュー側にあり、POSTで送る。オンラインショップは商品一覧へのリンク
     */
    #[Test]
    public function マイページ用ナビのログアウトはポストで送りオンラインショップは商品一覧へ移動する(): void
    {
        $items = collect(Nav::mypage());

        $this->assertSame('post', $items->firstWhere('label', 'ログアウト')['method'] ?? null);
        $this->assertSame(route('shop.items.index'), $items->firstWhere('label', 'オンラインショップ')['url'] ?? null);
    }

    /**
     * ログイン中のナビの「マイページ」は、マイページへのリンクで、SPのヘッダーにも出す
     */
    #[Test]
    public function ログイン中のナビのマイページはマイページへのリンクになる(): void
    {
        $item = collect(Nav::topAuth())->firstWhere('label', 'マイページ');

        $this->assertNotNull($item);
        $this->assertSame(route('shop.mypage.index'), $item['url']);
        $this->assertTrue(Nav::isSpBar($item));
    }
}
