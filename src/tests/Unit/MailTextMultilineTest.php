<?php

namespace Tests\Unit;

use App\Support\MailText;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * MailText::multiline（改行を保ったままの無害化）のテスト
 */
class MailTextMultilineTest extends TestCase
{
    /**
     * 行の間は <br> と改行になり、改行コードはそろう。空行は残る
     */
    #[Test]
    public function 行の間をbrでつなぎ空行を残す(): void
    {
        $this->assertSame("a<br>\n<br>\nb<br>\nc<br>\nd", (string) MailText::multiline("a\n\nb\r\nc\rd"));
    }

    /**
     * 先頭と末尾の空行は取り除く。null・空は空文字
     */
    #[Test]
    public function 先頭と末尾の空行は取り除く(): void
    {
        $this->assertSame("a<br>\nb", (string) MailText::multiline("\n\na\nb\n\n"));
        $this->assertSame('', (string) MailText::multiline(null));
        $this->assertSame('', (string) MailText::multiline(''));
    }

    /**
     * 先頭と末尾の空白だけの行（半角・全角スペース・タブ）も、改行だけの行と同じく取り除く。途中の空行・空白だけの行と、内容のある行の字下げは残す
     */
    #[Test]
    public function 先頭と末尾の空白だけの行も取り除く(): void
    {
        $this->assertSame("a<br>\nb", (string) MailText::multiline("  \n　\n\t\na\nb\n \t　\n\n  "));
        $this->assertSame('', (string) MailText::multiline("  \n　\n\t"));
        // 途中の空白だけの行は残る
        $this->assertSame("a<br>\n&#160;&#160;<br>\nb", (string) MailText::multiline("a\n  \nb"));
        // 内容のある1行目の字下げは残る（半角は &#160;、全角スペースはそのまま）
        $this->assertSame("&#160;&#160;a<br>\n　b", (string) MailText::multiline("\n  a\n　b\n　"));
    }

    /**
     * HTML・Markdown の記号は数値文字参照になる
     */
    #[Test]
    public function 記号は数値文字参照になる(): void
    {
        $this->assertSame('&#60;b&#62;&#35; &#42;a&#42;', (string) MailText::multiline('<b># *a*'));
    }

    /**
     * 行頭の空白・タブは &#160; になって残る（4つ以上でコードブロックにならない）
     */
    #[Test]
    public function 行頭の空白は残る(): void
    {
        $this->assertSame("a<br>\n&#160;&#160;&#160;&#160;&#160;b", (string) MailText::multiline("a\n    \tb"));
    }
}
