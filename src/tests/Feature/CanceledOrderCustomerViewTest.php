<?php

namespace Tests\Feature;

use App\Enums\AdminListType;
use App\Livewire\MyPagePurchaseHistory;
use App\Livewire\MyPageReservationHistory;
use App\Mail\PasswordResetMail;
use App\Mail\PurchaseThanksMail;
use App\Mail\ReservationThanksMail;
use App\Mail\ThanksMail;
use App\Models\Purchase_history;
use App\Models\User;
use App\Repositories\Interfaces\PurchaseHistoryRepositoryInterface;
use App\Repositories\Interfaces\ReservationRepositoryInterface;
use App\Support\OrderCancelGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\Concerns\MyPageTestHelpers;
use Tests\TestCase;

/**
 * 取消済みの注文・予約が、お客様の画面（マイページ・サンクスメール・特定商取引法の表記）でどう見えるかのテスト
 */
class CanceledOrderCustomerViewTest extends TestCase
{
    use MyPageTestHelpers;
    use RefreshDatabase;

    /** 注文の案内文（AC-24） */
    private const string PURCHASE_GUIDE = 'ご注文の取り消しをご希望の場合は、お問い合わせフォームから、注文日とご注文者のお名前を添えてご連絡ください。発送後の取り消しはお受けできません。';

    /** 予約の案内文（AC-24） */
    private const string RESERVATION_GUIDE = 'ご予約の取り消しをご希望の場合は、お問い合わせフォームから、予約日とご予約者のお名前を添えてご連絡ください。発送後の取り消しはお受けできません。';

    /**
     * 表示された HTML から、リンクのタグを除いた文章を取り出す
     */
    private function plain(Testable $component): string
    {
        return html_entity_decode(strip_tags($component->html()), ENT_QUOTES);
    }

    /**
     * 文章の空白をそろえる
     */
    private function squish(string $text): string
    {
        return (string) preg_replace('/\s+/u', '', $text);
    }

    /**
     * 履歴の一覧に取消済みも残り、日付の下に「取消済」が出る。取消のない行には出ない（AC-20）
     */
    #[Test]
    public function 購入履歴の一覧に取消済みも残り取消済と表示される(): void
    {
        $user = User::factory()->create();
        $canceled = $this->makePurchase($user, overrides: ['canceled_at' => '2026-11-05 10:00:00', 'created_at' => '2026-11-04 09:00:00']);
        $normal = $this->makePurchase($user, overrides: ['created_at' => '2026-11-03 09:00:00']);

        $component = Livewire::actingAs($user)->test(MyPagePurchaseHistory::class);
        $html = $component->html();

        $this->assertSame(1, substr_count($html, '取消済'));
        $this->assertMatchesRegularExpression('#2026/11/04\s*(?:<!--.*?-->)?\s*<span class="block font-bold text-muted">取消済</span>#us', $html);
        $component->assertSee('aria-label="注文日2026/11/04の詳細を見る"', false)->assertSee('aria-label="注文日2026/11/03の詳細を見る"', false);
        $this->assertSame(2, Purchase_history::where('user_id', $user->id)->count());
        $this->assertNotSame($canceled->id, $normal->id);
    }

    /**
     * 予約履歴も同じ
     */
    #[Test]
    public function 予約履歴の一覧に取消済みも残り取消済と表示される(): void
    {
        $user = User::factory()->create();
        $this->makeReservation($user, overrides: ['canceled_at' => '2026-11-05 10:00:00', 'created_at' => '2026-11-04 09:00:00']);
        $this->makeReservation($user, overrides: ['created_at' => '2026-11-03 09:00:00']);

        $html = Livewire::actingAs($user)->test(MyPageReservationHistory::class)->html();

        $this->assertSame(1, substr_count($html, '取消済'));
        $this->assertMatchesRegularExpression('#2026/11/04\s*(?:<!--.*?-->)?\s*<span class="block font-bold text-muted">取消済</span>#us', $html);
    }

    /**
     * 取消済みの詳細に「取消済」が出て、銀行振込の振込先は出ない。取消のない注文は変わらない（AC-20）
     */
    #[Test]
    public function 取消済みの銀行振込の詳細は取消済と出て振込先は出ない(): void
    {
        $user = User::factory()->create();
        $canceled = $this->makePurchase($user, method: 'customer_balance', overrides: ['canceled_at' => '2026-11-05 10:00:00']);
        $this->attachPurchaseBankTransfer($canceled);
        $paid = $this->makePurchase($user, method: 'customer_balance', overrides: ['canceled_at' => '2026-11-05 10:00:00']);
        $this->attachPurchaseBankTransfer($paid, paid: true);
        $normal = $this->makePurchase($user, method: 'customer_balance');
        $this->attachPurchaseBankTransfer($normal);

        foreach ([$canceled, $paid] as $order) {
            Livewire::actingAs($user)->test(MyPagePurchaseHistory::class)->call('openDetail', $order->id)
                ->assertSee('取消済')
                ->assertSee('銀行振込')
                ->assertDontSee('お振込先')
                ->assertDontSee('銀行名')
                ->assertDontSee('いろは銀行')
                ->assertDontSee('お客様の残高からお支払いが完了しています');
        }

        Livewire::actingAs($user)->test(MyPagePurchaseHistory::class)->call('openDetail', $normal->id)
            ->assertSee('お振込先')
            ->assertSee('銀行名：いろは銀行')
            ->assertDontSee('<dt class="font-maru text-base leading-5 font-medium text-ink">状況</dt>', false);
    }

    /**
     * 予約の詳細も同じ
     */
    #[Test]
    public function 取消済みの銀行振込の予約の詳細は取消済と出て振込先は出ない(): void
    {
        $user = User::factory()->create();
        $canceled = $this->makeReservation($user, 'customer_balance', ['canceled_at' => '2026-11-05 10:00:00']);
        $this->attachReservationBankTransfer($canceled);
        $normal = $this->makeReservation($user, 'customer_balance');
        $this->attachReservationBankTransfer($normal);

        Livewire::actingAs($user)->test(MyPageReservationHistory::class)->call('openDetail', $canceled->id)
            ->assertSee('取消済')
            ->assertDontSee('お振込先')
            ->assertDontSee('いろは銀行');
        Livewire::actingAs($user)->test(MyPageReservationHistory::class)->call('openDetail', $normal->id)
            ->assertSee('お振込先')
            ->assertDontSee('<dt class="font-maru text-base leading-5 font-medium text-ink">状況</dt>', false);
    }

    /**
     * 未発送で未取消の注文の詳細に、「閉じる」の上に取消の案内文が出る。「お問い合わせフォーム」は /inquiry へのリンク（AC-24）
     */
    #[Test]
    public function 未発送の注文の詳細に取消の案内が出る(): void
    {
        $user = User::factory()->create();
        $order = $this->makePurchase($user);

        $component = Livewire::actingAs($user)->test(MyPagePurchaseHistory::class)->call('openDetail', $order->id);
        $html = $component->html();

        $this->assertStringContainsString($this->squish(self::PURCHASE_GUIDE), $this->squish($this->plain($component)));
        $this->assertMatchesRegularExpression('#ご注文の取り消しをご希望の場合は、<a [^>]*href="'.preg_quote(route('top.inquiry'), '#').'"[^>]*>お問い合わせフォーム</a>から、注文日とご注文者のお名前を添えてご連絡ください。発送後の取り消しはお受けできません。#u', $html);
        // 「閉じる」の上
        $this->assertLessThan(strpos($html, '>閉じる</button>'), strpos($html, 'ご注文の取り消しをご希望'));
        $component->assertDontSee('ご予約の取り消し');
    }

    /**
     * 未発送で未取消の予約は、予約の案内文が出る（AC-24）
     */
    #[Test]
    public function 未発送の予約の詳細に取消の案内が出る(): void
    {
        $user = User::factory()->create();
        $reservation = $this->makeReservation($user);

        $component = Livewire::actingAs($user)->test(MyPageReservationHistory::class)->call('openDetail', $reservation->id);

        $this->assertStringContainsString($this->squish(self::RESERVATION_GUIDE), $this->squish($this->plain($component)));
        $this->assertMatchesRegularExpression('#<a [^>]*href="'.preg_quote(route('top.inquiry'), '#').'"[^>]*>お問い合わせフォーム</a>から、予約日とご予約者のお名前を添えて#u', $component->html());
        $this->assertLessThan(strpos($component->html(), '>閉じる</button>'), strpos($component->html(), 'ご予約の取り消しをご希望'));
        $component->assertDontSee('ご注文の取り消し');
    }

    /**
     * 発送済み・取消済みの詳細には案内文が出ない（AC-24）
     */
    #[Test]
    public function 発送済みと取消済みの詳細には取消の案内が出ない(): void
    {
        $user = User::factory()->create();
        $purchases = [
            $this->makePurchase($user, overrides: ['shipped' => true]),
            $this->makePurchase($user, overrides: ['canceled_at' => '2026-11-05 10:00:00']),
        ];
        $reservations = [
            $this->makeReservation($user, overrides: ['shipped' => true]),
            $this->makeReservation($user, overrides: ['canceled_at' => '2026-11-05 10:00:00']),
        ];

        foreach ($purchases as $order) {
            Livewire::actingAs($user)->test(MyPagePurchaseHistory::class)->call('openDetail', $order->id)
                ->assertSet('showDetail', true)
                ->assertDontSee('取り消しをご希望')
                ->assertDontSee('お問い合わせフォーム');
        }
        foreach ($reservations as $reservation) {
            Livewire::actingAs($user)->test(MyPageReservationHistory::class)->call('openDetail', $reservation->id)
                ->assertSet('showDetail', true)
                ->assertDontSee('取り消しをご希望')
                ->assertDontSee('お問い合わせフォーム');
        }
    }

    /**
     * サンクスメールの案内文を確かめる（HTML版は「お問い合わせフォーム」が /inquiry へのリンク、テキスト版は1つの文）
     */
    private function assertMailGuide(ThanksMail $mail, string $before, string $after): void
    {
        $mail->assertSeeInHtml($before, false);
        $mail->assertSeeInHtml($after, false);
        $this->assertMatchesRegularExpression('#'.preg_quote($before, '#').'<a href="'.preg_quote(route('top.inquiry'), '#').'"[^>]*>お問い合わせフォーム</a>'.preg_quote($after, '#').'#u', $this->renderHtml($mail));
        $mail->assertSeeInText($before.'お問い合わせフォーム'.$after, false);
    }

    /**
     * メールの HTML 版を取り出す
     */
    private function renderHtml(ThanksMail $mail): string
    {
        return (new ReflectionMethod($mail, 'renderForAssertions'))->invoke($mail)[0];
    }

    /**
     * 案内文の部品：リンクの前後に分けて返し、つなげると決められた文になる
     */
    #[Test]
    public function 案内文はリンクの前後に分けて返される(): void
    {
        foreach ([[AdminListType::Purchase, self::PURCHASE_GUIDE], [AdminListType::Reservation, self::RESERVATION_GUIDE]] as [$type, $expected]) {
            $parts = OrderCancelGuide::parts($type);

            $this->assertSame($expected, $parts['before'].OrderCancelGuide::LINK_LABEL.$parts['after']);
        }
    }

    /**
     * 購入のサンクスメールに、案内文（お問い合わせフォームへのリンクつき）が入る。取り消せるとは書かず、発送後は受けられないと書く（AC-25）
     */
    #[Test]
    public function 購入のサンクスメールに取消の案内が入る(): void
    {
        $purchase = $this->makePurchase(User::factory()->create());
        $mail = new PurchaseThanksMail(app(PurchaseHistoryRepositoryInterface::class)->loadForThanksMail($purchase));

        $this->assertMailGuide($mail, 'ご注文の取り消しをご希望の場合は、', 'から、注文日とご注文者のお名前を添えてご連絡ください。発送後の取り消しはお受けできません。');
        $mail->assertDontSeeInHtml('ご予約の取り消し', false);
    }

    /**
     * 予約のサンクスメールにも、予約の案内文が入る（AC-25）
     */
    #[Test]
    public function 予約のサンクスメールに取消の案内が入る(): void
    {
        $reservation = $this->makeReservation(User::factory()->create());
        $mail = new ReservationThanksMail(app(ReservationRepositoryInterface::class)->loadForThanksMail($reservation));

        $this->assertMailGuide($mail, 'ご予約の取り消しをご希望の場合は、', 'から、予約日とご予約者のお名前を添えてご連絡ください。発送後の取り消しはお受けできません。');
        $mail->assertDontSeeInHtml('ご注文の取り消し', false);
    }

    /**
     * パスワード再設定のメールには、取消の案内は入らない（AC-25）
     */
    #[Test]
    public function パスワード再設定のメールに取消の案内は入らない(): void
    {
        $user = User::factory()->create();
        $mail = new PasswordResetMail($user, 'test-token');

        $mail->assertDontSeeInHtml('取り消し', false);
        $mail->assertDontSeeInText('取り消し', false);
    }

    /**
     * 特定商取引法の表記の「返品について」の近くに、取消の案内が出る。「お問い合わせフォーム」は /inquiry へのリンク（AC-26）
     */
    #[Test]
    public function 特商法の返品の近くに取消の案内が出る(): void
    {
        $html = $this->get(route('info.commercial-transaction'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#ご注文・ご予約の取り消しをご希望の場合は、発送前に<a [^>]*href="'.preg_quote(route('top.inquiry'), '#').'"[^>]*>お問い合わせフォーム</a>からご連絡ください。発送後の取り消しはお受けできません。#u', $html);
        // 「返品について」のすぐあと
        $this->assertLessThan(strpos($html, 'ご注文・ご予約の取り消し'), strpos($html, '返品について'));
        $this->assertLessThan(800, strpos($html, 'ご注文・ご予約の取り消し') - strpos($html, '返品について'));
    }
}
