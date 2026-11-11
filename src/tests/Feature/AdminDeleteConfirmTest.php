<?php

namespace Tests\Feature;

use App\View\Components\AdminDeleteConfirm;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 管理画面の削除の確認モーダル（<x-admin-delete-confirm>）のテスト
 */
class AdminDeleteConfirmTest extends TestCase
{
    /**
     * 見出しの x-text の式は、前の文があれば前後を文字列で囲んで名前を挟み、なければ名前の後ろだけにつなぐ
     */
    #[Test]
    public function 見出しの式は前後の文と名前をつなぐ(): void
    {
        $both = new AdminDeleteConfirm('/delete', 'x', '注記', '「', '」を削除します。');
        $afterOnly = new AdminDeleteConfirm('/delete', 'x', '注記', titleAfter: ' を削除します。');

        $this->assertSame("'「' + deleteLabel + '」を削除します。'", (string) $both->titleExpression());
        $this->assertSame("deleteLabel + ' を削除します。'", (string) $afterOnly->titleExpression());
    }

    /**
     * 確認のモーダルは、送信先・id・注記・見出しの式を出し、DELETE のフォームで id を Alpine の値から送る
     */
    #[Test]
    public function モーダルは送信先とidと注記を出す(): void
    {
        $html = Blade::render('<x-admin-delete-confirm action="/admin/x/delete" id-prefix="admin-x-delete" note="注記の文" title-before="「" title-after="」を削除します。" />');

        $this->assertStringContainsString('role="alertdialog"', $html);
        $this->assertStringContainsString('aria-labelledby="admin-x-delete-title"', $html);
        $this->assertStringContainsString('aria-describedby="admin-x-delete-note"', $html);
        $this->assertStringContainsString('action="/admin/x/delete"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('x-bind:value="deleteId"', $html);
        $this->assertStringContainsString("x-text=\"'「' + deleteLabel + '」を削除します。'\"", $html);
        $this->assertStringContainsString('>注記の文</p>', $html);
    }
}
