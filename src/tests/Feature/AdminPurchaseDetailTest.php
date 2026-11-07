<?php

namespace Tests\Feature;

use App\Livewire\AdminPurchaseDetail;
use App\Models\Purchase_history;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Constraints\SeeInOrder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の注文一覧の詳細モーダル（Livewire の AdminPurchaseDetail）のテスト
 */
class AdminPurchaseDetailTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 一覧の「詳細」ボタンと同じ、open-purchase-detail のイベントで詳細を開く
     */
    private function openFor(Purchase_history $order): Testable
    {
        return Livewire::test(AdminPurchaseDetail::class)->dispatch('open-purchase-detail', id: $order->id);
    }

    /**
     * コンポーネントの現在の表示に、文言が順に含まれていることを確認する
     *
     * Livewire の assertSeeInOrder は最初に表示したときの内容で判定するため、操作後の表示はこちらで確認する。
     *
     * @param  list<string>  $values
     */
    private function assertTextInOrder(Testable $component, array $values): void
    {
        $this->assertThat(array_map(e(...), $values), new SeeInOrder($component->html()));
    }

    /**
     * コンポーネントの現在の表示に、文言が含まれていないことを確認する
     *
     * @param  list<string>  $values
     */
    private function assertTextMissing(Testable $component, array $values): void
    {
        foreach ($values as $value) {
            $this->assertStringNotContainsString(e($value), $component->html(), $value);
        }
    }

    /**
     * 閉じているときは、モーダルを出さない
     */
    #[Test]
    public function 開く前はモーダルを表示しない(): void
    {
        $this->loginAsAdmin();

        Livewire::test(AdminPurchaseDetail::class)
            ->assertSet('showDetail', false)
            ->assertDontSee('注文管理番号');
    }

    /**
     * 項目が決められた順に、金額つきで表示される（クレジットカード：代引手数料も振込の案内もなし）
     */
    #[Test]
    public function クレジットカードの注文の詳細が決められた順に表示される(): void
    {
        $this->loginAsAdmin();
        $order = $this->makeOrder(
            $this->makeUser('山田', '太郎'),
            '2026-11-04 09:30:00',
            [['じゃがいも', 2, 330, 2], ['玉ねぎ', 5, 795, 1], ['にんじん', 2, 418, 1]],
            overrides: ['delivery_name' => '山田 花子', 'delivery_prefecture' => '北海道', 'delivery_city' => '札幌市', 'delivery_tell' => '09012345678'],
        );
        $total = 660 + 795 + 418 + 940;

        $component = $this->openFor($order)->assertSet('showDetail', true)->assertSet('detailId', $order->id);

        $this->assertTextInOrder($component, [
            '注文管理番号', (string) $order->id,
            '注文日', '2026/11/04',
            '注文者', '山田 太郎',
            '商品内訳',
            'じゃがいも 2kg', '660円', '330円(税込)', '2個',
            '玉ねぎ 5kg', '795円', '795円(税込)', '1個',
            'にんじん 2kg', '418円', '418円(税込)',
            '送料', '940円',
            '合計金額', number_format($total).'円',
            '配送先氏名', '山田 花子',
            '配送先住所', '北海道札幌市',
            '配送先電話番号', '09012345678',
            '支払い方法', 'クレジットカード',
            '閉じる',
        ]);
        $this->assertSame($total, $order->total_price);
        $this->assertTextMissing($component, ['代引手数料', 'お支払いの状況']);
    }

    /**
     * 代金引換のときは代引手数料（config の値）と、手数料を含む合計金額を表示する
     */
    #[Test]
    public function 代金引換の注文では代引手数料が表示される(): void
    {
        $this->loginAsAdmin();
        $codFee = (int) config('shipping.cod_fee');
        $order = $this->makeOrder(lines: [['じゃがいも', 2, 330, 1]], method: 'cod');

        $this->assertTextInOrder($this->openFor($order), [
            '送料', '940円',
            '代引手数料', number_format($codFee).'円',
            '合計金額', number_format(330 + 940 + $codFee).'円',
            '支払い方法', '代金引換',
        ]);
    }

    /**
     * 銀行振込は、振込先を案内した注文と、注文時に顧客残高から支払い済みの注文で文言が違い、口座情報や入金済みは出さない
     */
    #[Test]
    public function 銀行振込の注文ではお支払いの状況が文言で表示され口座情報は出ない(): void
    {
        $this->loginAsAdmin();
        $guided = $this->makeOrder(method: 'customer_balance');
        $this->attachPurchaseBankTransfer($guided);
        $paid = $this->makeOrder(method: 'customer_balance');
        $this->attachPurchaseBankTransfer($paid, true);

        $guidedView = $this->openFor($guided);
        $this->assertTextInOrder($guidedView, ['支払い方法', '銀行振込', 'お支払いの状況', '振込先をご案内済みです。入金はStripeの管理画面でご確認ください']);
        $this->assertTextMissing($guidedView, ['注文時にお客様の残高からお支払い済みです']);

        $paidView = $this->openFor($paid);
        $this->assertTextInOrder($paidView, ['お支払いの状況', '注文時にお客様の残高からお支払い済みです']);
        $this->assertTextMissing($paidView, ['振込先をご案内済みです']);

        foreach ([$guidedView, $paidView] as $view) {
            $this->assertTextMissing($view, ['いろは銀行', '中央支店', '口座番号', 'ストライプジャパン', '未入金']);
            // 「入金済み」は、取消の確認の返金の案内（入金済みの場合、返金は〜）にだけ出る
            $this->assertStringNotContainsString('入金済み', str_replace('入金済みの場合、返金はStripeの管理画面で行ってください。', '', $view->html()));
        }
    }

    /**
     * 退会済みの会員の注文も、注文者名つきで詳細を表示できる
     */
    #[Test]
    public function 退会済みの会員の注文も詳細を表示できる(): void
    {
        $this->loginAsAdmin();
        $user = $this->makeUser('佐藤', '花子');
        $order = $this->makeOrder($user);
        $user->delete();

        $this->assertTextInOrder($this->openFor($order), ['注文者', '佐藤 花子']);
    }

    /**
     * 詳細を開くとき、同じリクエストで注文を2回取得しない
     */
    #[Test]
    public function 詳細を開くときの注文の取得は1回だけである(): void
    {
        $this->loginAsAdmin();
        $order = $this->makeOrder();

        DB::enableQueryLog();
        $this->openFor($order)->assertSet('showDetail', true);

        $selects = array_filter(
            array_column(DB::getQueryLog(), 'query'),
            fn (string $sql): bool => str_starts_with($sql, 'select') && str_contains($sql, 'from "purchase_histories"'),
        );
        $this->assertCount(1, $selects);
    }

    /**
     * 存在しない注文は何も開かない
     */
    #[Test]
    public function 存在しない注文は何も開かない(): void
    {
        $this->loginAsAdmin();
        $order = $this->makeOrder();

        Livewire::test(AdminPurchaseDetail::class)
            ->dispatch('open-purchase-detail', id: $order->id + 100)
            ->assertSet('showDetail', false)
            ->assertSet('detailId', null)
            ->assertDontSee('注文管理番号');
    }

    /**
     * 管理者としてログインしていないときは開けない
     */
    #[Test]
    public function 管理者としてログインしていないときは開けない(): void
    {
        $order = $this->makeOrder();

        Livewire::test(AdminPurchaseDetail::class)
            ->dispatch('open-purchase-detail', id: $order->id)
            ->assertForbidden();

        $this->loginAsMember();
        Livewire::test(AdminPurchaseDetail::class)
            ->dispatch('open-purchase-detail', id: $order->id)
            ->assertForbidden();
    }

    /**
     * 開いている注文のIDは、ブラウザから書き換えられない
     */
    #[Test]
    public function 開いている注文のidは書き換えられない(): void
    {
        $this->loginAsAdmin();
        $order = $this->makeOrder();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->openFor($order)->set('detailId', $order->id + 1);
    }

    /**
     * 閉じ方：下の文字だけの「閉じる」（高さ44px以上）・背景・Esc。×は付けない。幅400px・角丸30・内側でスクロール
     */
    #[Test]
    public function 閉じる操作と見た目のクラスがある(): void
    {
        $this->loginAsAdmin();
        $order = $this->makeOrder();

        $html = $this->openFor($order)->html();

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        // 確認のモーダル（取消）が開いているときは、Esc・背景・「閉じる」は確認だけを閉じる（Esc）／確認も閉じて詳細を閉じる（背景・「閉じる」）
        $this->assertStringContainsString('x-on:keydown.escape.window="if (confirming) { confirming = false } else { $wire.showDetail = false }"', $html);
        $this->assertStringContainsString('absolute inset-0 bg-black/35" x-on:click="confirming = false; $wire.showDetail = false"', $html);
        $this->assertMatchesRegularExpression('#<button type="button" class="[^"]*min-h-11[^"]*" x-on:click="confirming = false; \$wire.showDetail = false">閉じる</button>#u', $html);
        $this->assertStringNotContainsString('×', $html);
        $this->assertStringContainsString('max-h-full w-full max-w-[400px] overflow-y-auto rounded-[30px]', $html);
        $this->assertStringContainsString('shadow-panel', $html);
        // ラベル16px 太字・値14px・線 line-modal
        $this->assertStringContainsString('text-base leading-5 font-bold', $html);
        $this->assertStringContainsString('mt-2 text-sm break-words', $html);
        $this->assertStringContainsString('border-line-modal', $html);
    }
}
