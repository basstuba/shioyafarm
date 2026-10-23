<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Livewire\ReservationForm;
use App\Models\Delivery_address;
use App\Models\Item;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\Concerns\ReservationTestHelpers;
use Tests\TestCase;

/**
 * 予約ページの Livewire コンポーネント（数量・住所・支払方法・住所追加モーダル）のテスト
 */
class ReservationFormLivewireTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;
    use ReservationTestHelpers;

    /**
     * 会員（住所：北海道）と北の恵みセット10kg（1,450円）を作る
     *
     * @return array{User, Item}
     */
    private function scenario(): array
    {
        $this->makeAreas();

        return [$this->makeUser(), $this->makeReservable()];
    }

    /**
     * 初期表示：会員登録の内容が入り、数量1で小計1,450円・送料1,530円・合計2,980円（S-2）
     */
    #[Test]
    public function 初期表示は会員登録の内容と北海道の送料(): void
    {
        [$user, $item] = $this->scenario();

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->assertSet('itemId', $item->id)
            ->assertSet('quantity', 1)
            ->assertSet('deliveryName', '塩谷太郎')
            ->assertSet('deliveryTell', '09012345678')
            ->assertSet('addressId', 'user_'.$user->id)
            ->assertSet('paymentMethod', 'card')
            ->assertViewHas('summary', function ($summary): bool {
                return $summary->subtotal === 1450
                    && $summary->shippingFee === config('shipping.fees.100.北海道')
                    && $summary->shippingFee === 1530
                    && $summary->codFee === 0
                    && $summary->total === 2980;
            })
            ->assertSee('北海道札幌市中央区1-1-1')
            ->assertSee('2,980円');
    }

    /**
     * 数量を2に変えると、再読み込みなしで小計2,900円・送料2,190円（20kg・140サイズ）・合計5,090円になる（S-4）
     */
    #[Test]
    public function 数量を2にすると金額が更新される(): void
    {
        [$user, $item] = $this->scenario();

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->call('updateQuantity', '2')
            ->assertHasNoErrors()
            ->assertSet('quantity', 2)
            ->assertViewHas('summary', function ($summary): bool {
                return $summary->subtotal === 2900
                    && $summary->totalWeight === 20
                    && $summary->shippingFee === config('shipping.fees.140.北海道')
                    && $summary->shippingFee === 2190
                    && $summary->total === 5090;
            })
            ->assertSee('5,090円');
    }

    /**
     * 数量を4に変えると、小計5,800円・送料4,590円（180サイズ3,060円＋100サイズ1,530円の2個口）・合計10,390円（S-6）
     */
    #[Test]
    public function 数量を4にすると二個口の送料になる(): void
    {
        [$user, $item] = $this->scenario();

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->call('updateQuantity', '4')
            ->assertViewHas('summary', function ($summary): bool {
                return $summary->subtotal === 5800
                    && $summary->shippingFee === config('shipping.fees.180.北海道') + config('shipping.fees.100.北海道')
                    && $summary->shippingFee === 4590
                    && $summary->total === 10390;
            });
    }

    /**
     * 5kgの商品は送料1,230円（80サイズ）・合計2,055円（S-7）
     */
    #[Test]
    public function 五キロの商品は送料1230円(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable(5, 825);

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->assertViewHas('summary', fn ($summary): bool => $summary->subtotal === 825
                && $summary->shippingFee === config('shipping.fees.80.北海道')
                && $summary->shippingFee === 1230
                && $summary->total === 2055);
    }

    /**
     * お届け先住所を東京都に切り替えると、送料2,050円・合計3,500円になり、確認用住所も切り替わる（S-5）
     */
    #[Test]
    public function 住所を東京都に切り替えると送料と確認用住所が変わる(): void
    {
        [$user, $item] = $this->scenario();
        $tokyo = Delivery_address::factory()->create(['user_id' => $user->id, 'delivery_prefecture' => '東京都', 'delivery_city' => '千代田区1-1']);

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->set('addressId', 'delivery_'.$tokyo->id)
            ->assertViewHas('summary', fn ($summary): bool => $summary->shippingFee === config('shipping.fees.100.関東')
                && $summary->shippingFee === 2050
                && $summary->total === 3500)
            ->assertViewHas('confirmAddress', '東京都千代田区1-1')
            ->assertSee('3,500円');
    }

    /**
     * 数量が範囲外・数字以外のときはエラーになり、数量も金額も変わらない（S-10）
     */
    #[Test]
    public function 範囲外の数量はエラーで金額が変わらない(): void
    {
        [$user, $item] = $this->scenario();

        foreach (['0', '11', '', 'abc', '1.5'] as $input) {
            Livewire::actingAs($user)
                ->test(ReservationForm::class, ['itemId' => $item->id])
                ->call('updateQuantity', $input)
                ->assertHasErrors(['quantity'])
                ->assertSet('quantity', 1)
                ->assertViewHas('summary', fn ($summary): bool => $summary->subtotal === 1450);
        }
    }

    /**
     * 数量の境界値：1個と10個は受け付ける。入力の上限（max）は10
     */
    #[Test]
    public function 数量の境界値は一個と十個まで受け付ける(): void
    {
        [$user, $item] = $this->scenario();

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->call('updateQuantity', '10')
            ->assertHasNoErrors()
            ->assertSet('quantity', 10)
            ->call('updateQuantity', '1')
            ->assertHasNoErrors()
            ->assertSet('quantity', 1)
            ->assertSee('max="10"', false)
            ->assertSee('min="1"', false);
    }

    /**
     * 残数が10より少ないときは、数量の入力の上限が残数になり、超える数量はエラーにする（AC-8, S-12）
     */
    #[Test]
    public function 数量の上限は残数と10の小さい方(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable(limit: 3);
        $this->makeReservedBy($item, 1);

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->assertSee('max="2"', false)
            ->call('updateQuantity', '3')
            ->assertHasErrors(['quantity'])
            ->assertSee('予約可能数を超えています（残り2個）')
            ->assertSet('quantity', 1)
            ->call('updateQuantity', '2')
            ->assertHasNoErrors()
            ->assertSet('quantity', 2);
    }

    /**
     * 予約可能数が未設定の商品は、上限10だけ
     */
    #[Test]
    public function 予約可能数が未設定なら上限は10(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable(limit: null);
        $this->makeReservedBy($item, 500);

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->assertSee('max="10"', false)
            ->call('updateQuantity', '10')
            ->assertHasNoErrors()
            ->call('updateQuantity', '11')
            ->assertHasErrors(['quantity'])
            ->assertSee('商品の数は10個以下にしてください');
    }

    /**
     * 数量の変更では、商品の取得と予約済みの数量の集計を1回のリクエストの中で1回ずつだけ行う
     */
    #[Test]
    public function 数量の変更で商品と集計を重複して問い合わせない(): void
    {
        [$user, $item] = $this->scenario();
        $component = Livewire::actingAs($user)->test(ReservationForm::class, ['itemId' => $item->id]);

        DB::enableQueryLog();
        $component->call('updateQuantity', '2');
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertSame(1, $queries->filter(fn (string $query): bool => str_contains($query, 'from `reservations`') || str_contains($query, 'from "reservations"'))->count());
        $this->assertSame(1, $queries->filter(fn (string $query): bool => str_contains($query, 'from `items`') || str_contains($query, 'from "items"'))->count());
    }

    /**
     * 予約する商品のIDはクライアントから書き換えられない
     */
    #[Test]
    public function 商品のidは書き換えられない(): void
    {
        [$user, $item] = $this->scenario();
        $other = $this->makeReservable(5, 825, 300, '別のセット');

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->set('itemId', $other->id);
    }

    /**
     * 予約限定でない商品・存在しない商品では、コンポーネントを開けない
     */
    #[Test]
    public function 予約限定でない商品では開けない(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $normal = $this->makeItem('人参', 2, 418);

        foreach ([$normal->id, 9999] as $id) {
            try {
                Livewire::actingAs($user)->test(ReservationForm::class, ['itemId' => $id]);
                $this->fail('存在しない商品・予約限定でない商品は開けないはず');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * 支払方法はクレジットカードと銀行振込の2つで、銀行振込にしても金額は変わらず、代引手数料の行は出ない（AC-5）
     */
    #[Test]
    public function 支払方法の選択肢は2つで金額は変わらない(): void
    {
        [$user, $item] = $this->scenario();

        $component = Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->assertViewHas('paymentOptions', fn (array $options): bool => array_column($options, 'value') === ['card', 'customer_balance'])
            ->assertDontSee('代金引換')
            ->assertDontSee('代引手数料');

        $component->set('paymentMethod', 'customer_balance')
            ->assertViewHas('summary', fn ($summary): bool => $summary->total === 2980)
            ->assertDontSee('代引手数料');
    }

    /**
     * 他人の配送先住所・不正な値は選べず、会員登録の住所に戻る（認可）
     */
    #[Test]
    public function 他人の住所や不正な値は会員登録の住所に戻る(): void
    {
        [$user, $item] = $this->scenario();
        $others = Delivery_address::factory()->create(['delivery_prefecture' => '沖縄県', 'delivery_city' => '那覇市9-9']);

        foreach (['delivery_'.$others->id, 'delivery_9999', 'user_'.($user->id + 1), 'foo', ''] as $addressId) {
            Livewire::actingAs($user)
                ->test(ReservationForm::class, ['itemId' => $item->id])
                ->set('addressId', $addressId)
                ->assertSet('addressId', 'user_'.$user->id)
                ->assertViewHas('confirmAddress', '北海道札幌市中央区1-1-1')
                ->assertViewHas('summary', fn ($summary): bool => $summary->shippingFee === 1530)
                ->assertDontSee('那覇市9-9');
        }
    }

    /**
     * 住所追加：「住所を追加」で登録され、モーダルが閉じ、新しい住所が選択されて送料2,050円・合計3,500円になる（AC-6, S-8）
     */
    #[Test]
    public function 住所を追加するとモーダルが閉じて新しい住所が選択される(): void
    {
        [$user, $item] = $this->scenario();

        $component = Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->call('openAddressModal')
            ->assertSet('showAddressModal', true)
            ->set('delivery_prefecture', '東京都')
            ->set('delivery_city', 'UAT区予約町1-2-3')
            ->call('addAddress')
            ->assertHasNoErrors()
            ->assertSet('showAddressModal', false);

        $address = Delivery_address::where('user_id', $user->id)->sole();
        $this->assertSame('東京都', $address->delivery_prefecture);
        $this->assertSame('UAT区予約町1-2-3', $address->delivery_city);

        $component->assertSet('addressId', 'delivery_'.$address->id)
            ->assertViewHas('confirmAddress', '東京都UAT区予約町1-2-3')
            ->assertViewHas('summary', fn ($summary): bool => $summary->shippingFee === 2050 && $summary->total === 3500)
            ->assertSee('東京都UAT区予約町1-2-3');
    }

    /**
     * 住所追加：未入力・areas にない都道府県はエラーで、住所は登録されない
     */
    #[Test]
    public function 住所追加は不正な入力だとエラーになる(): void
    {
        [$user, $item] = $this->scenario();

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->call('openAddressModal')
            ->call('addAddress')
            ->assertHasErrors(['delivery_prefecture' => 'required', 'delivery_city' => 'required'])
            ->assertSet('showAddressModal', true)
            ->set('delivery_prefecture', '存在県')
            ->set('delivery_city', 'どこか1-1')
            ->call('addAddress')
            ->assertHasErrors(['delivery_prefecture']);

        $this->assertSame(0, Delivery_address::count());
    }

    /**
     * 送料の地域に対応していない都道府県でも、描画でエラーにならず、住所欄にエラーを出して「予約を確定する」を押せなくする
     */
    #[Test]
    public function 送料を計算できない住所でも描画でエラーにならず押せない(): void
    {
        $this->makeAreas();
        $user = $this->makeUser('存在県');
        $item = $this->makeReservable();

        $component = Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->assertOk()
            ->assertViewHas('shippingError', fn ($error): bool => $error !== null)
            ->assertViewHas('summary', fn ($summary): bool => $summary->subtotal === 1450 && $summary->shippingFee === 0)
            ->assertSee('このお届け先住所は送料を計算できません。別の住所を選択してください');

        $this->assertMatchesRegularExpression('#<button[^>]*\sdisabled(?=[=\s>])[^>]*>\s*予約を確定する#', $component->html());
    }

    /**
     * 「予約を確定する」は送信中に押せなくする（二重送信の防止）。注文トークンも発行する
     */
    #[Test]
    public function 予約を確定するボタンは送信中に押せなくなる(): void
    {
        [$user, $item] = $this->scenario();

        $component = Livewire::actingAs($user)->test(ReservationForm::class, ['itemId' => $item->id]);
        $html = $component->html();

        // 送信は、実行中の Livewire の更新（数量の欄の change など）が終わるまで待つ（検証エラーのフラッシュを更新リクエストに消されないため）
        $this->assertStringContainsString('x-on:submit="submit($event)"', $html);
        $this->assertStringContainsString("Livewire.hook('commit'", $html);
        $this->assertStringContainsString('this.pending > 0', $html);
        $this->assertMatchesRegularExpression('#<button[^>]*x-bind:disabled="submitting \|\| false"[^>]*>\s*予約を確定する#', $html);
        $this->assertNotSame('', $component->get('orderToken'));
        $this->assertStringContainsString('name="order_token" value="'.$component->get('orderToken').'"', $html);
    }

    /**
     * 検証エラーで戻ったときは、入力した内容を復元し、使っていない注文トークンは使い回す
     */
    #[Test]
    public function 検証エラーで戻ったときは入力を復元する(): void
    {
        [$user, $item] = $this->scenario();
        $token = $this->newReservationToken($user);

        $this->actingAs($user)->withSession(['_old_input' => [
            'delivery_name' => '',
            'delivery_tell' => '090-1234-5678',
            'quantity' => '0',
            'payment_method' => 'customer_balance',
            'order_token' => $token,
        ]]);

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->assertSet('deliveryName', '')
            ->assertSet('deliveryTell', '090-1234-5678')
            ->assertSet('quantity', 0)
            ->assertSet('paymentMethod', 'customer_balance')
            ->assertSet('orderToken', $token);
    }

    /**
     * 代金引換が送られて戻ってきたときも、選択済みにはせずクレジットカードにする
     */
    #[Test]
    public function 代金引換は復元しない(): void
    {
        [$user, $item] = $this->scenario();

        $this->withSession(['_old_input' => ['payment_method' => 'cod']]);

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->assertSet('paymentMethod', 'card');
    }

    /**
     * 「戻る」で戻った直後は保存待ちの予約の入力（氏名・電話番号・住所・支払方法・数量）を復元する
     */
    #[Test]
    public function 戻った直後は保存待ちの予約の入力を復元する(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance);

        $this->withSession([ReservationService::RESTORE_FLASH_KEY => true]);

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->assertSet('deliveryName', '山田花子')
            ->assertSet('deliveryTell', '08011112222')
            ->assertSet('addressId', 'user_'.$user->id)
            ->assertSet('paymentMethod', 'customer_balance')
            ->assertSet('quantity', 1);
    }

    /**
     * 合図がなければ、保存待ちの予約があっても会員登録の内容で表示する
     */
    #[Test]
    public function 合図がなければ復元しない(): void
    {
        [$user, $item] = $this->scenario();
        $this->putPendingReservation($user, $item, PaymentMethod::CustomerBalance);

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id])
            ->assertSet('deliveryName', '塩谷太郎')
            ->assertSet('paymentMethod', 'card');
    }

    /**
     * 予約ページを開いたときに渡された商品と数量の上限は、最初の表示でだけ使い、後続のリクエストには引き継がない
     * （数量の変更では、そのときの予約数で上限を集計し直す）
     */
    #[Test]
    public function 渡された上限は最初の表示だけで使い数量の変更では集計し直す(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable(limit: 5);

        $component = Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id, 'item' => $item, 'maxQuantity' => 5])
            ->assertViewHas('maxQuantity', 5);

        // 表示した後に、ほかの会員の予約が入って残りが2個になった
        $this->makeReservedBy($item, 3);

        $component->call('updateQuantity', '4')
            ->assertHasErrors(['quantity'])
            ->assertSet('quantity', 1)
            ->assertViewHas('maxQuantity', 2);
    }

    /**
     * 渡された商品が別の商品・予約限定でない商品のときは、渡された値を使わず取得し直す
     */
    #[Test]
    public function 渡された商品が一致しなければ取得し直す(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeReservable(limit: 5);
        $other = $this->makeReservable(5, 825, 300, '別のセット');

        Livewire::actingAs($user)
            ->test(ReservationForm::class, ['itemId' => $item->id, 'item' => $other, 'maxQuantity' => 1])
            ->assertSet('itemId', $item->id)
            ->assertViewHas('item', fn (Item $shown): bool => $shown->id === $item->id)
            ->assertViewHas('maxQuantity', 5);
    }
}
