<?php

namespace Tests\Feature;

use App\Enums\AdminListType;
use App\Livewire\AdminPurchaseDetail;
use App\Livewire\AdminReservationDetail;
use App\Models\Purchase_history;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Constraints\SeeInOrder;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の注文・予約の詳細モーダルの「取り消す」と確認、取消済みの表示のテスト
 */
class AdminOrderCancelDetailTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 注文・予約の両方で確かめるときのデータ
     *
     * @return array<string, array{AdminListType}>
     */
    public static function types(): array
    {
        return ['注文' => [AdminListType::Purchase], '予約' => [AdminListType::Reservation]];
    }

    /**
     * 注文または予約を作る
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeTarget(AdminListType $type, string $method = 'card', array $overrides = []): Purchase_history|Reservation
    {
        return match ($type) {
            AdminListType::Purchase => $this->makeOrder(method: $method, overrides: $overrides),
            AdminListType::Reservation => $this->makeAdminReservation(method: $method, overrides: $overrides),
        };
    }

    /**
     * 一覧の「詳細」ボタンと同じイベントで詳細を開く
     */
    private function openFor(AdminListType $type, Purchase_history|Reservation $target): Testable
    {
        $component = match ($type) {
            AdminListType::Purchase => Livewire::test(AdminPurchaseDetail::class),
            AdminListType::Reservation => Livewire::test(AdminReservationDetail::class),
        };

        return $component->dispatch($type->detailEvent(), id: $target->id);
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
     * 未発送で未取消の詳細に、赤い「取り消す」と確認のモーダルが出る。確認は会員の物理削除と同じ形（AC-1, AC-3）
     */
    #[Test]
    #[DataProvider('types')]
    public function 未発送の詳細に赤い取り消すと確認が出る(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeTarget($type);
        $subject = $type->subject();

        $component = $this->openFor($type, $target)
            ->assertSee("{$subject}を取り消します。よろしいですか？")
            ->assertSee('取り消すと元に戻せません。');
        $html = $component->html();

        $this->assertTextInOrder($component, ['支払い方法', '取り消す', '閉じる']);
        // 詳細の中の赤い「取り消す」（確認を開く）
        $this->assertMatchesRegularExpression('#<button type="button"[^>]*bg-delete[^>]*aria-haspopup="dialog"[^>]*x-on:click="canceling = false; confirming = true">取り消す</button>#u', $html);
        // 確認の「取り消す」は、IDを取消のルートへ POST するフォーム
        $this->assertStringContainsString('action="'.route($type->cancelRoute()).'"', $html);
        $this->assertStringContainsString('<input type="hidden" name="id" value="'.$target->id.'">', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringNotContainsString('name="_method"', $html);
        $this->assertMatchesRegularExpression('#<button type="submit"[^>]*bg-delete[^>]*>取り消す</button>#u', $html);
        // 閉じるボタンは「キャンセル」ではなく「戻る」（最初のフォーカス）。確認だけを閉じる
        $this->assertMatchesRegularExpression('#<button type="button"[^>]*autofocus[^>]*x-on:click="confirming = false">戻る</button>#u', $html);
        $this->assertStringNotContainsString('キャンセル', $html);
        // フォーカスを閉じ込め、Esc・背景・「閉じる」は確認も閉じる
        $this->assertStringContainsString('x-trap="confirming"', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window="if (confirming) { confirming = false } else { $wire.showDetail = false }"', $html);
        // 送信中は押せない
        $this->assertStringContainsString('x-bind:disabled="canceling"', $html);
        // 管理画面用のボタン（SPは文字16px・高さ44px、PCは14px・30px）。マイページ用の font-maru は使わない
        $this->assertSame(3, substr_count($html, 'lg:h-[30px] lg:text-sm'));
        $this->assertStringNotContainsString('font-maru', $html);
        // 閉じていても確認は DOM にあるが、Alpine の状態で隠れる
        $this->assertStringContainsString('x-show="confirming"', $html);
    }

    /**
     * 確認の文言は、予約では「予約を取り消します。よろしいですか？」になる（AC-3）
     */
    #[Test]
    public function 予約の確認は予約の語になる(): void
    {
        $this->loginAsAdmin();
        $reservation = $this->makeTarget(AdminListType::Reservation);

        $this->openFor(AdminListType::Reservation, $reservation)
            ->assertSee('予約を取り消します。よろしいですか？')
            ->assertDontSee('注文を取り消します');
    }

    /**
     * 確認の返金の案内は支払方法で変わる。カードは「返金はStripeの管理画面で行ってください。」、銀行振込は「入金済みの場合、〜」、代金引換は案内なし（AC-4）
     */
    #[Test]
    public function 返金の案内は支払方法で変わる(): void
    {
        $this->loginAsAdmin();
        $cardNote = '<p>返金はStripeの管理画面で行ってください。</p>';
        $bankNote = '<p>入金済みの場合、返金はStripeの管理画面で行ってください。</p>';

        $cardHtml = $this->openFor(AdminListType::Purchase, $this->makeTarget(AdminListType::Purchase, 'card'))->html();
        $this->assertStringContainsString($cardNote, $cardHtml);
        $this->assertStringNotContainsString('入金済みの場合', $cardHtml);

        $bank = $this->makeTarget(AdminListType::Purchase, 'customer_balance');
        $this->attachPurchaseBankTransfer($bank);
        $bankHtml = $this->openFor(AdminListType::Purchase, $bank)->html();
        $this->assertStringContainsString($bankNote, $bankHtml);
        $this->assertStringNotContainsString($cardNote, $bankHtml);

        $codHtml = $this->openFor(AdminListType::Purchase, $this->makeTarget(AdminListType::Purchase, 'cod'))->html();
        $this->assertStringNotContainsString('返金は', $codHtml);
        $this->assertStringContainsString('取り消すと元に戻せません。', $codHtml);

        // 予約もカード・銀行振込で同じ
        $reservationCard = $this->openFor(AdminListType::Reservation, $this->makeTarget(AdminListType::Reservation, 'card'))->html();
        $this->assertStringContainsString($cardNote, $reservationCard);
        $reservationBank = $this->makeTarget(AdminListType::Reservation, 'customer_balance');
        $this->attachReservationBankTransfer($reservationBank);
        $this->assertStringContainsString($bankNote, $this->openFor(AdminListType::Reservation, $reservationBank)->html());
    }

    /**
     * 発送済みの詳細には「取り消す」も確認も出ない（AC-2）
     */
    #[Test]
    #[DataProvider('types')]
    public function 発送済みの詳細には取り消すが出ない(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeTarget($type, overrides: ['shipped' => true]);

        $component = $this->openFor($type, $target)->assertSet('showDetail', true);

        $component->assertDontSee('取り消す')->assertDontSee('取消日')->assertDontSee('<form', false);
    }

    /**
     * 取消済みの詳細には「取り消す」が出ない。先頭に「取消日」（YYYY/MM/DD）が出て、ほかの項目は取消の前と同じ（AC-2, AC-14）
     */
    #[Test]
    #[DataProvider('types')]
    public function 取消済みの詳細は先頭に取消日が出て取り消すは出ない(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeTarget($type, overrides: ['canceled_at' => '2026-11-05 23:30:00', 'created_at' => '2026-11-01 09:00:00']);
        $subject = $type->subject();

        $component = $this->openFor($type, $target);

        $component->assertDontSee('取り消す')->assertDontSee('<form', false);
        $this->assertTextInOrder($component, ['取消日', '2026/11/05', "{$subject}管理番号", (string) $target->id, "{$subject}日", '2026/11/01', "{$subject}者", '商品内訳', '送料', '合計金額', '配送先氏名', '配送先住所', '配送先電話番号', '支払い方法', '閉じる']);
    }

    /**
     * 取消済みの銀行振込は、支払い状況を表示しない（振込先の行があっても、顧客残高で支払い済みでも）（AC-14）
     */
    #[Test]
    #[DataProvider('types')]
    public function 取消済みの銀行振込は支払い状況を表示しない(AdminListType $type): void
    {
        $this->loginAsAdmin();

        foreach ([false, true] as $paid) {
            $target = $this->makeTarget($type, 'customer_balance', ['canceled_at' => '2026-11-05 10:00:00']);
            match ($type) {
                AdminListType::Purchase => $this->attachPurchaseBankTransfer($target, $paid),
                AdminListType::Reservation => $this->attachReservationBankTransfer($target, $paid),
            };

            $component = $this->openFor($type, $target);

            $this->assertTextInOrder($component, ['支払い方法', '銀行振込']);
            $component->assertDontSee('お支払いの状況')->assertDontSee('ご案内済み')->assertDontSee('お支払い済み');
        }
    }

    /**
     * 一覧で発送済みにチェックしたあとに詳細を開いても、「取り消す」は出ない（詳細は開くたびにDBから読み直す）（AC-2）
     */
    #[Test]
    #[DataProvider('types')]
    public function 発送済みにチェックしたあとに開いても取り消すは出ない(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeTarget($type);

        // 最初は出る。一覧のチェックと同じリクエストで発送済みにしたあと、同じコンポーネントで開き直す
        $component = $this->openFor($type, $target)->assertSee('取り消す');
        $this->postJson(route($type->updateRoute()), ['id' => $target->id, 'shipped' => true])->assertOk();

        $component->dispatch($type->detailEvent(), id: $target->id)->assertSet('showDetail', true)->assertDontSee('取り消す');
    }

    /**
     * 取り消したあとに同じコンポーネントで開き直すと、取消済みとして表示される
     */
    #[Test]
    #[DataProvider('types')]
    public function 取り消したあとに開くと取消済みとして表示される(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeTarget($type);
        $component = $this->openFor($type, $target)->assertSee('取り消す');
        $this->post(route($type->cancelRoute()), ['id' => $target->id])->assertSessionHas('list_status');

        $component->dispatch($type->detailEvent(), id: $target->id)->assertSee('取消日')->assertDontSee('取り消す');
    }
}
