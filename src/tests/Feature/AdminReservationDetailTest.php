<?php

namespace Tests\Feature;

use App\Livewire\AdminReservationDetail;
use App\Livewire\MyPageReservationHistory;
use App\Models\Reservation;
use App\Services\AdminReservationService;
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
 * 管理画面の予約一覧の詳細モーダル（Livewire の AdminReservationDetail）のテスト
 */
class AdminReservationDetailTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 一覧の「詳細」ボタンと同じ、open-reservation-detail のイベントで詳細を開く
     */
    private function openFor(Reservation $reservation): Testable
    {
        return Livewire::test(AdminReservationDetail::class)->dispatch('open-reservation-detail', id: $reservation->id);
    }

    /**
     * コンポーネントの現在の表示に、文言が順に含まれていることを確認する
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
     * 開く前は、モーダルを出さない
     */
    #[Test]
    public function 開く前はモーダルを表示しない(): void
    {
        $this->loginAsAdmin();

        Livewire::test(AdminReservationDetail::class)
            ->assertSet('showDetail', false)
            ->assertDontSee('予約管理番号');
    }

    /**
     * 項目が決められた順に、金額つきで表示される（クレジットカード：代引手数料も振込の案内もなし）
     */
    #[Test]
    public function クレジットカードの予約の詳細が決められた順に表示される(): void
    {
        $this->loginAsAdmin();
        $reservation = $this->makeAdminReservation(
            $this->makeUser('山田', '太郎'),
            '2026-11-04 09:30:00',
            overrides: ['quantity' => 2, 'subtotal' => 2900, 'total_price' => 4430, 'shipping_fee' => 1530, 'delivery_name' => '山田 花子', 'delivery_prefecture' => '北海道', 'delivery_city' => '札幌市', 'delivery_tell' => '09012345678'],
        );

        $component = $this->openFor($reservation)->assertSet('showDetail', true)->assertSet('detailId', $reservation->id);

        $this->assertTextInOrder($component, [
            '予約管理番号', (string) $reservation->id,
            '予約日', '2026/11/04',
            '予約者', '山田 太郎',
            '商品内訳',
            '北の恵みセット 10kg', '2,900円', '1,450円(税込)', '2個',
            '送料', '1,530円',
            '合計金額', '4,430円',
            '配送先氏名', '山田 花子',
            '配送先住所', '北海道札幌市',
            '配送先電話番号', '09012345678',
            '支払い方法', 'クレジットカード',
            '閉じる',
        ]);
        $this->assertTextMissing($component, ['代引手数料', 'お支払いの状況', '注文']);
        $component->assertSee('予約の詳細');
        $component->assertSeeHtml('aria-labelledby="admin-reservation-detail-title"');
    }

    /**
     * 銀行振込は、振込先を案内した予約と、予約時に顧客残高から支払い済みの予約で文言が違い、口座情報や入金済みは出さない
     */
    #[Test]
    public function 銀行振込の予約ではお支払いの状況が文言で表示され口座情報は出ない(): void
    {
        $this->loginAsAdmin();
        $guided = $this->makeAdminReservation(method: 'customer_balance');
        $this->attachReservationBankTransfer($guided);
        $paid = $this->makeAdminReservation(method: 'customer_balance');
        $this->attachReservationBankTransfer($paid, true);

        $guidedView = $this->openFor($guided);
        $this->assertTextInOrder($guidedView, ['支払い方法', '銀行振込', 'お支払いの状況', '振込先をご案内済みです。入金はStripeの管理画面でご確認ください']);
        $this->assertTextMissing($guidedView, ['予約時にお客様の残高からお支払い済みです']);

        $paidView = $this->openFor($paid);
        $this->assertTextInOrder($paidView, ['お支払いの状況', '予約時にお客様の残高からお支払い済みです']);
        $this->assertTextMissing($paidView, ['振込先をご案内済みです', '注文時に']);

        foreach ([$guidedView, $paidView] as $view) {
            $this->assertTextMissing($view, ['いろは銀行', '中央支店', '口座番号', 'ストライプジャパン', '入金済み', '未入金']);
        }
    }

    /**
     * 退会済みの会員の予約も、予約者名つきで詳細を表示できる
     */
    #[Test]
    public function 退会済みの会員の予約も詳細を表示できる(): void
    {
        $this->loginAsAdmin();
        $user = $this->makeUser('佐藤', '花子');
        $reservation = $this->makeAdminReservation($user);
        $user->delete();

        $this->assertTextInOrder($this->openFor($reservation), ['予約者', '佐藤 花子']);
    }

    /**
     * 詳細を開くとき、同じリクエストで予約を2回取得しない
     */
    #[Test]
    public function 詳細を開くときの予約の取得は1回だけである(): void
    {
        $this->loginAsAdmin();
        $reservation = $this->makeAdminReservation();

        DB::enableQueryLog();
        $this->openFor($reservation)->assertSet('showDetail', true);

        $selects = array_filter(
            array_column(DB::getQueryLog(), 'query'),
            fn (string $sql): bool => str_starts_with($sql, 'select') && str_contains($sql, 'from "reservations"'),
        );
        $this->assertCount(1, $selects);
    }

    /**
     * 存在しない予約は何も開かない
     */
    #[Test]
    public function 存在しない予約は何も開かない(): void
    {
        $this->loginAsAdmin();
        $reservation = $this->makeAdminReservation();

        Livewire::test(AdminReservationDetail::class)
            ->dispatch('open-reservation-detail', id: $reservation->id + 100)
            ->assertSet('showDetail', false)
            ->assertSet('detailId', null)
            ->assertDontSee('予約管理番号');
    }

    /**
     * 管理者としてログインしていないときは開けない
     */
    #[Test]
    public function 管理者としてログインしていないときは開けない(): void
    {
        $reservation = $this->makeAdminReservation();

        Livewire::test(AdminReservationDetail::class)
            ->dispatch('open-reservation-detail', id: $reservation->id)
            ->assertForbidden();

        $this->loginAsMember();
        Livewire::test(AdminReservationDetail::class)
            ->dispatch('open-reservation-detail', id: $reservation->id)
            ->assertForbidden();
    }

    /**
     * 開いている予約のIDは、ブラウザから書き換えられない
     */
    #[Test]
    public function 開いている予約のidは書き換えられない(): void
    {
        $this->loginAsAdmin();
        $reservation = $this->makeAdminReservation();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->openFor($reservation)->set('detailId', $reservation->id + 1);
    }

    /**
     * 閉じ方と見た目のクラスは注文のモーダルと同じ
     */
    #[Test]
    public function 閉じる操作と見た目のクラスがある(): void
    {
        $this->loginAsAdmin();
        $reservation = $this->makeAdminReservation();

        $html = $this->openFor($reservation)->html();

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window="$wire.showDetail = false"', $html);
        $this->assertStringContainsString('absolute inset-0 bg-black/35" x-on:click="$wire.showDetail = false"', $html);
        $this->assertMatchesRegularExpression('#<button type="button" class="[^"]*min-h-11[^"]*" x-on:click="\$wire.showDetail = false">閉じる</button>#u', $html);
        $this->assertStringContainsString('max-h-full w-full max-w-[400px] overflow-y-auto rounded-[30px]', $html);
        $this->assertStringContainsString('text-base leading-5 font-bold', $html);
        $this->assertStringContainsString('mt-2 text-sm break-words', $html);
    }

    /**
     * 予約の詳細に管理番号・予約者名を渡しても、マイページの予約履歴の詳細の表示は変わらない
     */
    #[Test]
    public function マイページの予約履歴の詳細に管理番号と予約者は出ない(): void
    {
        $user = $this->makeUser('山田', '太郎');
        $reservation = $this->makeAdminReservation($user);

        $detail = app(AdminReservationService::class)->findDetail($reservation->id);
        $this->assertSame($reservation->id, $detail->orderId);
        $this->assertSame('山田 太郎', $detail->ordererName);

        $this->actingAs($user);
        Livewire::test(MyPageReservationHistory::class)
            ->call('openDetail', $reservation->id)
            ->assertSee('商品内訳')
            ->assertDontSee('予約管理番号')
            ->assertDontSee('予約者');
    }
}
