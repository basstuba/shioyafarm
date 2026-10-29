<?php

namespace Tests\Feature;

use App\Mail\PurchaseThanksMail;
use App\Mail\ReservationThanksMail;
use App\Mail\ThanksMail;
use App\Models\Item;
use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use App\Models\Purchase_history_item;
use App\Models\Reservation;
use App\Models\Reservation_bank_transfer;
use App\Models\User;
use App\Repositories\Interfaces\PurchaseHistoryRepositoryInterface;
use App\Repositories\Interfaces\ReservationRepositoryInterface;
use App\Support\MailText;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 購入・予約のサンクスメールの内容（件名・宛先・差出人・HTML版とテキスト版の本文）のテスト
 *
 * メールの送信（保存した直後に1通だけ送る、失敗しても保存する）は ThanksMailSendTest で確かめる。
 */
class ThanksMailContentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 差出人・署名は config('mail.from') から出るため、.env に左右されないよう、既定の値にそろえる
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.from.address' => 'shioyafarm@example.com', 'mail.from.name' => 'しおや農園']);
    }

    /** テスト内で使い回す会員 */
    private ?User $member = null;

    /**
     * 会員を作る（宛名は「山田 太郎」）。同じテストの中では同じ会員を返す
     */
    private function member(): User
    {
        return $this->member ??= User::factory()->create(['last_name' => '山田', 'first_name' => '太郎', 'email' => 'taro@example.com']);
    }

    /**
     * 購入履歴を、明細（じゃがいも2kg×2・玉ねぎ5kg×1・人参2kg×1。商品小計1,873円）つきで作る
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makePurchase(string $method = 'card', array $overrides = [], ?User $user = null): Purchase_history
    {
        $codFee = $method === 'cod' ? (int) config('shipping.cod_fee') : 0;

        $purchase = Purchase_history::factory()->create(array_merge([
            'user_id' => ($user ?? $this->member())->id,
            'delivery_name' => '塩谷花子',
            'delivery_prefecture' => '北海道',
            'delivery_city' => '札幌市中央区1-1-1',
            'delivery_tell' => '08011112222',
            'payment_method' => $method,
            'shipping_fee' => 1850,
            'cod_fee' => $codFee,
            'total_price' => 1873 + 1850 + $codFee,
            'created_at' => CarbonImmutable::parse('2026-10-23 14:05:30'),
        ], $overrides));

        foreach ([['じゃがいも', 2, 330, 2, 660], ['玉ねぎ', 5, 795, 1, 795], ['人参', 2, 418, 1, 418]] as [$name, $weight, $price, $quantity, $subtotal]) {
            Purchase_history_item::factory()->create([
                'purchase_history_id' => $purchase->id,
                'item_id' => Item::factory()->create(['name' => $name, 'weight' => $weight, 'price' => $price])->id,
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $subtotal,
            ]);
        }

        return $purchase;
    }

    /**
     * 予約（北の恵みセット10kg×1。商品小計1,450円・送料1,530円・合計2,980円）を作る
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeReservation(string $method = 'card', array $overrides = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'user_id' => $this->member()->id,
            'item_id' => Item::factory()->create(['name' => '北の恵みセット', 'weight' => 10, 'price' => 1450, 'is_reserved_only' => true])->id,
            'delivery_name' => '塩谷花子',
            'delivery_prefecture' => '北海道',
            'delivery_city' => '札幌市中央区1-1-1',
            'delivery_tell' => '08011112222',
            'quantity' => 1,
            'price' => 1450,
            'subtotal' => 1450,
            'payment_method' => $method,
            'shipping_fee' => 1530,
            'total_price' => 2980,
            'created_at' => CarbonImmutable::parse('2026-10-23 14:05:30'),
        ], $overrides));
    }

    /**
     * 購入のメールは、会員のメールアドレス宛て・しおや農園の差出人で、件名に注文番号が入る（AC-1, AC-6）
     */
    #[Test]
    public function 購入のメールの宛先_差出人_件名(): void
    {
        $purchase = $this->makePurchase();

        $mail = new PurchaseThanksMail($purchase);

        $this->assertTrue($mail->hasTo('taro@example.com'));
        $this->assertTrue($mail->hasFrom('shioyafarm@example.com', 'しおや農園'));
        $this->assertTrue($mail->hasSubject("【しおや農園】ご注文ありがとうございます（注文番号：{$purchase->id}）"));
    }

    /**
     * 差出人と署名は config('mail.from') の値になる（config を変えると、差出人と署名もその値になる）
     */
    #[Test]
    public function 差出人と署名は設定の値になる(): void
    {
        config(['mail.from.address' => 'info@example.test', 'mail.from.name' => 'テスト農園']);

        foreach ([new PurchaseThanksMail($this->makePurchase()), new ReservationThanksMail($this->makeReservation())] as $mail) {
            $this->assertTrue($mail->hasFrom('info@example.test', 'テスト農園'));
            $mail->assertSeeInText("テスト農園\ninfo@example.test");
            $mail->assertSeeInHtml("テスト農園<br>\ninfo@example.test", false);
            $mail->assertDontSeeInText('shioyafarm@example.com');
        }
    }

    /**
     * 予約のメールは、件名に予約番号が入る（AC-5）
     */
    #[Test]
    public function 予約のメールの宛先と件名(): void
    {
        $reservation = $this->makeReservation();

        $mail = new ReservationThanksMail($reservation);

        $this->assertTrue($mail->hasTo('taro@example.com'));
        $this->assertTrue($mail->hasSubject("【しおや農園】ご予約ありがとうございます（予約番号：{$reservation->id}）"));
    }

    /**
     * クレジットカードの購入：宛名・注文情報・商品ごとの明細・金額・お届け先が、HTML版・テキスト版のどちらにも載る。代引手数料とお振込先は載らない（AC-2, AC-3, AC-6）
     */
    #[Test]
    public function クレジットカードの購入メールの本文(): void
    {
        $purchase = $this->makePurchase('card');
        $mail = new PurchaseThanksMail($purchase);

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $see) {
            foreach ([
                '山田 太郎 様',
                '以下の内容でご注文を承りました。',
                '■ ご注文内容',
                "注文番号：{$purchase->id}",
                'ご注文日時：2026年10月23日 14:05',
                'お支払方法：クレジットカード',
                'じゃがいも 2kg × 2　　¥660',
                '玉ねぎ 5kg × 1　　¥795',
                '人参 2kg × 1　　¥418',
                '商品小計　　¥1,873',
                '送料　　　　¥1,850',
                '合計　　　　¥3,723',
                '■ お届け先',
                '塩谷花子 様',
                '北海道札幌市中央区1-1-1',
                '電話番号：08011112222',
                'ご注文の内容は、マイページの購入履歴でもご確認いただけます。',
                'このメールは送信専用です。',
                route('top.inquiry'),
                'shioyafarm@example.com',
            ] as $expected) {
                $mail->{$see}($expected, false);
            }
        }

        foreach (['assertDontSeeInHtml', 'assertDontSeeInText'] as $dontSee) {
            foreach (['代引手数料', '■ お振込先', '残高', '9月より'] as $unexpected) {
                $mail->{$dontSee}($unexpected, false);
            }
        }
    }

    /**
     * 代金引換の購入：代引手数料の行があり、合計に含まれる（AC-2）
     */
    #[Test]
    public function 代金引換の購入メールには代引手数料が載る(): void
    {
        $codFee = (int) config('shipping.cod_fee');
        $mail = new PurchaseThanksMail($this->makePurchase('cod'));

        $this->assertSame(330, $codFee);
        foreach (['assertSeeInHtml', 'assertSeeInText'] as $see) {
            $mail->{$see}('お支払方法：代金引換', false);
            $mail->{$see}('商品小計　　'.MailText::yen(1873), false);
            $mail->{$see}('代引手数料　'.MailText::yen($codFee), false);
            $mail->{$see}('合計　　　　'.MailText::yen(1873 + 1850 + $codFee), false);
        }
        $mail->assertSeeInText('合計　　　　¥4,053');
        $mail->assertDontSeeInHtml('■ お振込先', false);
        $mail->assertDontSeeInText('■ お振込先');
    }

    /**
     * 銀行振込の購入：お振込先（銀行名・支店名・口座種別・口座番号・口座名義・お振込金額）と専用口座の注意が載る（AC-4）
     */
    #[Test]
    public function 銀行振込の購入メールにはお振込先が載る(): void
    {
        $purchase = $this->makePurchase('customer_balance');
        Purchase_bank_transfer::factory()->create([
            'purchase_history_id' => $purchase->id,
            'bank_name' => 'いろは銀行',
            'branch_name' => '中央支店',
            'account_type' => 'toza',
            'account_number' => '7654321',
            'account_holder_name' => 'ストライプジャパン',
            'amount' => 3723,
        ]);
        $mail = new PurchaseThanksMail($purchase);

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $see) {
            foreach ([
                'お支払方法：銀行振込',
                '■ お振込先',
                '銀行名：いろは銀行',
                '支店名：中央支店',
                '口座種別：当座',
                '口座番号：7654321',
                '口座名義：ストライプジャパン',
                'お振込金額：¥3,723',
                '※ お客様専用の口座です。ご入金を確認してから発送いたします。',
            ] as $expected) {
                $mail->{$see}($expected, false);
            }
        }
        $mail->assertDontSeeInHtml('お客様の残高からお支払いが完了しています', false);
        $mail->assertDontSeeInText('代引手数料');
    }

    /**
     * 顧客残高で支払い済みの銀行振込（振込先の5項目が null）：口座の項目は出さず、支払い完了の案内を出す（AC-4）
     */
    #[Test]
    public function 顧客残高で支払い済みの購入メールには口座の項目が載らない(): void
    {
        $purchase = $this->makePurchase('customer_balance');
        Purchase_bank_transfer::factory()->create([
            'purchase_history_id' => $purchase->id,
            'bank_name' => null,
            'branch_name' => null,
            'account_type' => null,
            'account_number' => null,
            'account_holder_name' => null,
            'amount' => 3723,
        ]);
        $mail = new PurchaseThanksMail($purchase);

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $see) {
            $mail->{$see}('■ お振込先', false);
            $mail->{$see}('お客様の残高からお支払いが完了しています', false);
        }
        foreach (['assertDontSeeInHtml', 'assertDontSeeInText'] as $dontSee) {
            foreach (['銀行名', '支店名', '口座種別', '口座番号', '口座名義', 'お振込金額', 'お客様専用の口座です'] as $unexpected) {
                $mail->{$dontSee}($unexpected, false);
            }
        }
    }

    /**
     * 銀行振込なのに振込先のデータが無いとき（通常は起きない）は、「残高から支払い完了」と言い切らず、お振込先のセクションを出さずに警告を残す
     */
    #[Test]
    public function 振込先のデータが無い銀行振込のメールにはお振込先を載せない(): void
    {
        Log::spy();
        $purchase = $this->makePurchase('customer_balance');
        $mail = new PurchaseThanksMail($purchase);

        foreach (['assertDontSeeInHtml', 'assertDontSeeInText'] as $dontSee) {
            foreach (['■ お振込先', 'お客様の残高からお支払いが完了しています', '銀行名'] as $unexpected) {
                $mail->{$dontSee}($unexpected, false);
            }
        }
        $mail->assertSeeInText('お支払方法：銀行振込');
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, '振込先のデータがない') && $context['id'] === $purchase->id)
            ->atLeast()->once();
    }

    /**
     * 予約（クレジットカード）：予約の見出し・商品1行・金額・お届け先・9月からの発送の案内が載り、代引手数料とお振込先は載らない（AC-5, AC-6）
     */
    #[Test]
    public function クレジットカードの予約メールの本文(): void
    {
        $reservation = $this->makeReservation('card');
        $mail = new ReservationThanksMail($reservation);

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $see) {
            foreach ([
                '山田 太郎 様',
                '以下の内容でご予約を承りました。',
                '■ ご予約内容',
                "予約番号：{$reservation->id}",
                'ご予約日時：2026年10月23日 14:05',
                'お支払方法：クレジットカード',
                '北の恵みセット 10kg × 1　　¥1,450',
                '商品小計　　¥1,450',
                '送料　　　　¥1,530',
                '合計　　　　¥2,980',
                '商品は9月より、準備が整い次第順次発送いたします。',
                '■ お届け先',
                '塩谷花子 様',
                '北海道札幌市中央区1-1-1',
                '電話番号：08011112222',
                'ご予約の内容は、マイページの予約履歴でもご確認いただけます。',
                'このメールは送信専用です。',
                route('top.inquiry'),
                'shioyafarm@example.com',
            ] as $expected) {
                $mail->{$see}($expected, false);
            }
        }
        foreach (['assertDontSeeInHtml', 'assertDontSeeInText'] as $dontSee) {
            foreach (['代引手数料', '■ お振込先', '■ ご注文内容', '注文番号'] as $unexpected) {
                $mail->{$dontSee}($unexpected, false);
            }
        }
    }

    /**
     * 予約（銀行振込）：振込先の口座とお振込金額が載る。顧客残高で支払い済みなら支払い完了の案内になる（AC-4, AC-5）
     */
    #[Test]
    public function 銀行振込の予約メールのお振込先(): void
    {
        $transferred = $this->makeReservation('customer_balance');
        Reservation_bank_transfer::factory()->create(['reservation_id' => $transferred->id, 'amount' => 2980]);
        $paid = $this->makeReservation('customer_balance');
        Reservation_bank_transfer::factory()->create([
            'reservation_id' => $paid->id,
            'bank_name' => null,
            'branch_name' => null,
            'account_type' => null,
            'account_number' => null,
            'account_holder_name' => null,
            'amount' => 2980,
        ]);

        $mail = new ReservationThanksMail($transferred);
        foreach (['assertSeeInHtml', 'assertSeeInText'] as $see) {
            foreach (['お支払方法：銀行振込', '■ お振込先', '銀行名：いろは銀行', '支店名：中央支店', '口座種別：普通', '口座番号：1234567', '口座名義：ストライプジャパン', 'お振込金額：¥2,980', 'ご入金を確認してから発送いたします。'] as $expected) {
                $mail->{$see}($expected, false);
            }
        }

        $mail = new ReservationThanksMail($paid);
        foreach (['assertSeeInHtml', 'assertSeeInText'] as $see) {
            $mail->{$see}('お客様の残高からお支払いが完了しています', false);
        }
        $mail->assertDontSeeInHtml('銀行名', false);
        $mail->assertDontSeeInText('口座番号');
    }

    /**
     * 金額は保存済みの値を使う（現在の商品価格を変えても、注文時の金額のまま）
     */
    #[Test]
    public function 金額は保存済みの値を使う(): void
    {
        $purchase = $this->makePurchase('cod');
        Item::query()->update(['price' => 99999]);

        $mail = new PurchaseThanksMail($purchase->fresh());

        $mail->assertSeeInHtml('じゃがいも 2kg × 2　　¥660', false);
        $mail->assertSeeInText('商品小計　　¥1,873');
        $mail->assertDontSeeInText('99,999');
    }

    /**
     * お届け先氏名などに入力したHTMLのタグは、タグとして解釈されず、文字のまま表示される（AC-9）
     */
    #[Test]
    public function 入力したhtmlのタグはエスケープされる(): void
    {
        $purchase = $this->makePurchase('card', [
            'delivery_name' => '<b>UAT太字</b><script>alert(1)</script>',
            'delivery_city' => '<i>札幌市</i>',
        ]);
        $mail = new PurchaseThanksMail($purchase);

        $mail->assertSeeInHtml('&lt;b&gt;UAT太字&lt;/b&gt;', false);
        $mail->assertSeeInHtml('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $mail->assertDontSeeInHtml('<b>UAT太字', false);
        $mail->assertDontSeeInHtml('<script>', false);
        $mail->assertDontSeeInHtml('<i>札幌市', false);
        // テキスト版では、タグの文字のまま読める
        $mail->assertSeeInText('<b>UAT太字</b><script>alert(1)</script> 様');
        $mail->assertSeeInText('北海道<i>札幌市</i>');
    }

    /**
     * 入力した Markdown の記号は、強調・リンク・見出しにならず、文字のまま表示される（AC-9）
     */
    #[Test]
    public function 入力したmarkdownの記号は解釈されない(): void
    {
        $name = '*強調* **太字** [リンク](http://evil.example/a) www.evil.example <http://evil.example/b>';
        $purchase = $this->makePurchase('card', ['delivery_name' => $name, 'delivery_tell' => '# 見出し']);
        $mail = new PurchaseThanksMail($purchase);

        $mail->assertSeeInHtml('*強調* **太字** [リンク](http://evil.example/a) www.evil.example', false);
        foreach (['<em>', '<h1', 'href="http://evil.example', 'href="www.evil.example', 'href="mailto:'] as $unexpected) {
            $mail->assertDontSeeInHtml($unexpected, false);
        }
        $mail->assertSeeInText("{$name} 様");
        $mail->assertSeeInText('電話番号：# 見出し');
    }

    /**
     * 会員の氏名・商品名に入力した記号も解釈されない
     */
    #[Test]
    public function 会員の氏名や商品名の記号も解釈されない(): void
    {
        $user = User::factory()->create(['last_name' => '<u>山田</u>', 'first_name' => '*太郎*', 'email' => 'taro@example.com']);
        $purchase = $this->makePurchase('card', [], $user);
        Item::query()->where('name', 'じゃがいも')->update(['name' => '[じゃがいも](http://evil.example)']);
        $mail = new PurchaseThanksMail($purchase->fresh());

        $mail->assertSeeInHtml('&lt;u&gt;山田&lt;/u&gt; *太郎* 様', false);
        $mail->assertSeeInHtml('[じゃがいも](http://evil.example) 2kg', false);
        $mail->assertDontSeeInHtml('<u>', false);
        $mail->assertDontSeeInHtml('href="http://evil.example', false);
    }

    /**
     * HTML版は「しおや農園」のヘッダーとフッターで、Laravel のロゴや表記は出ない。ボタンは置かない（AC-6, AC-9）
     */
    #[Test]
    public function html版のヘッダーとフッターはしおや農園になる(): void
    {
        $html = (new PurchaseThanksMail($this->makePurchase()))->render();

        $this->assertStringContainsString('しおや農園', $html);
        $this->assertStringContainsString('© '.date('Y').' しおや農園', $html);
        $this->assertStringContainsString('<title>しおや農園</title>', $html);
        $this->assertStringContainsString('background-color: #f0a13a', $html);
        $this->assertStringNotContainsString('Laravel', $html);
        $this->assertStringNotContainsString('laravel.com', $html);
        $this->assertStringNotContainsString('class="button', $html);
        // スマートフォンの幅で横スクロールが出ないよう、画面幅に追従する設定がある
        $this->assertStringContainsString('name="viewport"', $html);
        $this->assertStringContainsString('width: 100% !important', $html);
    }

    /**
     * テキスト版は、見出し・金額・お振込先が行ごとに読める形で、HTMLのタグを含まない（AC-9）
     */
    #[Test]
    public function テキスト版は行ごとに読める(): void
    {
        $purchase = $this->makePurchase('customer_balance');
        Purchase_bank_transfer::factory()->create(['purchase_history_id' => $purchase->id, 'amount' => 3723]);
        $mail = new PurchaseThanksMail($purchase);

        $text = $this->renderText($mail);

        $this->assertStringContainsString("■ ご注文内容\n注文番号：{$purchase->id}\nご注文日時：2026年10月23日 14:05\nお支払方法：銀行振込", $text);
        $this->assertStringContainsString("じゃがいも 2kg × 2　　¥660\n玉ねぎ 5kg × 1　　¥795\n人参 2kg × 1　　¥418", $text);
        $this->assertStringContainsString("商品小計　　¥1,873\n送料　　　　¥1,850\n合計　　　　¥3,723", $text);
        $this->assertStringContainsString("■ お振込先\n銀行名：いろは銀行\n支店名：中央支店\n口座種別：普通\n口座番号：1234567\n口座名義：ストライプジャパン\nお振込金額：¥3,723", $text);
        $this->assertDoesNotMatchRegularExpression('/<\/?(p|br|strong|a|table|td)\b/', $text);
        $this->assertStringNotContainsString('&#', $text);
        $this->assertStringContainsString("しおや農園\nshioyafarm@example.com", $text);
    }

    /**
     * メールに渡す前に会員・商品・振込先を Eager Load するので、商品が増えても問い合わせは増えない
     */
    #[Test]
    public function 商品の数が増えても問い合わせの数は増えない(): void
    {
        $few = $this->makePurchase('customer_balance');
        Purchase_bank_transfer::factory()->create(['purchase_history_id' => $few->id]);
        $many = $this->makePurchase('customer_balance');
        Purchase_bank_transfer::factory()->create(['purchase_history_id' => $many->id]);
        Purchase_history_item::factory()->count(5)->create(['purchase_history_id' => $many->id]);

        $count = function (Purchase_history $purchase): int {
            $fresh = $purchase->fresh();
            DB::flushQueryLog();
            DB::enableQueryLog();
            (new PurchaseThanksMail(app(PurchaseHistoryRepositoryInterface::class)->loadForThanksMail($fresh)))->render();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->assertSame($count($few), $count($many));
        $this->assertLessThanOrEqual(4, $count($many));
    }

    /**
     * 商品ごとの行は、HTML版では1つの段落の中で <br> 区切り（商品ごとに段落を分けない）、テキスト版では1行ずつ並ぶ
     */
    #[Test]
    public function 商品の明細は1つの段落に改行で並ぶ(): void
    {
        $mail = new PurchaseThanksMail($this->makePurchase('card'));

        $html = $mail->render();
        $this->assertMatchesRegularExpression('/>じゃがいも 2kg × 2　　¥660<br>\s*玉ねぎ 5kg × 1　　¥795<br>\s*人参 2kg × 1　　¥418<\/p>/u', $html);
        $this->assertStringContainsString("じゃがいも 2kg × 2　　¥660\n玉ねぎ 5kg × 1　　¥795\n人参 2kg × 1　　¥418\n", $this->renderText($mail));
    }

    /**
     * 会員を渡し済みなら（保存した側が持っている User）、メールの組み立てで会員を読み込み直さない
     */
    #[Test]
    public function 会員を渡し済みならメールの組み立てで会員を読み込み直さない(): void
    {
        $purchase = $this->makePurchase('card')->fresh();
        $purchase->setRelation('user', $this->member());

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        (new PurchaseThanksMail(app(PurchaseHistoryRepositoryInterface::class)->loadForThanksMail($purchase)))->render();

        $this->assertSame([], array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from "users"'))));
    }

    /**
     * 振込先のデータは銀行振込のときだけ読む（カード・代金引換では問い合わせない）
     */
    #[Test]
    public function 振込先は銀行振込のときだけ読み込む(): void
    {
        $card = $this->makePurchase('card')->fresh();
        $transfer = $this->makePurchase('customer_balance');
        Purchase_bank_transfer::factory()->create(['purchase_history_id' => $transfer->id]);
        $transfer = $transfer->fresh();
        $reservationCard = $this->makeReservation('card')->fresh();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $bankQueries = function () use (&$queries): int {
            return count(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'bank_transfers')));
        };

        $purchases = app(PurchaseHistoryRepositoryInterface::class);
        $reservations = app(ReservationRepositoryInterface::class);

        (new PurchaseThanksMail($purchases->loadForThanksMail($card)))->render();
        (new ReservationThanksMail($reservations->loadForThanksMail($reservationCard)))->render();
        $this->assertSame(0, $bankQueries());

        (new PurchaseThanksMail($purchases->loadForThanksMail($transfer)))->render();
        $this->assertSame(1, $bankQueries());
    }

    /**
     * 商品の表示名は「名前 容量kg」
     */
    #[Test]
    public function 商品の表示名は名前に容量を付ける(): void
    {
        $this->assertSame('北の恵みセット 10kg', Item::factory()->make(['name' => '北の恵みセット', 'weight' => 10])->display_name);
    }

    /**
     * 日時は保存日時を「2026年10月23日 14:05」の形で、月日は0を付けずに表示する。金額は「¥1,850」の形
     */
    #[Test]
    public function 日時と金額の形式(): void
    {
        $this->assertSame('2026年9月1日 08:05', MailText::dateTime(CarbonImmutable::parse('2026-09-01 08:05:59')));
        $this->assertSame('', MailText::dateTime(null));
        $this->assertSame('¥0', MailText::yen(0));
        $this->assertSame('¥1,850', MailText::yen(1850));
        $this->assertSame('¥1,234,567', MailText::yen(1234567));
    }

    /**
     * 入力値の整形：記号は文字参照にし、改行などの制御文字は空白にする
     */
    #[Test]
    public function 入力値の整形(): void
    {
        $this->assertSame('&#60;b&#62;&#42;a&#42;&#60;&#47;b&#62;', (string) MailText::safe('<b>*a*</b>'));
        $this->assertSame('東京都 渋谷区 &#35;1', (string) MailText::safe("東京都\n渋谷区\t#1"));
        $this->assertSame('', (string) MailText::safe(null));
        $this->assertSame('山田 太郎', (string) MailText::safe('山田 太郎'));
    }

    /**
     * 不正なUTF-8のバイト列があっても、値は消えずに「?」に置き換わり、警告が残る
     */
    #[Test]
    public function 不正なutf8でも内容は消えない(): void
    {
        Log::spy();

        $this->assertSame('山田&#63;太郎', (string) MailText::safe("山田\xFF太郎"));
        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * SMTP のタイムアウトは、空・0以下・未設定のときは10秒、正の値ならその秒数
     */
    #[Test]
    public function smtpのタイムアウトの設定(): void
    {
        $original = $_SERVER['MAIL_TIMEOUT'] ?? null;
        try {
            foreach ([['', 10], ['0', 10], ['-3', 10], ['abc', 10], ['25', 25]] as [$value, $expected]) {
                $_SERVER['MAIL_TIMEOUT'] = $value;
                $_ENV['MAIL_TIMEOUT'] = $value;
                $this->assertSame($expected, (require config_path('mail.php'))['mailers']['smtp']['timeout'], "MAIL_TIMEOUT={$value}");
            }
            unset($_SERVER['MAIL_TIMEOUT'], $_ENV['MAIL_TIMEOUT']);
            $this->assertSame(10, (require config_path('mail.php'))['mailers']['smtp']['timeout']);
        } finally {
            if ($original === null) {
                unset($_SERVER['MAIL_TIMEOUT'], $_ENV['MAIL_TIMEOUT']);
            } else {
                $_SERVER['MAIL_TIMEOUT'] = $original;
                $_ENV['MAIL_TIMEOUT'] = $original;
            }
        }
    }

    /**
     * テキスト版を取り出す（Mailable の assertSeeInText と同じ描画）
     */
    private function renderText(ThanksMail $mail): string
    {
        $render = new ReflectionMethod($mail, 'renderForAssertions');

        return $render->invoke($mail)[1];
    }
}
