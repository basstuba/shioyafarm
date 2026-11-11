<?php

namespace Tests\Feature;

use App\Http\Responses\ExpiredPageResponder;
use App\Models\Notice;
use App\View\Components\AdminNoticeForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面のお知らせの作成・修正（admin.notices.create・store・edit・update）のテスト
 */
class AdminNoticeFormTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 作成を送る
     *
     * @param  array<string, mixed>  $overrides
     */
    private function store(array $overrides = []): TestResponse
    {
        return $this->from(route('admin.notices.create'))->post(route('admin.notices.store'), [
            'title' => '夏野菜の販売を開始しました',
            'text' => "いつもありがとうございます。\n夏野菜を販売します。",
            ...$overrides,
        ]);
    }

    /**
     * 修正を送る
     *
     * @param  array<string, mixed>  $overrides
     */
    private function update(Notice $notice, array $overrides = []): TestResponse
    {
        return $this->from(route('admin.notices.edit', $notice->id))->post(route('admin.notices.update'), [
            'id' => (string) $notice->id,
            'title' => '修正したタイトル',
            'text' => '修正した内容',
            ...$overrides,
        ]);
    }

    /**
     * 作成ページ：タイトル・入力欄・プレースホルダー・ボタン・「一覧へ戻る」が出る。ボタンは橙の地に ink の文字
     */
    #[Test]
    public function 作成ページの各部品が表示される(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.notices.create'));

        $response->assertOk()->assertViewIs('admin.notice_create');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertSee('<title>お知らせ作成 | 管理画面 | Shioya Farm</title>', false);
        $response->assertSeeInOrder(['<h1', 'お知らせ作成', 'お知らせタイトル', 'お知らせ内容', '作成する', '一覧へ戻る'], false);
        $html = $response->getContent();
        $this->assertStringContainsString('<h1 class="text-center text-[28px] font-normal text-primary-text lg:text-[32px]">お知らせ作成</h1>', $html);
        $this->assertStringContainsString('action="'.route('admin.notices.store').'"', $html);
        $this->assertStringContainsString('placeholder="例：お知らせがあります！"', $html);
        $this->assertStringContainsString('placeholder="例：ECサイトで直接お買い求めいただけるようになりました！"', $html);
        // 入力欄の大きさ（PC：タイトル400px・内容584×169、SPは全幅）
        $this->assertMatchesRegularExpression('#<input\s+class="[^"]*h-11 w-full lg:h-10 lg:w-\[400px\][^"]*"\s+id="title"#', $html);
        $this->assertMatchesRegularExpression('#<textarea\s+class="[^"]*h-\[169px\] w-full[^"]*lg:w-\[584px\][^"]*"\s+id="text"#', $html);
        // ボタン：橙の地・ink の文字、ホバー・フォーカスで濃くなり影がつく。幅400px・角丸30
        $this->assertMatchesRegularExpression('#<button\s+class="[^"]*lg:w-\[400px\][^"]*bg-primary text-ink hover:bg-primary-dark focus-visible:bg-primary-dark"#', $html);
        $this->assertStringContainsString('hover:shadow-button focus-visible:shadow-button', $html);
        $this->assertStringNotContainsString('bg-mail-link', $html);
        $this->assertStringContainsString('href="'.route('admin.notices.index').'"', $html);
        // 確認のモーダルは出さない。二重送信の防止
        $this->assertStringNotContainsString('role="alertdialog"', $html);
        $this->assertStringContainsString('x-bind:disabled="sending"', $html);
        $this->assertSame(1, preg_match_all('#<h1[ >]#', $html));
        $this->assertStringNotContainsString('name="id"', $html);
    }

    /**
     * 作成すると一覧に戻って緑の案内が出て、一覧の先頭に出る。公開サイトのトップに作成日つきで出る（改行も保たれる）
     */
    #[Test]
    public function 作成すると一覧の先頭に出てトップにも出る(): void
    {
        $this->loginAsAdmin();
        Notice::factory()->create(['title' => '以前のお知らせ', 'created_at' => now()->subDay()]);

        $this->store()
            ->assertRedirect(route('admin.notices.index'))
            ->assertSessionHas('list_status', 'お知らせを作成しました。')
            ->assertSessionHasNoErrors();

        $notice = Notice::query()->where('title', '夏野菜の販売を開始しました')->firstOrFail();
        $this->assertSame("いつもありがとうございます。\n夏野菜を販売します。", $notice->text);
        $this->assertDatabaseCount('notices', 2);

        $response = $this->get(route('admin.notices.index'))
            ->assertSee('お知らせを作成しました。')
            ->assertSee('text-accent-hover', false);
        $this->assertSame($notice->id, $response->viewData('notices')->items()[0]->id);

        $top = $this->get(route('top.index'));
        $top->assertSeeInOrder(['夏野菜の販売を開始しました', $notice->created_at->format('Y-m-d'), 'いつもありがとうございます。', '以前のお知らせ'], false);
        $top->assertSee('whitespace-pre-line', false);
    }

    /**
     * ブラウザが送る CRLF の改行は LF にそろえて保存する（見た目の文字数で数える）
     */
    #[Test]
    public function 改行はlfにそろえて保存される(): void
    {
        $this->loginAsAdmin();

        $this->store(['text' => "一行目\r\n二行目\r三行目"])->assertSessionHasNoErrors();

        $this->assertSame("一行目\n二行目\n三行目", Notice::query()->firstOrFail()->text);
    }

    /**
     * タイトル255文字・内容2000文字ちょうどは作成できる（改行を含めて2000文字、CRLFで送っても同じ）
     */
    #[Test]
    public function 境界値ちょうどは作成できる(): void
    {
        $this->loginAsAdmin();
        $text = str_repeat('あ', 999)."\n".str_repeat('あ', 1000);

        $this->store(['title' => str_repeat('あ', 255), 'text' => str_repeat('い', 2000)])->assertSessionHasNoErrors();
        $this->store(['title' => 'タイトル', 'text' => str_replace("\n", "\r\n", $text)])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('notices', 2);
        $this->assertSame(255, mb_strlen(Notice::query()->orderBy('id')->firstOrFail()->title));
    }

    /**
     * 空・空白だけ・タイトル256文字・内容2001文字は作成されず、作成ページに戻って項目ごとにエラーが出て、入力が残る
     *
     * @param  array<string, string>  $input
     * @param  array<string, string>  $expectedErrors
     */
    #[Test]
    #[DataProvider('invalidInputs')]
    public function 不正な入力は作成されずエラーが表示される(array $input, array $expectedErrors): void
    {
        $this->loginAsAdmin();

        $this->store($input)
            ->assertRedirect(route('admin.notices.create'))
            ->assertSessionHasErrors($expectedErrors)
            ->assertSessionMissing('list_status');

        $this->assertDatabaseCount('notices', 0);

        $html = $this->get(route('admin.notices.create'))->assertOk()->getContent();
        foreach ($expectedErrors as $message) {
            $this->assertStringContainsString('class="text-base text-danger">'.$message.'</p>', $html);
        }
        $this->assertStringContainsString('border-danger', $html);
    }

    /**
     * @return array<string, array{array<string, string>, array<string, string>}>
     */
    public static function invalidInputs(): array
    {
        $titleBlank = 'お知らせタイトルを入力してください';
        $textBlank = 'お知らせ内容を入力してください';

        return [
            '両方が空' => [['title' => '', 'text' => ''], ['title' => $titleBlank, 'text' => $textBlank]],
            '両方が空白だけ（全角を含む）' => [['title' => " 　\t", 'text' => "  \n　 "], ['title' => $titleBlank, 'text' => $textBlank]],
            'タイトルだけ空' => [['title' => ''], ['title' => $titleBlank]],
            '内容だけ空' => [['text' => ''], ['text' => $textBlank]],
            'タイトル256文字' => [['title' => str_repeat('あ', 256)], ['title' => 'お知らせタイトルは255文字以内で入力してください']],
            '内容2001文字' => [['text' => str_repeat('い', 2001)], ['text' => 'お知らせ内容は2000文字以内で入力してください']],
            '内容が改行（CRLF）を含めて2001文字' => [['text' => str_repeat('あ', 1000)."\r\n".str_repeat('あ', 1000)], ['text' => 'お知らせ内容は2000文字以内で入力してください']],
        ];
    }

    /**
     * 検証エラーのとき、入力した値が入力欄に残る
     */
    #[Test]
    public function エラーのとき入力した値が残る(): void
    {
        $this->loginAsAdmin();

        $this->store(['title' => '残るタイトル', 'text' => str_repeat('い', 2001)]);
        $html = $this->get(route('admin.notices.create'))->assertOk()->getContent();

        $this->assertStringContainsString('value="残るタイトル"', $html);
        $this->assertStringContainsString(str_repeat('い', 2001).'</textarea>', $html);
    }

    /**
     * 文字列でない値（配列など、改変した送信）は検証で弾かれ、500にならない
     */
    #[Test]
    public function 文字列でない値は検証エラーになる(): void
    {
        $this->loginAsAdmin();

        $this->store(['title' => ['a'], 'text' => ['b']])->assertSessionHasErrors(['title', 'text']);
        $this->get(route('admin.notices.create'))->assertOk();
        $this->assertDatabaseCount('notices', 0);
    }

    /**
     * 修正ページ：タイトル「お知らせ修正」（青）と今の値、青の「修正する」ボタン、「一覧へ戻る」、hidden の id。サイドメニューは「お知らせ一覧」
     */
    #[Test]
    public function 修正ページには今の値が入った状態で開く(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create(['title' => '今のタイトル', 'text' => "今の内容\n二行目"]);

        $response = $this->get(route('admin.notices.edit', $notice->id));

        $response->assertOk()->assertViewIs('admin.notice_edit');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $html = $response->getContent();
        $response->assertSee('<title>お知らせ修正 | 管理画面 | Shioya Farm</title>', false);
        $this->assertStringContainsString('<h1 class="text-center text-[28px] font-normal text-mail-link lg:text-[32px]">お知らせ修正</h1>', $html);
        $this->assertStringContainsString('action="'.route('admin.notices.update').'"', $html);
        $this->assertStringContainsString('<input type="hidden" name="id" value="'.$notice->id.'">', $html);
        $this->assertStringContainsString('value="今のタイトル"', $html);
        $this->assertStringContainsString(">今の内容\n二行目</textarea>", $html);
        $this->assertMatchesRegularExpression('#<button\s+class="[^"]*bg-mail-link text-white hover:bg-mail-link-hover focus-visible:bg-mail-link-hover"[^>]*>\s*修正する</button>#u', $html);
        $this->assertStringNotContainsString('bg-primary text-ink', $html);
        $this->assertStringContainsString('hover:shadow-button focus-visible:shadow-button', $html);
        $this->assertStringContainsString('href="'.route('admin.notices.index').'"', $html);
        $this->assertStringNotContainsString('role="alertdialog"', $html);
    }

    /**
     * 修正すると一覧に戻って緑の案内が出て、一覧・詳細・トップに修正した内容が出る。作成日・並び順は変わらない
     */
    #[Test]
    public function 修正すると内容が変わり作成日と並び順は変わらない(): void
    {
        $this->loginAsAdmin();
        $old = Notice::factory()->create(['title' => '古いお知らせ', 'text' => '古い内容', 'created_at' => '2026-10-01 09:00:00', 'updated_at' => '2026-10-01 09:00:00']);
        $new = Notice::factory()->create(['title' => '新しいお知らせ', 'created_at' => '2026-11-01 09:00:00', 'updated_at' => '2026-11-01 09:00:00']);
        $this->travelTo('2026-11-10 12:00:00');

        $this->update($old, ['title' => '直したタイトル', 'text' => "直した内容\n二行目"])
            ->assertRedirect(route('admin.notices.index'))
            ->assertSessionHas('list_status', 'お知らせを修正しました。')
            ->assertSessionHasNoErrors();

        $old = $old->fresh();
        $this->assertSame('直したタイトル', $old->title);
        $this->assertSame("直した内容\n二行目", $old->text);
        $this->assertSame('2026-10-01 09:00:00', $old->created_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('notices', 2);
        $this->assertSame('新しいお知らせ', $new->fresh()->title);

        $list = $this->get(route('admin.notices.index'))->assertSee('お知らせを修正しました。')->assertSee('2026/10/01');
        $this->assertSame([$new->id, $old->id], array_map(fn (Notice $n): int => $n->id, $list->viewData('notices')->items()));

        $this->get(route('top.index'))
            ->assertViewHas('notices', fn ($notices) => $notices->pluck('id')->all() === [$new->id, $old->id])
            ->assertSeeInOrder(['新しいお知らせ', '2026-11-01', '直したタイトル', '2026-10-01'], false)
            ->assertDontSee('2026-11-10');
    }

    /**
     * 修正の入力に誤りがあると、更新されずに修正ページに戻り、作成と同じエラーが出て、入力した値が残る
     */
    #[Test]
    #[DataProvider('invalidInputs')]
    public function 修正の不正な入力は更新されずエラーが表示される(array $input, array $expectedErrors): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create(['title' => '元のタイトル', 'text' => '元の内容']);

        $this->update($notice, $input)
            ->assertRedirect(route('admin.notices.edit', $notice->id))
            ->assertSessionHasErrors($expectedErrors);

        $this->assertSame('元のタイトル', $notice->fresh()->title);
        $this->assertSame('元の内容', $notice->fresh()->text);

        $html = $this->get(route('admin.notices.edit', $notice->id))->assertOk()->getContent();
        foreach ($expectedErrors as $message) {
            $this->assertStringContainsString($message.'</p>', $html);
        }
    }

    /**
     * 修正のエラーのとき、元の値ではなく入力した値が残る
     */
    #[Test]
    public function 修正のエラーのとき入力した値が残る(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create(['title' => '元のタイトル']);

        $this->update($notice, ['title' => '', 'text' => str_repeat('い', 2001)]);
        $html = $this->get(route('admin.notices.edit', $notice->id))->assertOk()->getContent();

        $this->assertStringNotContainsString('value="元のタイトル"', $html);
        $this->assertStringContainsString(str_repeat('い', 2001).'</textarea>', $html);
        $this->assertStringContainsString('<input type="hidden" name="id" value="'.$notice->id.'">', $html);
    }

    /**
     * 修正のタイトル255文字・内容2000文字ちょうどは更新できる
     */
    #[Test]
    public function 修正の境界値ちょうどは更新できる(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create();

        $this->update($notice, ['title' => str_repeat('あ', 255), 'text' => str_repeat('い', 2000)])->assertSessionHasNoErrors();

        $this->assertSame(str_repeat('あ', 255), $notice->fresh()->title);
        $this->assertSame(2000, mb_strlen($notice->fresh()->text));
    }

    /**
     * 存在しない（削除済みの）お知らせの修正ページは、一覧に戻って赤字で「お知らせが見つかりません。」
     */
    #[Test]
    public function 存在しないお知らせの修正ページは一覧に戻り案内が出る(): void
    {
        $this->loginAsAdmin();
        $other = Notice::factory()->create(['title' => '残るお知らせ']);

        $this->get(route('admin.notices.edit', 999999))
            ->assertRedirect(route('admin.notices.index'))
            ->assertSessionHasErrors(['notice' => 'お知らせが見つかりません。']);

        $this->get(route('admin.notices.index'))
            ->assertSee('お知らせが見つかりません。')
            ->assertSee('text-danger', false)
            ->assertSee('残るお知らせ');
        $this->assertSame('残るお知らせ', $other->fresh()->title);
    }

    /**
     * 存在しないお知らせの修正は、何も変えずに一覧に戻って赤字の案内が出る（ページを開いたあとに削除された場合も同じ）
     */
    #[Test]
    public function 存在しないお知らせの修正は何も変えずに案内が出る(): void
    {
        $this->loginAsAdmin();
        $other = Notice::factory()->create(['title' => '残るお知らせ']);
        $target = Notice::factory()->create();
        $targetId = $target->id;
        $target->delete();

        $this->update($target)
            ->assertRedirect(route('admin.notices.index'))
            ->assertSessionMissing('list_status')
            ->assertSessionHasErrors(['notice' => 'お知らせが見つかりません。']);

        $this->assertDatabaseMissing('notices', ['id' => $targetId]);
        $this->assertDatabaseCount('notices', 1);
        $this->assertSame('残るお知らせ', $other->fresh()->title);
        $this->get(route('admin.notices.index'))->assertSee('お知らせが見つかりません。');
    }

    /**
     * IDが空・数字でない・0以下のときも、何も変えずに一覧に戻って同じ案内になる
     */
    #[Test]
    public function idの形が不正な修正も見つからない案内になる(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create(['title' => '元のタイトル']);

        foreach ([null, '', 'abc', '1.5', '0', '-1', ['1'], '99999999999999999999'] as $id) {
            $this->from(route('admin.notices.index'))
                ->post(route('admin.notices.update'), ['id' => $id, 'title' => '変更', 'text' => '内容'])
                ->assertRedirect(route('admin.notices.index'))
                ->assertSessionHasErrors(['notice' => 'お知らせが見つかりません。']);
        }

        $this->assertSame('元のタイトル', $notice->fresh()->title);
    }

    /**
     * タイトル・内容の HTML の記号は、修正ページ・作成のやり直しの入力欄・トップで文字のまま表示される
     */
    #[Test]
    public function html記号はエスケープされる(): void
    {
        $this->loginAsAdmin();
        $dangerous = '<b>太字</b><script>alert(1)</script>';

        $this->store(['title' => $dangerous, 'text' => $dangerous])->assertSessionHasNoErrors();
        $notice = Notice::query()->firstOrFail();
        $this->assertSame($dangerous, $notice->title);

        $escaped = '&lt;b&gt;太字&lt;/b&gt;&lt;script&gt;alert(1)&lt;/script&gt;';
        foreach ([route('admin.notices.edit', $notice->id), route('top.index')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html, $url);
            $this->assertStringContainsString($escaped, $html, $url);
        }

        // 検証エラーで戻したときの入力欄にも、エスケープして残す
        $this->store(['title' => $dangerous, 'text' => '']);
        $html = $this->get(route('admin.notices.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('value="'.$escaped.'"', $html);
    }

    /**
     * 419（ページの有効期限切れ）のとき、作成は作成ページへ、修正は修正ページへ、入力を残して案内つきで戻る（作成・更新はされない）
     */
    #[Test]
    public function トークンが古いときは作成ページと修正ページに入力を残して戻る(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create(['title' => '元のタイトル']);
        $this->app['env'] = 'local';
        $create = route('admin.notices.create');
        $edit = route('admin.notices.edit', $notice->id);

        $this->withSession(['_token' => 'valid-token'])
            ->post(route('admin.notices.store'), ['_token' => 'stale-token', 'title' => '残るタイトル', 'text' => '残る内容'])
            ->assertRedirect($create)
            ->assertSessionHasErrors(['form' => ExpiredPageResponder::NOTICE_STORE_MESSAGE]);
        $html = $this->get($create)->assertOk()->getContent();
        $this->assertStringContainsString('<p class="mt-6 text-base text-danger" role="alert">ページの有効期限が切れました。もう一度作成してください。</p>', $html);
        $this->assertStringContainsString('value="残るタイトル"', $html);
        $this->assertStringContainsString('>残る内容</textarea>', $html);
        $this->assertStringNotContainsString('stale-token', $html);

        $this->withSession(['_token' => 'valid-token'])
            ->post(route('admin.notices.update'), ['_token' => 'stale-token', 'id' => (string) $notice->id, 'title' => '変えたタイトル', 'text' => '残る内容'])
            ->assertRedirect($edit)
            ->assertSessionHasErrors(['form' => ExpiredPageResponder::NOTICE_UPDATE_MESSAGE]);
        $html = $this->get($edit)->assertOk()->getContent();
        $this->assertStringContainsString('<p class="mt-6 text-base text-danger" role="alert">ページの有効期限が切れました。もう一度修正してください。</p>', $html);
        $this->assertStringContainsString('value="変えたタイトル"', $html);

        $this->assertDatabaseCount('notices', 1);
        $this->assertSame('元のタイトル', $notice->fresh()->title);
    }

    /**
     * 419の戻り先は、ログインとIDの形で変わる。削除の419は一覧に戻って赤字の案内になる
     */
    #[Test]
    public function トークンが古いときの戻り先はログインとidの形で変わる(): void
    {
        $this->app['env'] = 'local';
        $stale = ['_token' => 'stale-token'];

        $this->withSession(['_token' => 'valid-token'])->post(route('admin.notices.store'), $stale)->assertRedirect(route('admin.login'));
        $this->withSession(['_token' => 'valid-token'])->post(route('admin.notices.update'), $stale + ['id' => '1'])->assertRedirect(route('admin.login'));
        $this->withSession(['_token' => 'valid-token'])->delete(route('admin.notices.delete'), $stale + ['id' => '1'])->assertRedirect(route('admin.login'));

        $this->loginAsAdmin();
        foreach ([[], ['id' => 'abc'], ['id' => '0'], ['id' => ['1']]] as $extra) {
            $this->withSession(['_token' => 'valid-token'])->post(route('admin.notices.update'), $stale + $extra)
                ->assertRedirect(route('admin.notices.index'))
                ->assertSessionHasErrors(['notice' => ExpiredPageResponder::NOTICE_UPDATE_MESSAGE]);
        }

        $notice = Notice::factory()->create();
        $this->withSession(['_token' => 'valid-token'])->delete(route('admin.notices.delete'), $stale + ['id' => (string) $notice->id])
            ->assertRedirect(route('admin.notices.index'))
            ->assertSessionHasErrors(['delete' => ExpiredPageResponder::NOTICE_DELETE_MESSAGE]);
        $this->assertDatabaseHas('notices', ['id' => $notice->id]);
    }

    /**
     * 削除の419は、削除の前に見ていた検索条件・ページのお知らせ一覧に戻る（削除はされない）
     */
    #[Test]
    public function 削除の419は削除の前に見ていた一覧に戻る(): void
    {
        $this->app['env'] = 'local';
        $this->loginAsAdmin();
        $notice = Notice::factory()->create();
        $searchUrl = route('admin.notices.search', ['date' => '2026-10-01', 'page' => 2]);

        $this->from($searchUrl)->withSession(['_token' => 'valid-token'])
            ->delete(route('admin.notices.delete'), ['_token' => 'stale-token', 'id' => (string) $notice->id])
            ->assertRedirect($searchUrl)
            ->assertSessionHasErrors(['delete' => ExpiredPageResponder::NOTICE_DELETE_MESSAGE]);
        $this->assertDatabaseHas('notices', ['id' => $notice->id]);
    }

    /**
     * 処理の最初と最後にログを出す
     */
    #[Test]
    public function 処理の最初と最後にログを出す(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create();
        Log::spy();

        $this->get(route('admin.notices.create'));
        $this->store();
        $this->get(route('admin.notices.edit', $notice->id));
        $this->update($notice);
        $this->delete(route('admin.notices.delete'), ['id' => $notice->id]);

        foreach (['create', 'store', 'edit', 'update', 'delete'] as $method) {
            Log::shouldHaveReceived('debug')->with("AdminNoticeController@{$method} start")->once();
            Log::shouldHaveReceived('debug')->with("AdminNoticeController@{$method} end")->once();
        }
    }

    /**
     * 一覧の「修正」のリンクから、そのお知らせの修正ページが開く
     */
    #[Test]
    public function 一覧の修正のリンクから修正ページが開く(): void
    {
        $this->loginAsAdmin();
        $notice = Notice::factory()->create(['title' => '一覧から開くお知らせ']);

        $html = $this->get(route('admin.notices.index'))->getContent();
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('admin.notices.edit', $notice->id), '#').'"#', $html);

        $this->get(route('admin.notices.edit', $notice->id))->assertOk()->assertSee('value="一覧から開くお知らせ"', false);
        $this->assertSame('/admin/notices/edit/'.$notice->id, parse_url(route('admin.notices.edit', $notice->id), PHP_URL_PATH));
    }

    /**
     * コンポーネントの送信先・文言・ボタンの色は、作成と修正で切り替わる（mode が不正なら作成と同じ）
     */
    #[Test]
    public function フォームの作成と修正の切り替え(): void
    {
        $create = new AdminNoticeForm;
        $this->assertFalse($create->isEdit());
        $this->assertSame(route('admin.notices.store'), $create->action());
        $this->assertSame('作成する', $create->submitLabel());
        $this->assertStringContainsString('bg-primary text-ink', $create->submitClass());

        $edit = new AdminNoticeForm('edit', Notice::factory()->make(['title' => '今のタイトル']));
        $this->assertTrue($edit->isEdit());
        $this->assertSame(route('admin.notices.update'), $edit->action());
        $this->assertSame('修正する', $edit->submitLabel());
        $this->assertStringContainsString('bg-mail-link text-white', $edit->submitClass());
        $this->assertSame('今のタイトル', $edit->title);

        $this->assertFalse((new AdminNoticeForm('unknown'))->isEdit());
        $this->assertSame('border-danger', $create->borderClass(true));
        $this->assertSame('border-line-strong', $create->borderClass(false));
    }
}
