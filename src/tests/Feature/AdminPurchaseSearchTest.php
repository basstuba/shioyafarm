<?php

namespace Tests\Feature;

use App\Models\Purchase_history;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の注文一覧の検索（admin.purchase.search）のテスト
 */
class AdminPurchaseSearchTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 検索結果の注文IDを（新しい順に）返す
     *
     * @param  array<string, string>  $query
     * @return list<int>
     */
    private function searchIds(array $query): array
    {
        return array_map(
            fn (Purchase_history $order): int => $order->id,
            $this->search($query)->assertOk()->viewData('purchases')->items(),
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

        return $this->get(route('admin.purchase.search', $query));
    }

    /**
     * 注文日は日本時間の暦日で絞る（前日の23:59は含まれず、当日の0:00と23:59は含まれ、翌日の0:00は含まれない）
     */
    #[Test]
    public function 注文日は日本時間のその日の注文だけが表示される(): void
    {
        $this->makeOrder(createdAt: '2026-11-03 23:59:59');
        $start = $this->makeOrder(createdAt: '2026-11-04 00:00:00');
        $end = $this->makeOrder(createdAt: '2026-11-04 23:59:59');
        $this->makeOrder(createdAt: '2026-11-05 00:00:00');

        $this->assertSame([$end->id, $start->id], $this->searchIds(['date' => '2026-11-04']));
    }

    /**
     * 注文者名は姓名の部分一致で、スペースの有無・全角半角に関係なく見つかる
     */
    #[Test]
    public function 注文者名は姓名のどの入力でも見つかる(): void
    {
        $yamada = $this->makeOrder($this->makeUser('山田', '太郎'));
        $this->makeOrder($this->makeUser('山本', '次郎'));
        $this->makeOrder($this->makeUser('佐藤', '花子'));

        foreach (['山田太郎', '山田 太郎', '山田　太郎', '山田', '太郎', ' 山田　 太郎 ', '田太'] as $name) {
            $this->assertSame([$yamada->id], $this->searchIds(['name' => $name]), "「{$name}」で見つからない");
        }
    }

    /**
     * 注文者名の部分一致は、姓名の境目をまたいでも合う（姓の末尾と名の先頭）
     */
    #[Test]
    public function 姓と名の境目をまたぐ入力でも見つかる(): void
    {
        $order = $this->makeOrder($this->makeUser('鈴木', '一郎'));
        $this->makeOrder($this->makeUser('高橋', '二郎'));

        $this->assertSame([$order->id], $this->searchIds(['name' => '木一']));
    }

    /**
     * LIKE のワイルドカード（%・_）と退避文字（!）は文字として扱う
     */
    #[Test]
    public function ワイルドカードは文字として扱われ他の注文に一致しない(): void
    {
        $this->makeOrder($this->makeUser('山田', '太郎'));
        $percent = $this->makeOrder($this->makeUser('田%中', '太郎'));
        $underscore = $this->makeOrder($this->makeUser('佐_藤', '花子'));
        $bang = $this->makeOrder($this->makeUser('木!村', '次郎'));

        $this->assertSame([$percent->id], $this->searchIds(['name' => '%']));
        $this->assertSame([$underscore->id], $this->searchIds(['name' => '_']));
        $this->assertSame([$bang->id], $this->searchIds(['name' => '!']));
        $this->assertSame([$percent->id], $this->searchIds(['name' => '田%中']));
        $this->assertSame([], $this->searchIds(['name' => '山%郎']));
    }

    /**
     * 名前の中にスペースがある会員（英字の姓名など）も、スペースありでもなしでも見つかる
     */
    #[Test]
    public function 名前の中にスペースがある会員も見つかる(): void
    {
        $mary = $this->makeOrder($this->makeUser('Smith', 'Mary Ann'));
        $zenkaku = $this->makeOrder($this->makeUser('山田', '太郎　次郎'));
        $this->makeOrder($this->makeUser('Jones', 'Tom'));

        foreach (['Mary Ann', 'MaryAnn', 'Smith Mary Ann', 'ry A'] as $name) {
            $this->assertSame([$mary->id], $this->searchIds(['name' => $name]), "「{$name}」で見つからない");
        }
        foreach (['太郎 次郎', '太郎　次郎', '太郎次郎', '山田太郎次郎'] as $name) {
            $this->assertSame([$zenkaku->id], $this->searchIds(['name' => $name]), "「{$name}」で見つからない");
        }
    }

    /**
     * 退会済みの会員の注文も検索できる
     */
    #[Test]
    public function 退会済みの会員の注文も注文者名で見つかる(): void
    {
        $user = $this->makeUser('伊藤', '健');
        $order = $this->makeOrder($user);
        $user->delete();
        $this->makeOrder($this->makeUser('渡辺', '誠'));

        $this->assertSame([$order->id], $this->searchIds(['name' => '伊藤']));
    }

    /**
     * 注文日と注文者名の両方に合う注文だけが表示される。どちらも空なら全件
     */
    #[Test]
    public function 注文日と注文者名の両方に合う注文だけが表示される(): void
    {
        $yamada = $this->makeUser('山田', '太郎');
        $match = $this->makeOrder($yamada, '2026-11-04 10:00:00');
        $this->makeOrder($yamada, '2026-11-03 10:00:00');
        $this->makeOrder($this->makeUser('佐藤', '花子'), '2026-11-04 11:00:00');

        $this->assertSame([$match->id], $this->searchIds(['date' => '2026-11-04', 'name' => '山田']));
        $this->assertCount(3, $this->searchIds(['date' => '', 'name' => '', 'shipped' => '']));
    }

    /**
     * 発送状況で、未発送だけ・発送済みだけに絞り込める。何も選ばないと両方
     */
    #[Test]
    public function 発送状況で絞り込める(): void
    {
        $unshipped = $this->makeOrder();
        $shipped = $this->makeOrder(overrides: ['shipped' => true]);

        $this->assertSame([$unshipped->id], $this->searchIds(['shipped' => '0']));
        $this->assertSame([$shipped->id], $this->searchIds(['shipped' => '1']));
        $this->assertSame([$shipped->id, $unshipped->id], $this->searchIds(['shipped' => '']));
    }

    /**
     * 発送状況・注文日・注文者名を組み合わせられる
     */
    #[Test]
    public function 発送状況は注文日と注文者名と組み合わせられる(): void
    {
        $yamada = $this->makeUser('山田', '太郎');
        $match = $this->makeOrder($yamada, '2026-11-04 10:00:00');
        $this->makeOrder($yamada, '2026-11-04 11:00:00', overrides: ['shipped' => true]);
        $this->makeOrder($this->makeUser('佐藤', '花子'), '2026-11-04 12:00:00');

        $this->assertSame([$match->id], $this->searchIds(['shipped' => '0', 'date' => '2026-11-04', 'name' => '山田']));
    }

    /**
     * 検索後も入力した値が検索欄に残り、エクスポートにも同じ条件が hidden で渡る
     */
    #[Test]
    public function 検索後も入力値が検索欄に残りエクスポートへ同じ条件が渡る(): void
    {
        $this->makeOrder($this->makeUser('山田', '太郎'), '2026-11-04 10:00:00');

        $html = $this->search(['shipped' => '1', 'date' => '2026-11-04', 'name' => '山田'])->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<option value="1" selected>発送済</option>#u', $html);
        $this->assertStringContainsString('value="2026-11-04"', $html);
        $this->assertStringContainsString('value="山田"', $html);
        $this->assertStringContainsString('<input type="hidden" name="type" value="purchase">', $html);
        $this->assertStringContainsString('<input type="hidden" name="shipped" value="1">', $html);
        $this->assertStringContainsString('<input type="hidden" name="date" value="2026-11-04">', $html);
        $this->assertStringContainsString('<input type="hidden" name="name" value="山田">', $html);
    }

    /**
     * ページを移動しても検索条件が保たれる（ページ送りのリンクに条件が付く）
     */
    #[Test]
    public function ページ送りのリンクに検索条件が保たれる(): void
    {
        $user = $this->makeUser('山田', '太郎');
        for ($i = 0; $i < 11; $i++) {
            $this->makeOrder($user);
        }
        $this->makeOrder($this->makeUser('佐藤', '花子'));

        $first = $this->search(['shipped' => '0', 'name' => '山田']);
        $first->assertOk();
        $this->assertSame(11, $first->viewData('purchases')->total());

        $next = route('admin.purchase.search', ['shipped' => '0', 'name' => '山田', 'page' => 2]);
        $first->assertSee(e($next), false);

        $second = $this->get($next)->assertOk();
        $this->assertCount(1, $second->viewData('purchases')->items());
        $second->assertSee('value="山田"', false);
    }

    /**
     * 条件に合う注文がないときは、該当なしの文言を出し、ページ送りは出さず、エクスポートは押せない
     */
    #[Test]
    public function 該当する注文がないときは文言を出しエクスポートは押せない(): void
    {
        $this->makeOrder($this->makeUser('山田', '太郎'));

        $response = $this->search(['name' => '該当しない名前'])->assertOk();

        $response->assertSee('該当する注文はありません。');
        $response->assertDontSee('aria-label="ページ送り"', false);
        $response->assertSee('type="submit" disabled>エクスポート</button>', false);
    }

    /**
     * 注文日が日付でないときは、一覧に戻って検索欄の近くにエラーを出す（500にしない）
     */
    #[Test]
    public function 注文日が日付でないときは検索欄の近くにエラーが表示される(): void
    {
        $this->loginAsAdmin();
        $this->makeOrder();

        $this->get(route('admin.purchase.search', ['date' => 'abc']))
            ->assertRedirect(route('admin.purchase.index'))
            ->assertSessionHasErrors(['date' => '注文日は日付で入力してください。']);

        $html = $this->followingRedirects()
            ->get(route('admin.purchase.search', ['date' => 'abc']))
            ->assertOk()
            ->assertSeeInOrder(['role="search"', 'role="alert"', '注文日は日付で入力してください。', '<table'], false)
            ->getContent();
        // 不正な値は type=date の欄に戻さない（ブラウザのコンソール警告になる）
        $this->assertStringContainsString('id="date" name="date" type="date" value=""', $html);
        $this->assertStringNotContainsString('value="abc"', $html);
        // 検索欄の値と、一覧・エクスポートの条件（全件）がずれるため、エクスポートは押せない
        $this->assertStringContainsString('type="submit" disabled>エクスポート</button>', $html);
    }

    /**
     * 配列で送られた検索条件は、500にならず、エラーを表示して入力欄には戻さない
     */
    #[Test]
    public function 配列の入力は500にならず入力欄に戻さない(): void
    {
        $this->loginAsAdmin();
        $this->makeOrder();

        foreach (['name' => '注文者名は文字で入力してください。', 'date' => '注文日は日付で入力してください。', 'shipped' => '発送状況は「未発送」か「発送済」を選んでください。'] as $key => $message) {
            $html = $this->followingRedirects()
                ->get(route('admin.purchase.search', [$key => ['x']]))
                ->assertOk()
                ->assertSee($message)
                ->getContent();
            $this->assertStringNotContainsString('value="Array"', $html, $key);
            $this->assertStringNotContainsString('value="x"', $html, $key);
        }
    }

    /**
     * 存在しない日付（2月30日）や形式違いもエラーにする
     */
    #[Test]
    public function 存在しない日付や形式違いはエラーになる(): void
    {
        $this->loginAsAdmin();

        foreach (['2026-02-30', '2026/11/04', '20261104'] as $date) {
            $this->get(route('admin.purchase.search', ['date' => $date]))->assertSessionHasErrors('date');
        }
    }

    /**
     * 発送状況が選択肢以外、注文者名が長すぎるときもエラー（配列の入力も弾く）
     */
    #[Test]
    public function 発送状況と注文者名の不正な値はエラーになる(): void
    {
        $this->loginAsAdmin();

        $this->get(route('admin.purchase.search', ['shipped' => '2']))->assertSessionHasErrors('shipped');
        // 「0」「1」以外は、数値として等しくても弾く（サービスの判定とずれて未発送扱いになるため）
        foreach (['1.0', '01', '00', '0.0', '10', 'true', '-1'] as $shipped) {
            $this->get(route('admin.purchase.search', ['shipped' => $shipped]))->assertSessionHasErrors('shipped');
        }
        // 空（空白だけも、前後の空白を除いたあとで空になる）は絞り込まない
        foreach (['0', '1', '', ' '] as $shipped) {
            $this->get(route('admin.purchase.search', ['shipped' => $shipped]))->assertSessionHasNoErrors();
        }
        $this->get(route('admin.purchase.search', ['name' => str_repeat('あ', 101)]))->assertSessionHasErrors('name');
        $this->get(route('admin.purchase.search', ['name' => ['a']]))->assertSessionHasErrors('name');
        $this->get(route('admin.purchase.search', ['date' => ['2026-11-04']]))->assertSessionHasErrors('date');
    }

    /**
     * 壊れた文字（不正な UTF-8）を含む注文者名はエラーにする（絞り込みが外れて全件が出ないようにする）
     */
    #[Test]
    public function 不正なutf8の注文者名はエラーになり全件は表示されない(): void
    {
        $this->loginAsAdmin();
        $this->makeOrder($this->makeUser('山田', '太郎'));
        $this->makeOrder($this->makeUser('佐藤', '花子'));
        $broken = "山田\xFF";

        $this->get(route('admin.purchase.search', ['name' => $broken]))
            ->assertRedirect(route('admin.purchase.index'))
            ->assertSessionHasErrors(['name' => '注文者名は文字で入力してください。']);

        // 戻った一覧は壊れず表示され、エラーは1件だけで、エクスポートは押せない
        $html = $this->followingRedirects()
            ->get(route('admin.purchase.search', ['name' => $broken]))
            ->assertOk()
            ->getContent();
        $this->assertSame(1, substr_count($html, '注文者名は文字で入力してください。'));
        $this->assertStringContainsString('type="submit" disabled>エクスポート</button>', $html);
    }
}
