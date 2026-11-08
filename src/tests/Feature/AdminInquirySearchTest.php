<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面のお問い合わせ一覧の検索（admin.inquiries.search）のテスト
 */
class AdminInquirySearchTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 管理者として検索する
     *
     * @param  array<string, mixed>  $query
     */
    private function search(array $query): TestResponse
    {
        $this->loginAsAdmin();

        return $this->get(route('admin.inquiries.search', $query));
    }

    /**
     * 検索結果のお問い合わせIDを（新しい順に）返す
     *
     * @param  array<string, mixed>  $query
     * @return list<int>
     */
    private function searchIds(array $query): array
    {
        return array_map(
            fn (Inquiry $inquiry): int => $inquiry->id,
            $this->search($query)->assertOk()->viewData('inquiries')->items(),
        );
    }

    /**
     * 問い合わせ日は日本時間のその日だけが見つかる（前日 23:59 と当日 00:00 の境界）
     */
    #[Test]
    public function 問い合わせ日は日本時間の境界で絞り込まれる(): void
    {
        $this->loginAsAdmin();
        $before = Inquiry::factory()->create(['created_at' => '2026-11-07 23:59:59']);
        $start = Inquiry::factory()->create(['created_at' => '2026-11-08 00:00:00']);
        $end = Inquiry::factory()->create(['created_at' => '2026-11-08 23:59:59']);
        $after = Inquiry::factory()->create(['created_at' => '2026-11-09 00:00:00']);

        $this->assertSame([$end->id, $start->id], $this->searchIds(['date' => '2026-11-08']));
        $this->assertSame([$before->id], $this->searchIds(['date' => '2026-11-07']));
        $this->assertSame([$after->id], $this->searchIds(['date' => '2026-11-09']));
    }

    /**
     * 対応状況：「1」は対応済みだけ、「0」は未対応だけ、空は全件
     */
    #[Test]
    public function 対応状況で絞り込まれる(): void
    {
        $this->loginAsAdmin();
        $done = Inquiry::factory()->addressed()->create(['created_at' => '2026-11-01 09:00:00']);
        $todo = Inquiry::factory()->create(['created_at' => '2026-11-02 09:00:00']);

        $this->assertSame([$done->id], $this->searchIds(['addressed' => '1']));
        $this->assertSame([$todo->id], $this->searchIds(['addressed' => '0']));
        $this->assertSame([$todo->id, $done->id], $this->searchIds(['addressed' => '']));
        $this->assertSame([$todo->id, $done->id], $this->searchIds([]));
    }

    /**
     * 対応状況と問い合わせ日を組み合わせると、両方に合うものだけが見つかる
     */
    #[Test]
    public function 対応状況と問い合わせ日を組み合わせて絞り込める(): void
    {
        $this->loginAsAdmin();
        Inquiry::factory()->addressed()->create(['created_at' => '2026-11-08 09:00:00']);
        $target = Inquiry::factory()->create(['created_at' => '2026-11-08 10:00:00']);
        Inquiry::factory()->create(['created_at' => '2026-11-07 10:00:00']);

        $this->assertSame([$target->id], $this->searchIds(['addressed' => '0', 'date' => '2026-11-08']));
    }

    /**
     * 検索後も入力した値が検索欄に残り、ページ送りのリンクにも検索条件が付く
     */
    #[Test]
    public function 検索条件は検索欄とページ送りに残る(): void
    {
        $this->loginAsAdmin();
        for ($i = 0; $i < 11; $i++) {
            Inquiry::factory()->create(['created_at' => '2026-11-08 09:00:00']);
        }
        Inquiry::factory()->create(['created_at' => '2026-11-05 09:00:00']);

        $html = $this->get(route('admin.inquiries.search', ['addressed' => '0', 'date' => '2026-11-08']))->assertOk()->getContent();

        $this->assertStringContainsString('value="2026-11-08"', $html);
        $this->assertMatchesRegularExpression('#<option value="0"[^>]*selected[^>]*>非対応</option>#u', $html);
        $this->assertStringContainsString(e(route('admin.inquiries.search', ['addressed' => '0', 'date' => '2026-11-08', 'page' => 2])), $html);
        // リセットは全件の一覧
        $this->assertStringContainsString('href="'.route('admin.inquiries.index').'"', $html);
    }

    /**
     * 該当がないときは案内を出し、表とページ送りは出さない
     */
    #[Test]
    public function 該当がないときは案内を出す(): void
    {
        $this->loginAsAdmin();
        Inquiry::factory()->create(['created_at' => '2026-11-08 09:00:00']);

        $response = $this->get(route('admin.inquiries.search', ['date' => '2026-01-01']));

        $response->assertOk()->assertSee('該当するお問い合わせはありません。');
        $response->assertDontSee('<table', false);
        $response->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 入力の誤りは、お問い合わせ一覧（全件の表示）に戻してエラーを出す。500にならない
     */
    #[Test]
    public function 不正な値は一覧に戻りエラーが表示される(): void
    {
        $this->loginAsAdmin();
        Inquiry::factory()->create();

        $cases = [
            ['date' => 'abc'],
            ['date' => '2026-02-30'],
            ['date' => '2026/11/08'],
            ['date' => ['2026-11-08']],
            ['addressed' => '9'],
            ['addressed' => '1.0'],
            ['addressed' => '01'],
            ['addressed' => ['1']],
        ];
        foreach ($cases as $query) {
            $this->get(route('admin.inquiries.search', $query))->assertRedirect(route('admin.inquiries.index'));
        }

        $this->get(route('admin.inquiries.search', ['date' => 'abc']));
        $this->get(route('admin.inquiries.index'))->assertOk()->assertSee('問い合わせ日は日付で入力してください。')->assertSee('role="alert"', false);

        $this->get(route('admin.inquiries.search', ['addressed' => '9']));
        $this->get(route('admin.inquiries.index'))->assertOk()->assertSee('対応状況は「対応済」「非対応」のどちらかを選んでください。');
    }
}
