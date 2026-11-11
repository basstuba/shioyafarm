<?php

namespace Tests\Feature;

use App\Livewire\AdminNoticeDetail;
use App\Models\Notice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Constraints\SeeInOrder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面のお知らせ一覧の詳細モーダル（Livewire の AdminNoticeDetail）のテスト
 */
class AdminNoticeDetailTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * お知らせの詳細を開く（一覧の「詳細」ボタンと同じ、open-notice-detail のイベント）
     */
    private function openFor(Notice $notice): Testable
    {
        return Livewire::test(AdminNoticeDetail::class)->dispatch('open-notice-detail', id: $notice->id);
    }

    /**
     * 開く前は、モーダルを出さない
     */
    #[Test]
    public function 開く前はモーダルを表示しない(): void
    {
        $this->loginAsAdmin();

        Livewire::test(AdminNoticeDetail::class)
            ->assertSet('showDetail', false)
            ->assertDontSee('本文');
    }

    /**
     * 作成日・タイトル・本文の3項目だけが、決められた順に出る。本文の改行は CSS で保ち、長いときはスクロールする
     */
    #[Test]
    public function 詳細は三項目が決められた順に表示される(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create(['title' => '収穫祭のお知らせ', 'text' => "一行目\n二行目", 'created_at' => '2026-11-08 10:00:00', 'updated_at' => '2026-11-09 10:00:00']);

        $component = $this->openFor($notice)
            ->assertSet('showDetail', true)
            ->assertSet('detailId', $notice->id);

        $html = $component->html();
        $this->assertThat(['作成日', '2026/11/08', 'タイトル', '収穫祭のお知らせ', '本文', "一行目\n二行目", '閉じる'], new SeeInOrder($html));
        $this->assertSame(3, substr_count($html, '<dt '));
        $this->assertStringNotContainsString('2026/11/09', $html);
        $this->assertStringContainsString('whitespace-pre-wrap', $html);
        $this->assertStringContainsString('overflow-y-auto', $html);
        $this->assertStringNotContainsString('<br', $html);
        // 閉じる・背景・Esc
        $this->assertStringContainsString('$wire.showDetail = false', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window', $html);
    }

    /**
     * HTML の記号は文字のまま表示される
     */
    #[Test]
    public function html記号はエスケープされる(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create(['title' => '<b>太字</b>', 'text' => '<script>alert(1)</script>']);

        $html = $this->openFor($notice)->html();

        $this->assertStringNotContainsString('<b>太字</b>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;b&gt;太字&lt;/b&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    /**
     * 存在しないお知らせは何も開かない
     */
    #[Test]
    public function 存在しないお知らせは何も開かない(): void
    {
        $this->loginAsAdmin();

        Livewire::test(AdminNoticeDetail::class)
            ->dispatch('open-notice-detail', id: 999999)
            ->assertSet('showDetail', false)
            ->assertSet('detailId', null)
            ->assertDontSee('本文');
    }

    /**
     * 別のお知らせを開くと、前の内容は残らない
     */
    #[Test]
    public function 別のお知らせを開くと前の内容は残らない(): void
    {
        $this->loginAsAdmin();
        $first = Notice::factory()->create(['text' => '前の内容']);
        $second = Notice::factory()->create(['text' => '次の内容']);

        $this->openFor($first)
            ->dispatch('open-notice-detail', id: $second->id)
            ->assertSee('次の内容')
            ->assertDontSee('前の内容');
    }

    /**
     * 閉じると詳細は表示されなくなる
     */
    #[Test]
    public function 閉じると表示されなくなる(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create(['text' => '内容です']);

        $this->openFor($notice)
            ->assertSee('内容です')
            ->set('showDetail', false)
            ->assertDontSee('内容です');
    }

    /**
     * 管理者としてログインしていないと、開けない（403）
     */
    #[Test]
    public function 管理者でないと開けない(): void
    {
        $notice = Notice::factory()->create(['text' => '内容です']);

        $this->loginAsMember();
        Livewire::test(AdminNoticeDetail::class)
            ->dispatch('open-notice-detail', id: $notice->id)
            ->assertForbidden();

        Livewire::test(AdminNoticeDetail::class)->assertDontSee('内容です');
    }

    /**
     * 開いているお知らせのIDは、ブラウザから書き換えられない
     */
    #[Test]
    public function idは書き換えられない(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create();
        $other = Notice::factory()->create();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->openFor($notice)->set('detailId', $other->id);
    }
}
