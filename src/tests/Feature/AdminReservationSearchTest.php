<?php

namespace Tests\Feature;

use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の予約一覧の検索（admin.reservations.search）のテスト
 */
class AdminReservationSearchTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 検索結果の予約IDを（新しい順に）返す
     *
     * @param  array<string, string>  $query
     * @return list<int>
     */
    private function searchIds(array $query): array
    {
        return array_map(
            fn (Reservation $reservation): int => $reservation->id,
            $this->search($query)->assertOk()->viewData('reservations')->items(),
        );
    }

    /**
     * 管理者として検索する
     *
     * @param  array<string, string>  $query
     */
    private function search(array $query): TestResponse
    {
        $this->loginAsAdmin();

        return $this->get(route('admin.reservations.search', $query));
    }

    /**
     * 予約日は日本時間の暦日で絞る
     */
    #[Test]
    public function 予約日は日本時間のその日の予約だけが表示される(): void
    {
        $this->makeAdminReservation(createdAt: '2026-11-03 23:59:59');
        $start = $this->makeAdminReservation(createdAt: '2026-11-04 00:00:00');
        $end = $this->makeAdminReservation(createdAt: '2026-11-04 23:59:59');
        $this->makeAdminReservation(createdAt: '2026-11-05 00:00:00');

        $this->assertSame([$end->id, $start->id], $this->searchIds(['date' => '2026-11-04']));
    }

    /**
     * 予約者名は、姓名のどの入力でも見つかる（スペースの有無、姓だけ、名だけ）。他の会員は出ない
     */
    #[Test]
    public function 予約者名は姓名のどの入力でも見つかる(): void
    {
        $target = $this->makeAdminReservation($this->makeUser('山田', '太郎'));
        $this->makeAdminReservation($this->makeUser('佐藤', '花子'));

        foreach (['山田太郎', '山田 太郎', '山田　太郎', '山田', '太郎', '田太'] as $name) {
            $this->assertSame([$target->id], $this->searchIds(['name' => $name]), $name);
        }
    }

    /**
     * LIKE のワイルドカードは文字として扱う
     */
    #[Test]
    public function ワイルドカードは文字として扱われ他の予約に一致しない(): void
    {
        $this->makeAdminReservation($this->makeUser('山田', '太郎'));

        $this->assertSame([], $this->searchIds(['name' => '%']));
        $this->assertSame([], $this->searchIds(['name' => '山_太郎']));
    }

    /**
     * 退会済みの会員の予約も予約者名で見つかる
     */
    #[Test]
    public function 退会済みの会員の予約も予約者名で見つかる(): void
    {
        $user = $this->makeUser('伊藤', '健');
        $reservation = $this->makeAdminReservation($user);
        $user->delete();

        $this->assertSame([$reservation->id], $this->searchIds(['name' => '伊藤']));
    }

    /**
     * 発送状況で絞り込める
     */
    #[Test]
    public function 発送状況で絞り込める(): void
    {
        $unshipped = $this->makeAdminReservation();
        $shipped = $this->makeAdminReservation(overrides: ['shipped' => true]);

        $this->assertSame([$unshipped->id], $this->searchIds(['shipped' => '0']));
        $this->assertSame([$shipped->id], $this->searchIds(['shipped' => '1']));
        $this->assertSame([$shipped->id, $unshipped->id], $this->searchIds(['shipped' => '']));
    }

    /**
     * 発送状況・予約日・予約者名は組み合わせられ、すべてに合う予約だけが表示される。すべて空なら全件
     */
    #[Test]
    public function 条件を組み合わせるとすべてに合う予約だけが表示される(): void
    {
        $yamada = $this->makeUser('山田', '太郎');
        $target = $this->makeAdminReservation($yamada, '2026-11-04 10:00:00');
        $this->makeAdminReservation($yamada, '2026-11-04 11:00:00', overrides: ['shipped' => true]);
        $this->makeAdminReservation($yamada, '2026-11-03 10:00:00');
        $this->makeAdminReservation($this->makeUser('佐藤', '花子'), '2026-11-04 10:00:00');

        $this->assertSame([$target->id], $this->searchIds(['shipped' => '0', 'date' => '2026-11-04', 'name' => '山田']));
        $this->assertCount(4, $this->searchIds(['shipped' => '', 'date' => '', 'name' => '']));
    }

    /**
     * 検索後も入力した値が検索欄に残り、エクスポートにも同じ条件が hidden で渡る
     */
    #[Test]
    public function 検索後も入力値が検索欄に残りエクスポートへ同じ条件が渡る(): void
    {
        $this->makeAdminReservation($this->makeUser('山田', '太郎'), '2026-11-04 10:00:00');

        $html = $this->search(['shipped' => '1', 'date' => '2026-11-04', 'name' => '山田'])->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<option value="1" selected>発送済</option>#u', $html);
        $this->assertStringContainsString('value="2026-11-04"', $html);
        $this->assertStringContainsString('value="山田"', $html);
        $this->assertStringContainsString('<input type="hidden" name="type" value="reservation">', $html);
        $this->assertStringContainsString('<input type="hidden" name="shipped" value="1">', $html);
        $this->assertStringContainsString('<input type="hidden" name="date" value="2026-11-04">', $html);
        $this->assertStringContainsString('<input type="hidden" name="name" value="山田">', $html);
        // 検索の送り先は予約の検索、リセットは予約一覧
        $this->assertStringContainsString('action="'.route('admin.reservations.search').'"', $html);
        $this->assertStringContainsString('href="'.route('admin.reservations.index').'">リセット', $html);
    }

    /**
     * ページを移動しても検索条件が保たれる
     */
    #[Test]
    public function ページ送りのリンクに検索条件が保たれる(): void
    {
        $user = $this->makeUser('山田', '太郎');
        for ($i = 0; $i < 11; $i++) {
            $this->makeAdminReservation($user);
        }
        $this->makeAdminReservation($this->makeUser('佐藤', '花子'));

        $first = $this->search(['shipped' => '0', 'name' => '山田']);
        $first->assertOk();
        $this->assertSame(11, $first->viewData('reservations')->total());

        $next = route('admin.reservations.search', ['shipped' => '0', 'name' => '山田', 'page' => 2]);
        $first->assertSee(e($next), false);

        $second = $this->get($next)->assertOk();
        $this->assertCount(1, $second->viewData('reservations')->items());
        $second->assertSee('value="山田"', false);
    }

    /**
     * 条件に合う予約がないときは、該当なしの文言を出し、ページ送りは出さず、エクスポートは押せない
     */
    #[Test]
    public function 該当する予約がないときは文言を出しエクスポートは押せない(): void
    {
        $this->makeAdminReservation($this->makeUser('山田', '太郎'));

        $response = $this->search(['name' => '該当しない名前'])->assertOk();

        $response->assertSee('該当する予約はありません。');
        $response->assertDontSee('aria-label="ページ送り"', false);
        $response->assertSee('type="submit" disabled>エクスポート</button>', false);
    }

    /**
     * 予約日が日付でないときは、予約一覧に戻って検索欄の近くにエラーを出す（注文一覧には戻らない）
     */
    #[Test]
    public function 予約日が日付でないときは検索欄の近くにエラーが表示される(): void
    {
        $this->loginAsAdmin();
        $this->makeAdminReservation();

        $this->get(route('admin.reservations.search', ['date' => 'abc']))
            ->assertRedirect(route('admin.reservations.index'))
            ->assertSessionHasErrors(['date' => '予約日は日付で入力してください。']);

        $html = $this->followingRedirects()
            ->get(route('admin.reservations.search', ['date' => 'abc']))
            ->assertOk()
            ->assertSeeInOrder(['role="search"', 'role="alert"', '予約日は日付で入力してください。', '<table'], false)
            ->getContent();
        $this->assertStringContainsString('id="date" name="date" type="date" value=""', $html);
        $this->assertStringContainsString('type="submit" disabled>エクスポート</button>', $html);
    }

    /**
     * 配列で送られた検索条件は、500にならず、予約の文言のエラーを表示する
     */
    #[Test]
    public function 配列の入力は500にならず予約の文言のエラーになる(): void
    {
        $this->loginAsAdmin();
        $this->makeAdminReservation();

        foreach (['name' => '予約者名は文字で入力してください。', 'date' => '予約日は日付で入力してください。', 'shipped' => '発送状況は「未発送」「発送済」「取消済」のどれかを選んでください。'] as $key => $message) {
            $html = $this->followingRedirects()
                ->get(route('admin.reservations.search', [$key => ['x']]))
                ->assertOk()
                ->assertSee($message)
                ->getContent();
            $this->assertStringNotContainsString('value="Array"', $html, $key);
        }
    }

    /**
     * 存在しない日付・不正な発送状況・長すぎる予約者名はエラー
     */
    #[Test]
    public function 不正な検索条件はエラーになる(): void
    {
        $this->loginAsAdmin();

        foreach (['2026-02-30', '2026/11/04', '20261104'] as $date) {
            $this->get(route('admin.reservations.search', ['date' => $date]))->assertSessionHasErrors('date');
        }
        foreach (['2', '1.0', '01', 'true'] as $shipped) {
            $this->get(route('admin.reservations.search', ['shipped' => $shipped]))->assertSessionHasErrors('shipped');
        }
        $this->get(route('admin.reservations.search', ['name' => str_repeat('あ', 101)]))
            ->assertSessionHasErrors(['name' => '予約者名は100文字以内で入力してください。']);
    }

    /**
     * 壊れた文字（不正な UTF-8）を含む予約者名はエラーにする（絞り込みが外れて全件が出ないようにする）
     */
    #[Test]
    public function 不正なutf8の予約者名はエラーになり全件は表示されない(): void
    {
        $this->loginAsAdmin();
        $this->makeAdminReservation($this->makeUser('山田', '太郎'));
        $this->makeAdminReservation($this->makeUser('佐藤', '花子'));
        $broken = "山田\xFF";

        $this->get(route('admin.reservations.search', ['name' => $broken]))
            ->assertRedirect(route('admin.reservations.index'))
            ->assertSessionHasErrors(['name' => '予約者名は文字で入力してください。']);

        // 戻った一覧は壊れず表示され、エラーは1件だけで、エクスポートは押せない
        $html = $this->followingRedirects()
            ->get(route('admin.reservations.search', ['name' => $broken]))
            ->assertOk()
            ->getContent();
        $this->assertSame(1, substr_count($html, '予約者名は文字で入力してください。'));
        $this->assertStringContainsString('type="submit" disabled>エクスポート</button>', $html);
    }
}
