<?php

namespace Tests\Unit;

use App\Support\FormText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * フォームの文字の整え方（前後の空白の除去・全角の数字の半角化）のテスト
 */
class FormTextTest extends TestCase
{
    /**
     * 前後の半角・全角の空白・タブ・改行を取り除き、間の空白は残す
     */
    #[Test]
    #[DataProvider('trims')]
    public function 前後の空白を取り除く(string $input, string $expected): void
    {
        $this->assertSame($expected, FormText::trim($input));
    }

    /**
     * 入力と、整えた結果
     *
     * @return array<string, array{string, string}>
     */
    public static function trims(): array
    {
        return [
            '半角' => ['  箱  ', '箱'],
            '全角' => ["\u{3000}箱\u{3000}", '箱'],
            '混在・タブ・改行' => [" \t\u{3000}\n箱 A\n\u{3000} ", '箱 A'],
            '空白だけ' => [" \u{3000}\t", ''],
            '空文字' => ['', ''],
            '間の空白は残す' => ['化粧箱　5kg 用', '化粧箱　5kg 用'],
        ];
    }

    /**
     * 全角の数字を半角にして、前後の空白を取り除く。数字以外はそのまま
     */
    #[Test]
    public function 全角の数字を半角にする(): void
    {
        $this->assertSame('40', FormText::number('４０'));
        $this->assertSame('1234567890', FormText::number(" \u{3000}１２３４５６７８９０\u{3000}"));
        $this->assertSame('12', FormText::number('1２'));
        $this->assertSame('abc', FormText::number('abc'));
        $this->assertSame('-1', FormText::number('-1'));
    }

    /**
     * 数字だけのときは先頭の0を取り除く（全角も同じ）。0だけの値は「0」にする。数字以外を含む値は変えない
     */
    #[Test]
    public function 先頭の0を取り除く(): void
    {
        $this->assertSame('5', FormText::number('05'));
        $this->assertSame('5', FormText::number('０５'));
        $this->assertSame('0', FormText::number('0'));
        $this->assertSame('0', FormText::number('00'));
        $this->assertSame('0', FormText::number('００'));
        $this->assertSame('100000', FormText::number('0100000'));
        $this->assertSame('10', FormText::number(' 010 '));
        $this->assertSame('-01', FormText::number('-01'));
        $this->assertSame('01.5', FormText::number('01.5'));
        $this->assertSame('0a', FormText::number('0a'));
    }

    /**
     * 文字列でない値（配列・null）は、検証のエラーにするため、そのまま返す
     */
    #[Test]
    public function 文字列でない値はそのまま返す(): void
    {
        $this->assertNull(FormText::trim(null));
        $this->assertSame(['a'], FormText::trim(['a']));
        $this->assertNull(FormText::number(null));
        $this->assertSame(['1'], FormText::number(['1']));
    }

    /**
     * 正しい UTF-8 でない文字列は、そのまま返す（検証のエラーにする）
     */
    #[Test]
    public function 壊れた_ut_f8はそのまま返す(): void
    {
        $this->assertSame("\xff", FormText::trim("\xff"));
    }
}
