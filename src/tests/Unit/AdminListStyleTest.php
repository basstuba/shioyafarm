<?php

namespace Tests\Unit;

use App\Support\AdminListStyle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 管理画面の一覧の表で共通のクラスのテスト
 */
class AdminListStyleTest extends TestCase
{
    /**
     * 「修正」「削除」のクラスの末尾に、フォーカス枠のクラスが付く
     */
    #[Test]
    public function 修正削除のクラスはフォーカス枠で終わる(): void
    {
        $this->assertStringEndsWith(' '.AdminListStyle::FOCUS, AdminListStyle::ACTION);
    }

    /**
     * SPは枠線つきのボタン、PC（lg以上）は下線つきの文字になる
     */
    #[Test]
    public function 修正削除は_s_pがボタンで_p_cが下線の文字(): void
    {
        $classes = explode(' ', AdminListStyle::ACTION);

        $this->assertContains('h-11', $classes);
        $this->assertContains('border', $classes);
        $this->assertContains('lg:border-0', $classes);
        $this->assertContains('lg:underline', $classes);
    }

    /**
     * セルのクラスはPC（lg以上）のときだけ表のセルにする
     */
    #[Test]
    public function セルのクラスは_p_cだけ表のセルにする(): void
    {
        $this->assertStringContainsString('lg:table-cell', AdminListStyle::CELL);
        $this->assertStringNotContainsString(' table-cell', ' '.AdminListStyle::CELL);
    }
}
