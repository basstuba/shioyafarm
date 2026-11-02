<?php

namespace Tests\Unit;

use App\Support\OldInput;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * OldInput（入力欄に戻す old() の値の整形）のテスト
 */
class OldInputTest extends TestCase
{
    /**
     * 直前の入力（フラッシュされた old）を設定する
     *
     * @param  array<string, mixed>  $input
     */
    private function flashOld(array $input): void
    {
        request()->setLaravelSession(app('session.store'));
        session()->put('_old_input', $input);
    }

    /**
     * 文字列はそのまま、数値と真偽値は文字列にして返す
     */
    #[Test]
    public function スカラーは文字列にして返す(): void
    {
        $this->flashOld(['s' => 'あ<b>', 'i' => 12, 'z' => 0, 'f' => 1.5, 't' => true, 'n' => false]);

        $this->assertSame('あ<b>', OldInput::scalar('s'));
        $this->assertSame('12', OldInput::scalar('i'));
        $this->assertSame('0', OldInput::scalar('z'));
        $this->assertSame('1.5', OldInput::scalar('f'));
        $this->assertSame('1', OldInput::scalar('t'));
        $this->assertSame('', OldInput::scalar('n'));
    }

    /**
     * 配列など文字列にできない値（改変した送信）は空にする
     */
    #[Test]
    public function 配列は空文字にする(): void
    {
        $this->flashOld(['a' => ['x', 'y'], 'nested' => ['k' => ['v']], 'nu' => null]);

        $this->assertSame('', OldInput::scalar('a'));
        $this->assertSame('', OldInput::scalar('nested'));
        $this->assertSame('', OldInput::scalar('nu'));
        // 配列が入っているときは、既定値があっても既定値は使わない
        $this->assertSame('', OldInput::scalar('a', 'dflt'));
    }

    /**
     * old がないときは既定値（なければ空文字）を返し、既定値が配列などでも空にする
     */
    #[Test]
    public function oldがないときは既定値を返す(): void
    {
        $this->flashOld(['other' => 'x']);

        $this->assertSame('', OldInput::scalar('none'));
        $this->assertSame('dflt', OldInput::scalar('none', 'dflt'));
        $this->assertSame('42', OldInput::scalar('none', 42));
        $this->assertSame('', OldInput::scalar('none', null));
        $this->assertSame('', OldInput::scalar('none', ['x']));
    }

    /**
     * old に空文字があるときは、既定値ではなく空文字を返す（入力を消して送った場合）
     */
    #[Test]
    public function oldが空文字なら既定値は使わない(): void
    {
        $this->flashOld(['em' => '']);

        $this->assertSame('', OldInput::scalar('em', 'dflt'));
    }

    /**
     * 指数表記になる float は、Blade の e() で出したときと同じ文字列になる（既定値でも old でも）
     */
    #[Test]
    public function 指数表記のfloatはbladeのeと同じ文字列になる(): void
    {
        $this->flashOld(['big' => 1.0E+25, 'small' => 1.5E-7]);

        $this->assertSame(e(1.0E+25), OldInput::scalar('big'));
        $this->assertSame(e(1.5E-7), OldInput::scalar('small'));
        $this->assertSame(e(1.0E+25), OldInput::scalar('none', 1.0E+25));
        $this->assertSame('1.0E+25', OldInput::scalar('none', 1.0E+25));
    }
}
