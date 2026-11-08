<?php

namespace Tests\Feature;

use App\Livewire\AdminInquiryDetail;
use App\Models\Inquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Constraints\SeeInOrder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面のお問い合わせ一覧の詳細モーダル（Livewire の AdminInquiryDetail）のテスト
 */
class AdminInquiryDetailTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * お問い合わせの詳細を開く（一覧の「詳細」ボタンと同じ、open-inquiry-detail のイベント）
     */
    private function openFor(Inquiry $inquiry): Testable
    {
        return Livewire::test(AdminInquiryDetail::class)->dispatch('open-inquiry-detail', id: $inquiry->id);
    }

    /**
     * 開く前は、モーダルを出さない
     */
    #[Test]
    public function 開く前はモーダルを表示しない(): void
    {
        $this->loginAsAdmin();

        Livewire::test(AdminInquiryDetail::class)
            ->assertSet('showDetail', false)
            ->assertDontSee('メールアドレス');
    }

    /**
     * 問い合わせ日・氏名・メールアドレス・問い合わせ内容の4項目だけが、決められた順に出る（姓・名は1項目にまとめる）
     */
    #[Test]
    public function 詳細は四項目が決められた順に表示される(): void
    {
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->create([
            'last_name' => '山田', 'first_name' => '太郎', 'email' => 'yamada@example.com',
            'detail' => "一行目\n二行目", 'created_at' => '2026-11-08 10:00:00',
        ]);

        $component = $this->openFor($inquiry)
            ->assertSet('showDetail', true)
            ->assertSet('detailId', $inquiry->id);

        $html = $component->html();
        $this->assertThat(['問い合わせ日', '2026/11/08', '氏名', '山田 太郎', 'メールアドレス', 'yamada@example.com', '問い合わせ内容', "一行目\n二行目", '閉じる'], new SeeInOrder($html));
        $this->assertSame(4, substr_count($html, '<dt '));
        $this->assertStringNotContainsString('>姓<', $html);
        $this->assertStringNotContainsString('>名<', $html);
        // 改行はそのまま表示する（nl2br ではなく CSS）。長い内容は枠の中をスクロールする
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
        $inquiry = Inquiry::factory()->create([
            'last_name' => '<b>x</b>', 'first_name' => '名', 'email' => '<i>@e.com', 'detail' => '<script>alert(1)</script>',
        ]);

        $html = $this->openFor($inquiry)->html();

        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt; 名', $html);
        $this->assertStringContainsString('&lt;i&gt;@e.com', $html);
    }

    /**
     * 存在しないお問い合わせは何も開かない
     */
    #[Test]
    public function 存在しないお問い合わせは何も開かない(): void
    {
        $this->loginAsAdmin();

        Livewire::test(AdminInquiryDetail::class)
            ->dispatch('open-inquiry-detail', id: 999999)
            ->assertSet('showDetail', false)
            ->assertSet('detailId', null)
            ->assertDontSee('メールアドレス');
    }

    /**
     * 別のお問い合わせを開くと、前の内容は残らない
     */
    #[Test]
    public function 別のお問い合わせを開くと前の内容は残らない(): void
    {
        $this->loginAsAdmin();
        $first = Inquiry::factory()->create(['last_name' => '前の', 'detail' => '前の内容']);
        $second = Inquiry::factory()->create(['last_name' => '次の', 'detail' => '次の内容']);

        $this->openFor($first)
            ->dispatch('open-inquiry-detail', id: $second->id)
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
        $inquiry = Inquiry::factory()->create(['detail' => '内容です']);

        $this->openFor($inquiry)
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
        $inquiry = Inquiry::factory()->create(['detail' => '内容です']);

        $this->loginAsMember();
        Livewire::test(AdminInquiryDetail::class)
            ->dispatch('open-inquiry-detail', id: $inquiry->id)
            ->assertForbidden();

        Livewire::test(AdminInquiryDetail::class)->assertDontSee('内容です');
    }

    /**
     * 開いているお問い合わせのIDは、ブラウザから書き換えられない
     */
    #[Test]
    public function idは書き換えられない(): void
    {
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->create();
        $other = Inquiry::factory()->create();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->openFor($inquiry)->set('detailId', $other->id);
    }
}
