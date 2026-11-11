<?php

namespace Tests\Feature;

use App\Models\Notice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面のお知らせの物理削除（DELETE admin.notices.delete）と、削除したお知らせが公開サイトのトップから消えることのテスト
 */
class AdminNoticeDeleteTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 管理者として、お知らせの削除を送る
     */
    private function deleteNotice(mixed $id): TestResponse
    {
        return $this->from(route('admin.notices.index'))->delete(route('admin.notices.delete'), ['id' => $id]);
    }

    /**
     * 削除すると物理削除になり、一覧に戻って緑の案内が出る。ほかのお知らせは残る。トップからも消える
     */
    #[Test]
    public function お知らせが物理削除され一覧に戻って案内が出てトップからも消える(): void
    {
        $this->loginAsAdmin();
        $target = Notice::factory()->create(['title' => '削除するお知らせ']);
        $other = Notice::factory()->create(['title' => '残るお知らせ']);
        $this->get(route('top.index'))->assertSee('削除するお知らせ');

        $this->deleteNotice($target->id)
            ->assertRedirect(route('admin.notices.index'))
            ->assertSessionHas('list_status', 'お知らせを削除しました。')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('notices', ['id' => $target->id]);
        $this->assertDatabaseHas('notices', ['id' => $other->id]);

        $this->get(route('admin.notices.index'))
            ->assertSee('お知らせを削除しました。')
            ->assertSee('text-accent-hover', false)
            ->assertSee('残るお知らせ')
            ->assertDontSee('削除するお知らせ');
        $this->get(route('top.index'))->assertSee('残るお知らせ')->assertDontSee('削除するお知らせ');
    }

    /**
     * 削除済み・存在しないお知らせの削除は何も変えず、一覧に戻って赤字の案内が出る（二度目の削除も同じ）
     */
    #[Test]
    public function 存在しないお知らせは何も変えずに見つからない案内になる(): void
    {
        $this->loginAsAdmin();
        $other = Notice::factory()->create(['title' => '残るお知らせ']);
        $gone = Notice::factory()->create();
        $this->deleteNotice($gone->id)->assertSessionHas('list_status');

        foreach ([$gone->id, 999999] as $id) {
            $this->deleteNotice($id)
                ->assertRedirect(route('admin.notices.index'))
                ->assertSessionMissing('list_status')
                ->assertSessionHasErrors(['delete' => 'お知らせが見つかりません。']);
        }

        $this->assertDatabaseCount('notices', 1);
        $this->assertSame('残るお知らせ', $other->fresh()->title);
        $this->get(route('admin.notices.index'))
            ->assertSee('お知らせが見つかりません。')
            ->assertSee('text-danger', false);
    }

    /**
     * IDが空・数字でない・0以下のときも、何も変えずに一覧に戻って同じ案内になる
     */
    #[Test]
    public function idの形が不正なときも見つからない案内になる(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create();

        foreach ([null, '', 'abc', '1.5', '0', '-1', ['1'], '99999999999999999999'] as $id) {
            $this->deleteNotice($id)
                ->assertRedirect(route('admin.notices.index'))
                ->assertSessionHasErrors(['id' => 'お知らせが見つかりません。']);
        }
        $this->from(route('admin.notices.index'))->delete(route('admin.notices.delete'))
            ->assertRedirect(route('admin.notices.index'))
            ->assertSessionHasErrors(['id' => 'お知らせが見つかりません。']);

        $this->assertDatabaseHas('notices', ['id' => $notice->id]);
        $this->get(route('admin.notices.index'))->assertSee('お知らせが見つかりません。');
    }

    /**
     * 削除は DELETE だけで受ける（GET・POST では削除されない。定義のないメソッドは 404 の fallback が受ける）
     */
    #[Test]
    public function 削除はdeleteだけで受ける(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create();

        $this->post(route('admin.notices.delete'), ['id' => $notice->id])->assertNotFound();
        $this->get(route('admin.notices.delete'))->assertNotFound();

        $this->assertDatabaseHas('notices', ['id' => $notice->id]);
    }

    /**
     * 1回の削除で消えるのは指定したお知らせ1件だけ
     */
    #[Test]
    public function 指定した1件だけが消える(): void
    {
        $this->loginAsAdmin();
        $notices = Notice::factory()->count(3)->create();

        $this->deleteNotice($notices[1]->id)->assertSessionHas('list_status');

        $this->assertSame([$notices[0]->id, $notices[2]->id], Notice::query()->orderBy('id')->pluck('id')->all());
    }

    /**
     * 削除のあとは、削除の前に見ていた検索条件・ページの一覧へ戻る（成功・見つからない・入力の誤りとも）
     */
    #[Test]
    public function 削除のあとは削除の前に見ていた一覧へ戻る(): void
    {
        $this->loginAsAdmin();
        $target = Notice::factory()->create();
        $searchUrl = route('admin.notices.search', ['date' => '2026-10-01', 'page' => 2]);
        $pageUrl = route('admin.notices.index', ['page' => 2]);

        // 成功：ページ番号つきの検索結果
        $this->from($searchUrl)->delete(route('admin.notices.delete'), ['id' => $target->id])
            ->assertRedirect($searchUrl)
            ->assertSessionHas('list_status', 'お知らせを削除しました。');
        // 見つからない：ページ番号つきの一覧
        $this->from($pageUrl)->delete(route('admin.notices.delete'), ['id' => $target->id])
            ->assertRedirect($pageUrl)
            ->assertSessionHasErrors(['delete' => 'お知らせが見つかりません。']);
        // 入力の誤り
        $this->from($searchUrl)->delete(route('admin.notices.delete'), ['id' => 'abc'])
            ->assertRedirect($searchUrl)
            ->assertSessionHasErrors(['id' => 'お知らせが見つかりません。']);
    }

    /**
     * 戻り先がお知らせ一覧・検索結果以外のとき・前のページがないときは、お知らせ一覧（全件）に戻る
     */
    #[Test]
    public function 戻り先がお知らせ一覧以外のときはお知らせ一覧に戻る(): void
    {
        $this->loginAsAdmin();
        $index = route('admin.notices.index');

        foreach ([route('admin.users.index'), 'https://evil.example.com/admin/notices', route('admin.notices.create')] as $referer) {
            $target = Notice::factory()->create();
            $this->from($referer)->delete(route('admin.notices.delete'), ['id' => $target->id])->assertRedirect($index);
        }
        $target = Notice::factory()->create();
        $this->delete(route('admin.notices.delete'), ['id' => $target->id])->assertRedirect($index);
    }

    /**
     * 最後の1件を消してページがなくなったときは、戻り先の一覧が最終ページを表示する
     */
    #[Test]
    public function 最後の1件を消してページがなくなると最終ページが表示される(): void
    {
        $this->loginAsAdmin();
        // 2ページ目の1件（いちばん古い）だけを残す
        $notices = [];
        foreach (range(0, 10) as $i) {
            $notices[] = Notice::factory()->create(['created_at' => now()->subHour()->addMinutes($i)]);
        }
        $pageUrl = route('admin.notices.index', ['page' => 2]);

        $this->from($pageUrl)->followingRedirects()->delete(route('admin.notices.delete'), ['id' => $notices[0]->id])
            ->assertOk()
            ->assertSee('お知らせを削除しました。')
            ->assertViewHas('notices', fn ($paginator): bool => $paginator->currentPage() === 1 && $paginator->total() === 10 && count($paginator->items()) === 10);
    }
}
