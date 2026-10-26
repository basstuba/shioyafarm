<?php

namespace Tests\Feature;

use App\Livewire\MyPageAddressList;
use App\Livewire\MyPagePurchaseHistory;
use App\Livewire\MyPageReservationHistory;
use App\Models\Delivery_address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Constraints\SeeInOrder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\MyPageTestHelpers;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * マイページの Livewire コンポーネント（お届け先住所一覧・購入履歴・予約履歴）のテスト
 */
class MyPageLivewireTest extends TestCase
{
    use MyPageTestHelpers;
    use PurchaseTestHelpers;
    use RefreshDatabase;

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
     * 配送先住所を n 件作る（「テスト市1」〜。作った順＝新しい順の逆）
     */
    private function makeAddresses(User $user, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Delivery_address::factory()->create(['user_id' => $user->id, 'delivery_prefecture' => '東京都', 'delivery_city' => "テスト市{$i}"]);
        }
    }

    // ---- お届け先住所一覧 ----

    /**
     * 住所が6件あると、1ページ目に新しい順の5件、2ページ目に最も古い1件が表示される
     */
    #[Test]
    public function 住所は新しい順に5件ずつ表示される(): void
    {
        $user = User::factory()->create();
        $this->makeAddresses($user, 6);

        Livewire::actingAs($user)
            ->test(MyPageAddressList::class)
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['東京都テスト市6', '東京都テスト市5', '東京都テスト市4', '東京都テスト市3', '東京都テスト市2']))
            ->assertDontSee('テスト市1')
            ->assertSee('aria-label="ページ送り"', false)
            ->call('gotoPage', 2, MyPageAddressList::PAGE_NAME)
            ->assertSee('東京都テスト市1')
            ->assertDontSee('テスト市6')
            ->call('previousPage', MyPageAddressList::PAGE_NAME)
            ->assertSee('東京都テスト市6')
            ->assertDontSee('テスト市1');
    }

    /**
     * 5件以下ならページ送りは出ない。ちょうど5件でも出ない
     */
    #[Test]
    public function 住所が5件以下のときはページ送りが出ない(): void
    {
        $user = User::factory()->create();
        $this->makeAddresses($user, 5);

        Livewire::actingAs($user)
            ->test(MyPageAddressList::class)
            ->assertSee('東京都テスト市1')
            ->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 0件のときは「登録されているお届け先住所はありません」を表示する
     */
    #[Test]
    public function 住所が0件のときはないことを表示する(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(MyPageAddressList::class)
            ->assertSee('登録されているお届け先住所はありません')
            ->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 自分が追加した住所だけを表示する（会員登録の住所・ほかの会員の住所は含めない）
     */
    #[Test]
    public function 住所は自分が追加したものだけが表示される(): void
    {
        $user = User::factory()->create(['prefecture' => '北海道', 'city' => '登録済みの住所9-9']);
        $other = User::factory()->create();
        $this->makeAddresses($user, 1);
        Delivery_address::factory()->create(['user_id' => $other->id, 'delivery_city' => '他人の住所']);

        Livewire::actingAs($user)
            ->test(MyPageAddressList::class)
            ->assertSee('東京都テスト市1')
            ->assertDontSee('登録済みの住所9-9')
            ->assertDontSee('他人の住所');
    }

    /**
     * 住所を追加するとモーダルが閉じ、2ページ目から1ページ目に戻って先頭に表示される
     */
    #[Test]
    public function 住所を追加すると1ページ目の先頭に表示される(): void
    {
        $this->makeAreas();
        $user = User::factory()->create();
        $this->makeAddresses($user, 6);

        Livewire::actingAs($user)
            ->test(MyPageAddressList::class)
            ->call('gotoPage', 2, MyPageAddressList::PAGE_NAME)
            ->call('openAddressModal')
            ->assertSet('showAddressModal', true)
            ->set('delivery_prefecture', '沖縄県')
            ->set('delivery_city', 'UAT那覇市1-1')
            ->call('addAddress')
            ->assertHasNoErrors()
            ->assertSet('showAddressModal', false)
            ->assertSet('delivery_city', '')
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['沖縄県UAT那覇市1-1', '東京都テスト市6']))
            ->assertDontSee('テスト市1');

        $this->assertDatabaseHas('delivery_addresses', ['user_id' => $user->id, 'delivery_prefecture' => '沖縄県', 'delivery_city' => 'UAT那覇市1-1']);
    }

    /**
     * 都道府県未選択・住所が空・256文字以上はエラーになり、追加されない
     */
    #[Test]
    public function 住所の追加は入力が正しくないとエラーになる(): void
    {
        $this->makeAreas();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(MyPageAddressList::class)->call('openAddressModal');

        $component->call('addAddress')
            ->assertHasErrors(['delivery_prefecture' => 'required', 'delivery_city' => 'required'])
            ->assertSee('都道府県を選択してください')
            ->assertSee('住所を入力してください')
            ->assertSet('showAddressModal', true);

        $component->set('delivery_prefecture', '沖縄県')
            ->set('delivery_city', str_repeat('あ', 256))
            ->call('addAddress')
            ->assertHasErrors(['delivery_city' => 'max'])
            ->assertSee('住所は255文字以内で入力してください');

        $component->set('delivery_prefecture', 'ない県')
            ->set('delivery_city', '住所')
            ->call('addAddress')
            ->assertHasErrors(['delivery_prefecture' => 'exists']);

        $this->assertSame(0, Delivery_address::count());
    }

    /**
     * モーダルを開き直すと、前回の入力とエラーは消える
     */
    #[Test]
    public function 住所追加モーダルを開き直すと入力とエラーが消える(): void
    {
        $this->makeAreas();

        Livewire::actingAs(User::factory()->create())
            ->test(MyPageAddressList::class)
            ->call('addAddress')
            ->assertHasErrors()
            ->set('delivery_city', '入力途中')
            ->call('openAddressModal')
            ->assertHasNoErrors()
            ->assertSet('delivery_city', '');
    }

    // ---- 購入履歴 ----

    /**
     * 購入履歴は自分の注文だけが新しい順に5件ずつ表示され、ページ送りで切り替わる
     */
    #[Test]
    public function 購入履歴は自分の注文だけが新しい順に5件ずつ表示される(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        for ($i = 1; $i <= 6; $i++) {
            $this->makePurchase($user, [["自分の品{$i}", 2, 100 * $i, 1]]);
        }
        $this->makePurchase($other, [['他人の品', 2, 100, 1]]);

        Livewire::actingAs($user)
            ->test(MyPagePurchaseHistory::class)
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['自分の品6', '自分の品5', '自分の品4', '自分の品3', '自分の品2']))
            ->assertDontSee('自分の品1')
            ->assertDontSee('他人の品')
            ->assertSee('aria-label="ページ送り"', false)
            ->call('gotoPage', 2, MyPagePurchaseHistory::PAGE_NAME)
            ->assertSee('自分の品1 2kg')
            ->assertDontSee('自分の品6')
            ->assertDontSee('他人の品');
    }

    /**
     * 注文日は Y/m/d、合計金額は送料・代引手数料込みのカンマ区切り、商品が複数の注文は1行ずつ全商品を表示する
     */
    #[Test]
    public function 購入履歴に日付と合計金額と全商品名が表示される(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user, [['じゃがいも', 2, 330, 2], ['玉ねぎ', 5, 1500, 1], ['にんじん', 2, 418, 1]], 'cod');
        $purchase->forceFill(['created_at' => '2025-09-02 10:00:00'])->save();
        $total = 330 * 2 + 1500 + 418 + 940 + (int) config('shipping.cod_fee');

        Livewire::actingAs($user)
            ->test(MyPagePurchaseHistory::class)
            ->assertSee('2025/09/02')
            ->assertSeeHtmlInOrder(['<p>じゃがいも 2kg</p>', '<p>玉ねぎ 5kg</p>', '<p>にんじん 2kg</p>'])
            ->assertDontSee('他')
            ->assertSee(number_format($total).'円')
            ->assertSee('注文日')
            ->assertSee('合計金額')
            ->assertSee('詳細');
    }

    /**
     * 購入履歴が0件のときは「購入履歴はありません」を表示し、ページ送りは出ない
     */
    #[Test]
    public function 購入履歴が0件のときはないことを表示する(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(MyPagePurchaseHistory::class)
            ->assertSee('購入履歴はありません')
            ->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 購入履歴が5件のときはページ送りが出ない
     */
    #[Test]
    public function 購入履歴が5件のときはページ送りが出ない(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->makePurchase($user);
        }

        Livewire::actingAs($user)
            ->test(MyPagePurchaseHistory::class)
            ->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * クレジットカードの詳細：購入日・商品内訳・送料・合計・お届け先・お支払い方法。代引手数料とお振込先は出ない
     */
    #[Test]
    public function クレジットカードの購入の詳細が表示される(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user, [['じゃがいも', 2, 330, 2], ['玉ねぎ', 5, 1500, 1]], 'card', [
            'delivery_name' => '塩谷花子',
            'delivery_prefecture' => '沖縄県',
            'delivery_city' => '那覇市1-2-3',
            'delivery_tell' => '08011112222',
        ]);
        $purchase->forceFill(['created_at' => '2025-09-02 10:00:00'])->save();

        Livewire::actingAs($user)
            ->test(MyPagePurchaseHistory::class)
            ->assertSet('showDetail', false)
            ->assertDontSee('お届け先')
            ->call('openDetail', $purchase->id)
            ->assertSet('showDetail', true)
            ->assertSet('detailId', $purchase->id)
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['購入日', '2025/09/02', '商品内訳', 'じゃがいも 2kg', '330円(税込)', '2個', '660円', '玉ねぎ 5kg', '1,500円(税込)', '1個', '1,500円']))
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['送料', '940円', '合計金額', number_format(660 + 1500 + 940).'円']))
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['お届け先', '塩谷花子 様', '沖縄県那覇市1-2-3', '電話番号：08011112222']))
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['お支払い方法', 'クレジットカード']))
            ->assertDontSee('代引手数料')
            ->assertDontSee('お振込先');
    }

    /**
     * 代金引換の詳細：代引手数料が表示され、商品小計 + 送料 + 代引手数料 = 合計金額
     */
    #[Test]
    public function 代金引換の購入の詳細に代引手数料が表示される(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user, [['じゃがいも', 2, 330, 1]], 'cod');
        $codFee = (int) config('shipping.cod_fee');

        Livewire::actingAs($user)
            ->test(MyPagePurchaseHistory::class)
            ->call('openDetail', $purchase->id)
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['送料', '940円', '代引手数料', number_format($codFee).'円', '合計金額', number_format(330 + 940 + $codFee).'円']))
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['お支払い方法', '代金引換']))
            ->assertDontSee('お振込先');
    }

    /**
     * 銀行振込の詳細：お振込先（銀行名・支店名・口座種別・口座番号・口座名義・お振込金額）が表示される
     */
    #[Test]
    public function 銀行振込の購入の詳細にお振込先が表示される(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user, [['じゃがいも', 2, 330, 1]], 'customer_balance');
        $this->attachPurchaseBankTransfer($purchase);

        Livewire::actingAs($user)
            ->test(MyPagePurchaseHistory::class)
            ->call('openDetail', $purchase->id)
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['お支払い方法', '銀行振込']))
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['お振込先', '銀行名：いろは銀行', '支店名：中央支店', '口座種別：普通', '口座番号：1234567', '口座名義：ストライプジャパン', 'お振込金額：'.number_format($purchase->total_price).'円']))
            ->assertDontSee('代引手数料')
            ->assertDontSee('お客様の残高からお支払いが完了しています');
    }

    /**
     * 顧客残高で支払い済みの銀行振込は、振込先ではなく支払い済みの案内を表示する
     */
    #[Test]
    public function 残高で支払い済みの銀行振込は支払い完了の案内が表示される(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user, [['じゃがいも', 2, 330, 1]], 'customer_balance');
        $this->attachPurchaseBankTransfer($purchase, paid: true);

        Livewire::actingAs($user)
            ->test(MyPagePurchaseHistory::class)
            ->call('openDetail', $purchase->id)
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['お振込先', 'お客様の残高からお支払いが完了しています']))
            ->assertDontSee('銀行名：')
            ->assertDontSee('口座番号：');
    }

    /**
     * ほかの会員の購入履歴のIDでは、詳細は開かず、内容も表示されない
     */
    #[Test]
    public function ほかの会員の購入履歴の詳細は開かない(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherPurchase = $this->makePurchase($other, [['他人の大根', 5, 777, 1]], 'card', ['delivery_name' => '他人の太郎']);

        Livewire::actingAs($user)
            ->test(MyPagePurchaseHistory::class)
            ->call('openDetail', $otherPurchase->id)
            ->assertSet('showDetail', false)
            ->assertSet('detailId', null)
            ->assertDontSee('他人の大根')
            ->assertDontSee('他人の太郎');
    }

    /**
     * 自分の詳細を開いたあとに、ほかの会員のIDを送っても、詳細は閉じて何も開かない
     */
    #[Test]
    public function 自分の詳細を開いたあとにほかの会員の_i_dを送ると閉じる(): void
    {
        $user = User::factory()->create();
        $mine = $this->makePurchase($user, [['自分の品', 2, 100, 1]]);
        $otherPurchase = $this->makePurchase(User::factory()->create(), [['他人の大根', 5, 777, 1]]);

        Livewire::actingAs($user)
            ->test(MyPagePurchaseHistory::class)
            ->call('openDetail', $mine->id)
            ->assertSet('showDetail', true)
            ->call('openDetail', $otherPurchase->id)
            ->assertSet('showDetail', false)
            ->assertSet('detailId', null)
            ->assertDontSee('他人の大根');
    }

    /**
     * 存在しないIDでも何も開かず、エラーにもならない
     */
    #[Test]
    public function 存在しない購入履歴の_i_dでは何も開かない(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(MyPagePurchaseHistory::class)
            ->call('openDetail', 99999)
            ->assertSet('showDetail', false)
            ->assertSet('detailId', null);
    }

    /**
     * 詳細を閉じると、モーダルの内容は表示されなくなる
     */
    #[Test]
    public function 詳細を閉じると内容が表示されなくなる(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user, [['じゃがいも', 2, 330, 1]]);

        Livewire::actingAs($user)
            ->test(MyPagePurchaseHistory::class)
            ->call('openDetail', $purchase->id)
            ->assertSee('商品内訳')
            ->set('showDetail', false)
            ->assertSet('showDetail', false)
            ->assertDontSee('商品内訳');
    }

    /**
     * 詳細を開いている購入履歴のIDは、画面から書き換えられない（ほかの会員のIDを直接セットできない）
     */
    #[Test]
    public function 詳細の_i_dは書き換えられない(): void
    {
        $user = User::factory()->create();
        $otherPurchase = $this->makePurchase(User::factory()->create());

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($user)
            ->test(MyPagePurchaseHistory::class)
            ->set('detailId', $otherPurchase->id);
    }

    /**
     * 未ログインでは使えない
     */
    #[Test]
    public function 未ログインでは購入履歴を表示できない(): void
    {
        Livewire::test(MyPagePurchaseHistory::class)->assertUnauthorized();
    }

    /**
     * 履歴の問い合わせの数は、注文の数や明細の数に関わらず一定になる（Eager Loading）
     */
    #[Test]
    public function 購入履歴の問い合わせの数は注文の数に関わらず一定になる(): void
    {
        $few = User::factory()->create();
        $this->makePurchase($few, [['じゃがいも', 2, 330, 1]]);
        $many = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->makePurchase($many, [['じゃがいも', 2, 330, 1], ['玉ねぎ', 5, 1500, 1], ['にんじん', 2, 418, 1]]);
        }

        $count = function (User $user): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($user)->test(MyPagePurchaseHistory::class);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->assertSame($count($few), $count($many));
    }

    // ---- 予約履歴 ----

    /**
     * 予約履歴は自分の予約だけが新しい順に5件ずつ表示され、ページ送りで切り替わる
     */
    #[Test]
    public function 予約履歴は自分の予約だけが新しい順に5件ずつ表示される(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        for ($i = 1; $i <= 6; $i++) {
            $this->makeReservation($user, 'card', ['total_price' => 1000 + $i]);
        }
        $this->makeReservation($other, 'card', ['total_price' => 55555]);

        Livewire::actingAs($user)
            ->test(MyPageReservationHistory::class)
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['1,006円', '1,005円', '1,004円', '1,003円', '1,002円']))
            ->assertDontSee('1,001円')
            ->assertDontSee('55,555円')
            ->assertSee('予約日')
            ->assertSee('aria-label="ページ送り"', false)
            ->call('gotoPage', 2, MyPageReservationHistory::PAGE_NAME)
            ->assertSee('1,001円')
            ->assertDontSee('1,006円');
    }

    /**
     * 予約履歴が0件のときは「予約履歴はありません」を表示し、ページ送りは出ない
     */
    #[Test]
    public function 予約履歴が0件のときはないことを表示する(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(MyPageReservationHistory::class)
            ->assertSee('予約履歴はありません')
            ->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 予約の詳細：予約日・商品内訳・送料・合計・お届け先・お支払い方法。代引手数料・お振込先は出ない
     */
    #[Test]
    public function クレジットカードの予約の詳細が表示される(): void
    {
        $user = User::factory()->create();
        $reservation = $this->makeReservation($user, 'card', [
            'quantity' => 2,
            'price' => 1450,
            'subtotal' => 2900,
            'shipping_fee' => 1530,
            'total_price' => 4430,
            'delivery_name' => '塩谷花子',
            'delivery_prefecture' => '沖縄県',
            'delivery_city' => '那覇市1-2-3',
            'delivery_tell' => '08011112222',
        ]);
        $reservation->forceFill(['created_at' => '2025-09-02 10:00:00'])->save();

        Livewire::actingAs($user)
            ->test(MyPageReservationHistory::class)
            ->call('openDetail', $reservation->id)
            ->assertSet('showDetail', true)
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['予約日', '2025/09/02', '商品内訳', '北の恵みセット 10kg', '1,450円(税込)', '2個', '2,900円']))
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['送料', '1,530円', '合計金額', '4,430円']))
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['お届け先', '塩谷花子 様', '沖縄県那覇市1-2-3', '電話番号：08011112222']))
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['お支払い方法', 'クレジットカード']))
            ->assertDontSee('代引手数料')
            ->assertDontSee('お振込先');
    }

    /**
     * 銀行振込の予約の詳細にはお振込先、残高で支払い済みのときは支払い完了の案内が表示される
     */
    #[Test]
    public function 銀行振込の予約の詳細にお振込先が表示される(): void
    {
        $user = User::factory()->create();
        $transfer = $this->makeReservation($user, 'customer_balance');
        $this->attachReservationBankTransfer($transfer);
        $paid = $this->makeReservation($user, 'customer_balance');
        $this->attachReservationBankTransfer($paid, paid: true);

        $component = Livewire::actingAs($user)->test(MyPageReservationHistory::class);

        $component->call('openDetail', $transfer->id)
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['お支払い方法', '銀行振込']))
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['お振込先', '銀行名：いろは銀行', '支店名：中央支店', '口座種別：普通', '口座番号：1234567', '口座名義：ストライプジャパン', 'お振込金額：'.number_format($transfer->total_price).'円']))
            ->assertDontSee('お客様の残高からお支払いが完了しています');

        $component->call('openDetail', $paid->id)
            ->tap(fn (Testable $component) => $this->assertTextInOrder($component, ['お振込先', 'お客様の残高からお支払いが完了しています']))
            ->assertDontSee('銀行名：');
    }

    /**
     * ほかの会員の予約のIDでは、詳細は開かず、内容も表示されない
     */
    #[Test]
    public function ほかの会員の予約の詳細は開かない(): void
    {
        $user = User::factory()->create();
        $otherReservation = $this->makeReservation(User::factory()->create(), 'card', ['delivery_name' => '他人の太郎']);

        Livewire::actingAs($user)
            ->test(MyPageReservationHistory::class)
            ->call('openDetail', $otherReservation->id)
            ->assertSet('showDetail', false)
            ->assertSet('detailId', null)
            ->assertDontSee('他人の太郎');
    }

    /**
     * 予約の詳細のIDは、画面から書き換えられない
     */
    #[Test]
    public function 予約の詳細の_i_dは書き換えられない(): void
    {
        $otherReservation = $this->makeReservation(User::factory()->create());

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs(User::factory()->create())
            ->test(MyPageReservationHistory::class)
            ->set('detailId', $otherReservation->id);
    }

    /**
     * 予約履歴の問い合わせの数は、予約の数に関わらず一定になる（Eager Loading）
     */
    #[Test]
    public function 予約履歴の問い合わせの数は予約の数に関わらず一定になる(): void
    {
        $few = User::factory()->create();
        $this->makeReservation($few);
        $many = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->makeReservation($many);
        }

        $count = function (User $user): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($user)->test(MyPageReservationHistory::class);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->assertSame($count($few), $count($many));
    }

    // ---- 範囲外のページ番号・詳細の取得 ----

    /**
     * 範囲外のページ番号（?addressPage=99 など）は、最終ページに丸めて表示する（0件の表示にしない）
     */
    #[Test]
    public function 住所の範囲外のページ番号は最終ページになる(): void
    {
        $user = User::factory()->create();
        $this->makeAddresses($user, 6);

        Livewire::withQueryParams([MyPageAddressList::PAGE_NAME => 99])
            ->actingAs($user)
            ->test(MyPageAddressList::class)
            ->assertSee('東京都テスト市1')
            ->assertDontSee('登録されているお届け先住所はありません')
            ->assertSet('paginators.'.MyPageAddressList::PAGE_NAME, 2);
    }

    /**
     * 購入履歴の範囲外のページ番号は、最終ページになる
     */
    #[Test]
    public function 購入履歴の範囲外のページ番号は最終ページになる(): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= 6; $i++) {
            $this->makePurchase($user, [["自分の品{$i}", 2, 100, 1]]);
        }

        Livewire::withQueryParams([MyPagePurchaseHistory::PAGE_NAME => 99])
            ->actingAs($user)
            ->test(MyPagePurchaseHistory::class)
            ->assertSee('自分の品1 2kg')
            ->assertDontSee('購入履歴はありません')
            ->assertSet('paginators.'.MyPagePurchaseHistory::PAGE_NAME, 2);
    }

    /**
     * 予約履歴の範囲外のページ番号は、最終ページになる
     */
    #[Test]
    public function 予約履歴の範囲外のページ番号は最終ページになる(): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= 6; $i++) {
            $this->makeReservation($user, 'card', ['total_price' => 1000 + $i]);
        }

        Livewire::withQueryParams([MyPageReservationHistory::PAGE_NAME => 99])
            ->actingAs($user)
            ->test(MyPageReservationHistory::class)
            ->assertSee('1,001円')
            ->assertDontSee('予約履歴はありません')
            ->assertSet('paginators.'.MyPageReservationHistory::PAGE_NAME, 2);
    }

    /**
     * 履歴が0件のときは、範囲外のページ番号でも1ページ目の「ありません」の表示になる
     */
    #[Test]
    public function 履歴が0件のときは範囲外のページ番号でもないことを表示する(): void
    {
        Livewire::withQueryParams([MyPagePurchaseHistory::PAGE_NAME => 5])
            ->actingAs(User::factory()->create())
            ->test(MyPagePurchaseHistory::class)
            ->assertSee('購入履歴はありません')
            ->assertSet('paginators.'.MyPagePurchaseHistory::PAGE_NAME, 1);
    }

    /**
     * 詳細を開くとき、購入履歴の詳細は1回だけ取得する（開く処理と描画で取得し直さない）
     */
    #[Test]
    public function 購入の詳細は1回だけ取得する(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user);
        $component = Livewire::actingAs($user)->test(MyPagePurchaseHistory::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->call('openDetail', $purchase->id)->assertSee('商品内訳');
        $detailQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'purchase_histories') && str_contains($query['query'], '"id" = ?'));
        DB::disableQueryLog();

        $this->assertCount(1, $detailQueries);
    }

    /**
     * 詳細を開くとき、予約の詳細は1回だけ取得する
     */
    #[Test]
    public function 予約の詳細は1回だけ取得する(): void
    {
        $user = User::factory()->create();
        $reservation = $this->makeReservation($user);
        $component = Livewire::actingAs($user)->test(MyPageReservationHistory::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->call('openDetail', $reservation->id)->assertSee('商品内訳');
        $detailQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'from "reservations"') && str_contains($query['query'], '"id" = ?'));
        DB::disableQueryLog();

        $this->assertCount(1, $detailQueries);
    }

    /**
     * 詳細モーダルを閉じたあとのページ送りでは、詳細を取得しない
     */
    #[Test]
    public function 詳細を閉じたあとのページ送りでは詳細を取得しない(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user);
        for ($i = 0; $i < 5; $i++) {
            $this->makePurchase($user);
        }
        $component = Livewire::actingAs($user)
            ->test(MyPagePurchaseHistory::class)
            ->call('openDetail', $purchase->id)
            ->set('showDetail', false);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->call('gotoPage', 2, MyPagePurchaseHistory::PAGE_NAME);
        $detailQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'purchase_histories') && str_contains($query['query'], '"id" = ?'));
        DB::disableQueryLog();

        $this->assertCount(0, $detailQueries);
    }

    /**
     * 購入と予約の詳細モーダルの見出しのidは、別々になる（同じページに並ぶため）
     */
    #[Test]
    public function 購入と予約の詳細モーダルの見出しのidは別になる(): void
    {
        $user = User::factory()->create();
        $purchase = $this->makePurchase($user);
        $reservation = $this->makeReservation($user);

        Livewire::actingAs($user)->test(MyPagePurchaseHistory::class)
            ->call('openDetail', $purchase->id)
            ->assertSeeHtml('id="purchase-detail-title"')
            ->assertSeeHtml('aria-labelledby="purchase-detail-title"');

        Livewire::actingAs($user)->test(MyPageReservationHistory::class)
            ->call('openDetail', $reservation->id)
            ->assertSeeHtml('id="reservation-detail-title"')
            ->assertSeeHtml('aria-labelledby="reservation-detail-title"');
    }

    /**
     * 都道府県の選択肢は、住所追加モーダルを開いているときだけ表示（取得）する
     */
    #[Test]
    public function 都道府県の選択肢はモーダルを開いたときだけ取得する(): void
    {
        $this->makeAreas();

        Livewire::actingAs(User::factory()->create())
            ->test(MyPageAddressList::class)
            ->assertDontSeeHtml('value="沖縄県"')
            ->call('openAddressModal')
            ->assertSeeHtml('value="沖縄県"')
            ->set('showAddressModal', false)
            ->call('$refresh')
            ->assertDontSeeHtml('value="沖縄県"');
    }

    /**
     * 3つの一覧のページ番号は、それぞれ独立している
     */
    #[Test]
    public function 一覧ごとにページ番号の名前が違う(): void
    {
        $names = [MyPageAddressList::PAGE_NAME, MyPagePurchaseHistory::PAGE_NAME, MyPageReservationHistory::PAGE_NAME];

        $this->assertCount(3, array_unique($names));
    }
}
