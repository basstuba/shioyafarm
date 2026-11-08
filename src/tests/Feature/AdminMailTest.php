<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminMailController;
use App\Mail\AdminMessageMail;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面のメール作成・送信（admin.mail.user・admin.mail.inquiry・admin.mail.send）のテスト
 */
class AdminMailTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    private const string FAILED_MESSAGE = 'メールを送信できませんでした。時間をおいて、もう一度お試しください。';

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.from.address' => 'shioyafarm@example.com', 'mail.from.name' => 'しおや農園']);
    }

    /**
     * 送信フォームの内容を送る
     *
     * @param  array<string, mixed>  $overrides
     */
    private function postMail(string $type, int $id, array $overrides = []): TestResponse
    {
        return $this->post(route('admin.mail.send'), [
            'type' => $type,
            'id' => $id,
            'mail_title' => 'ご案内',
            'mail_body' => "こんにちは。\nよろしくお願いします。",
            'back' => '',
            ...$overrides,
        ]);
    }

    /**
     * 会員宛ての送信フォームの内容を送る
     *
     * @param  array<string, mixed>  $overrides
     */
    private function sendToUser(User $user, array $overrides = []): TestResponse
    {
        return $this->postMail('user', $user->id, $overrides);
    }

    /**
     * お問い合わせへの返信の送信フォームの内容を送る
     *
     * @param  array<string, mixed>  $overrides
     */
    private function sendToInquiry(Inquiry $inquiry, array $overrides = []): TestResponse
    {
        return $this->postMail('inquiry', $inquiry->id, $overrides);
    }

    /**
     * 3つのルートは決められたコントローラーが受け、管理者の認証と no-store が付く
     */
    #[Test]
    public function ルートは決められたコントローラーが受け管理者の認証とno_storeを要する(): void
    {
        $routes = [
            'admin.mail.user' => [AdminMailController::class.'@createUserMail', 'GET', '/admin/mail/user/{user_id}'],
            'admin.mail.inquiry' => [AdminMailController::class.'@createInquiryMail', 'GET', '/admin/mail/inquiry/{inquiry_id}'],
            'admin.mail.send' => [AdminMailController::class.'@send', 'POST', '/admin/mail/send'],
        ];

        foreach ($routes as $name => [$action, $method, $uri]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertSame($action, $route->getActionName(), $name);
            $this->assertContains($method, $route->methods(), $name);
            $this->assertSame($uri, '/'.$route->uri(), $name);
            $this->assertContains('auth:admin', $route->gatherMiddleware(), $name);
            $this->assertContains('no-store', $route->gatherMiddleware(), $name);
        }
    }

    /**
     * 未ログインでは作成ページも送信も /admin/login へ移動し、メールは送られない（AC-1）
     */
    #[Test]
    public function 未ログインはログインへ移動しメールは送られない(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create();

        $this->get(route('admin.mail.user', ['user_id' => $user->id]))->assertRedirect(route('admin.login'));
        $this->get(route('admin.mail.inquiry', ['inquiry_id' => $inquiry->id]))->assertRedirect(route('admin.login'));
        $this->sendToUser($user)->assertRedirect(route('admin.login'));

        Mail::assertNothingSent();
    }

    /**
     * 会員としてログインしていても、管理者でなければ /admin/login へ移動し、メールは送られない（AC-1）
     */
    #[Test]
    public function 会員のログインでは開けず送れない(): void
    {
        Mail::fake();
        $member = $this->loginAsMember();
        $inquiry = Inquiry::factory()->create();

        $this->get(route('admin.mail.user', ['user_id' => $member->id]))->assertRedirect(route('admin.login'));
        $this->get(route('admin.mail.inquiry', ['inquiry_id' => $inquiry->id]))->assertRedirect(route('admin.login'));
        $this->sendToUser($member)->assertRedirect(route('admin.login'));

        Mail::assertNothingSent();
    }

    /**
     * 会員宛てのメール作成ページに、送信先の氏名・メールアドレス、入力欄、案内、ボタン、戻るリンクが出る。no-store が付く（AC-2・4・5・19）
     */
    #[Test]
    public function 会員宛てのメール作成ページが表示される(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create(['last_name' => '山田', 'first_name' => '太郎', 'email' => 'yamada@example.com']);

        $response = $this->get(route('admin.mail.user', ['user_id' => $user->id]));

        $response->assertOk()
            ->assertViewIs('admin.mail_create')
            ->assertSee('メール作成')
            ->assertSee('送信先氏名')
            ->assertSee('山田 太郎')
            ->assertSee('送信先メールアドレス')
            ->assertSee('yamada@example.com')
            ->assertSee('メールタイトル')
            ->assertSee('メール本文')
            ->assertSee('件名の先頭に【しおや農園】が付きます')
            ->assertSee('placeholder="例：お問い合わせありがとうございます。"', false)
            ->assertSee('placeholder="例：お問い合わせありがとうございました。"', false)
            ->assertSee('メールを送信します。よろしいですか？')
            ->assertSee('一覧へ戻る')
            ->assertSee('<input type="hidden" name="type" value="user">', false)
            ->assertSee('<input type="hidden" name="id" value="'.$user->id.'">', false)
            ->assertSee('action="'.route('admin.mail.send').'"', false)
            ->assertSee('text-mail-link', false)
            ->assertDontSee('name="email"', false)
            // 「送信する」ボタンはPCで幅400pxのカード中央寄せ、SPは全幅
            ->assertSee('class="mx-auto mt-8 flex h-12 w-full', false)
            ->assertSee('lg:h-11 lg:w-[400px]', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        // サイドメニューの太字（現在のページ）の項目はない
        $this->assertStringNotContainsString('aria-current="page"', $response->getContent());
    }

    /**
     * 退会済みの会員にもメール作成ページを開ける（AC-2）
     */
    #[Test]
    public function 退会済みの会員宛てのメール作成ページも表示される(): void
    {
        $this->loginAsAdmin();
        $user = $this->makeWithdrawnUser('退会', '花子');

        $this->get(route('admin.mail.user', ['user_id' => $user->id]))
            ->assertOk()
            ->assertSee('退会 花子')
            ->assertSee($user->email);
    }

    /**
     * お問い合わせ宛てのメール作成ページに、お問い合わせの氏名・メールアドレスが出る（AC-3・5）
     */
    #[Test]
    public function お問い合わせ宛てのメール作成ページが表示される(): void
    {
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->create(['last_name' => '佐藤', 'first_name' => '花子', 'email' => 'sato@example.com']);

        $this->get(route('admin.mail.inquiry', ['inquiry_id' => $inquiry->id]))
            ->assertOk()
            ->assertViewIs('admin.mail_create')
            ->assertSee('佐藤 花子')
            ->assertSee('sato@example.com')
            ->assertSee('<input type="hidden" name="type" value="inquiry">', false)
            ->assertSee('<input type="hidden" name="id" value="'.$inquiry->id.'">', false);
    }

    /**
     * 氏名・メールアドレスに HTML の記号があっても文字のまま表示される（AC-16）
     */
    #[Test]
    public function 宛先の氏名は文字のまま表示される(): void
    {
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->create(['last_name' => '<b>太字</b>', 'first_name' => '<script>alert(1)</script>']);

        $this->get(route('admin.mail.inquiry', ['inquiry_id' => $inquiry->id]))
            ->assertOk()
            ->assertSee('&lt;b&gt;太字&lt;/b&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    /**
     * 存在しない会員・お問い合わせ、数字でないIDのメール作成ページは404（AC-6）
     */
    #[Test]
    public function 存在しない宛先の作成ページは404になる(): void
    {
        $this->loginAsAdmin();

        $this->get(route('admin.mail.user', ['user_id' => 999999]))->assertNotFound();
        $this->get(route('admin.mail.inquiry', ['inquiry_id' => 999999]))->assertNotFound();
        $this->get(route('admin.mail.user', ['user_id' => '99999999999999999999']))->assertNotFound();
        $this->get('/admin/mail/user/abc')->assertNotFound();
        $this->get('/admin/mail/inquiry/abc')->assertNotFound();
    }

    /**
     * 会員一覧の検索結果から開くと、戻り先（「一覧へ戻る」と送信フォームの back）はその検索条件・ページになる（AC-10・17）
     */
    #[Test]
    public function 来た一覧の検索条件とページを戻り先にする(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $back = route('admin.users.search', ['name' => '山田', 'page' => 2], false);

        $html = $this->from(url($back))->get(route('admin.mail.user', ['user_id' => $user->id]))->assertOk()->getContent();

        $this->assertStringContainsString('<input type="hidden" name="back" value="'.e($back).'">', $html);
        $this->assertStringContainsString('href="'.e($back).'"', $html);
    }

    /**
     * お問い合わせ一覧の検索結果から開くと、お問い合わせ一覧の検索条件が戻り先になる
     */
    #[Test]
    public function お問い合わせ一覧の検索条件を戻り先にする(): void
    {
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->create();
        $back = route('admin.inquiries.search', ['addressed' => '1', 'date' => '2026-11-01'], false);

        $html = $this->from(url($back))->get(route('admin.mail.inquiry', ['inquiry_id' => $inquiry->id]))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.e($back).'"', $html);
    }

    /**
     * 直接開いた（前のページがない・その種類の一覧でない・外部のURL）ときの戻り先は、その種類の一覧の1ページ目
     */
    #[Test]
    public function 直接開いたときの戻り先は一覧の1ページ目(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create();
        $users = route('admin.users.index', absolute: false);
        $inquiries = route('admin.inquiries.index', absolute: false);

        $this->get(route('admin.mail.user', ['user_id' => $user->id]))->assertSee('href="'.$users.'"', false);
        $this->from('https://evil.example/somewhere')->get(route('admin.mail.user', ['user_id' => $user->id]))->assertSee('href="'.$users.'"', false);
        // 別の種類の一覧・注文一覧から来ても採用しない
        $this->from(route('admin.inquiries.index'))->get(route('admin.mail.user', ['user_id' => $user->id]))->assertSee('href="'.$users.'"', false);
        $this->from(route('admin.purchase.index'))->get(route('admin.mail.inquiry', ['inquiry_id' => $inquiry->id]))->assertSee('href="'.$inquiries.'"', false);
    }

    /**
     * 会員宛てに送ると、件名「【しおや農園】タイトル」・宛先・差出人・本文のメールが1通だけ届き、控えが農園のアドレスにBCCで届く（AC-8）
     */
    #[Test]
    public function 会員宛てにメールを送る(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = User::factory()->create(['email' => 'yamada@example.com']);

        $this->sendToUser($user, ['mail_title' => 'UAT会員宛て', 'mail_body' => "1行目\n2行目\n3行目"]);

        Mail::assertSentCount(1);
        Mail::assertSent(AdminMessageMail::class, function (AdminMessageMail $mail): bool {
            $mail->assertHasSubject('【しおや農園】UAT会員宛て');
            $mail->assertFrom('shioyafarm@example.com', 'しおや農園');
            $mail->assertSeeInHtml("1行目<br>\n2行目<br>\n3行目", false);
            $mail->assertSeeInText("1行目\n2行目\n3行目");
            // 会員宛てには元のお問い合わせの引用を付けない
            $mail->assertDontSeeInText('いただいたお問い合わせ');

            return $mail->hasTo('yamada@example.com');
        });
    }

    /**
     * メールの宛先（To）はお客様だけで、農園のアドレスは To・CC に入らず BCC に入る
     */
    #[Test]
    public function 控えは農園のアドレスに_bc_cで送り_toはお客様だけ(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = User::factory()->create(['email' => 'yamada@example.com']);
        $inquiry = Inquiry::factory()->create(['email' => 'sato@example.com']);

        $this->sendToUser($user);
        $this->sendToInquiry($inquiry);

        Mail::assertSentCount(2);
        foreach (['yamada@example.com', 'sato@example.com'] as $customer) {
            Mail::assertSent(AdminMessageMail::class, function (AdminMessageMail $mail) use ($customer): bool {
                if (! $mail->hasTo($customer)) {
                    return false;
                }
                $farm = config('mail.from.address');
                $this->assertCount(1, $mail->to);
                $this->assertSame([], $mail->cc);
                $this->assertTrue($mail->hasBcc($farm));
                $this->assertCount(1, $mail->envelope()->bcc);
                $this->assertFalse($mail->hasTo($farm));
                $this->assertFalse($mail->hasCc($farm));

                return true;
            });
        }
    }

    /**
     * 農園のアドレスの設定を変えると、BCC もそれに従う（新しい設定キーを使わず config('mail.from.address') を使う）
     */
    #[Test]
    public function bc_cの宛先はmail_fromのアドレスに従う(): void
    {
        Mail::fake();
        config(['mail.from.address' => 'farm@example.test']);
        $this->loginAsAdmin();

        $this->sendToUser(User::factory()->create(['email' => 'yamada@example.com']));

        Mail::assertSent(AdminMessageMail::class, fn (AdminMessageMail $mail): bool => $mail->hasBcc('farm@example.test') && $mail->hasFrom('farm@example.test'));
    }

    /**
     * 退会済みの会員にも送れる（AC-2）
     */
    #[Test]
    public function 退会済みの会員にも送れる(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = $this->makeWithdrawnUser();

        $this->sendToUser($user);

        Mail::assertSent(AdminMessageMail::class, fn (AdminMessageMail $mail): bool => $mail->hasTo($user->email));
    }

    /**
     * お問い合わせへの返信は、お問い合わせのメールアドレス宛てで、本文の下に元の問い合わせ日・内容が引用として付く（AC-9）
     */
    #[Test]
    public function お問い合わせへの返信に元のお問い合わせを引用する(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->create([
            'email' => 'sato@example.com',
            'detail' => "配送日を教えてください。\n急ぎです。",
            'created_at' => '2026-11-03 09:30:00',
        ]);

        $this->sendToInquiry($inquiry, ['mail_title' => 'お返事', 'mail_body' => 'ご連絡ありがとうございます。']);

        Mail::assertSent(AdminMessageMail::class, function (AdminMessageMail $mail): bool {
            $mail->assertSeeInHtml('お問い合わせ日：2026/11/03', false);
            $mail->assertSeeInText("お問い合わせ日：2026/11/03\n配送日を教えてください。\n急ぎです。");
            $mail->assertSeeInHtml("配送日を教えてください。<br>\n急ぎです。", false);

            return $mail->hasTo('sato@example.com');
        });
    }

    /**
     * 送信後は、持ち回った一覧（検索条件・ページつき）に戻り、検索欄の下に出す案内を flash に入れる（AC-10）
     */
    #[Test]
    public function 送信後は来た一覧の検索条件とページに戻る(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create();
        $usersBack = route('admin.users.search', ['name' => '山田', 'page' => 2], false);
        $inquiriesBack = route('admin.inquiries.search', ['addressed' => '0', 'page' => 3], false);

        $this->sendToUser($user, ['back' => $usersBack])
            ->assertRedirect(url($usersBack))
            ->assertSessionHas('list_status', 'メールを送信しました。');
        $this->sendToInquiry($inquiry, ['back' => $inquiriesBack])
            ->assertRedirect(url($inquiriesBack))
            ->assertSessionHas('list_status', 'メールを送信しました。');
    }

    /**
     * 戻り先がない・その種類の一覧でない・外部のURL・文字列でない値のときは、一覧の1ページ目に戻る
     */
    #[Test]
    public function 戻り先が不正なときは一覧の1ページ目に戻る(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create();

        foreach (['', 'https://evil.example/', '//evil.example/admin/users', '/admin/purchase', route('admin.inquiries.index', absolute: false), "/admin/users\r\nX-Evil: 1", ['x']] as $back) {
            $this->sendToUser($user, ['back' => $back])->assertRedirect(route('admin.users.index'));
        }
        $this->sendToUser($user, ['back' => null])->assertRedirect(route('admin.users.index'));
        $this->sendToInquiry($inquiry, ['back' => route('admin.users.index', absolute: false)])->assertRedirect(route('admin.inquiries.index'));
    }

    /**
     * 一覧に戻ると、検索欄の下に「メールを送信しました。」が緑の文字で出る（AC-10）
     */
    #[Test]
    public function 一覧に戻ると送信の案内が出る(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $inquiry = Inquiry::factory()->create();

        $this->sendToUser($user);
        $this->get(route('admin.users.index'))->assertOk()->assertSee('<p class="mt-3 w-full text-center text-base text-accent-hover" role="status">メールを送信しました。</p>', false);

        $this->sendToInquiry($inquiry);
        $this->get(route('admin.inquiries.index'))->assertOk()->assertSee('メールを送信しました。')->assertSee('text-accent-hover', false);
    }

    /**
     * 返信を送っても、お問い合わせの対応済みは変わらない（AC-11）
     */
    #[Test]
    public function 返信を送っても対応済みは変わらない(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $open = Inquiry::factory()->create();
        $done = Inquiry::factory()->addressed()->create();

        $this->sendToInquiry($open);
        $this->sendToInquiry($done);

        $this->assertFalse($open->fresh()->addressed);
        $this->assertTrue($done->fresh()->addressed);
        $this->assertSame(2, Inquiry::count());
    }

    /**
     * タイトル・本文が空のときは送られず、メール作成ページに戻って各欄の近くにエラーが出る。入力した値は残る（AC-12）
     */
    #[Test]
    public function 空のタイトルと本文は送れずエラーになる(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $create = url(route('admin.mail.user', ['user_id' => $user->id], false));

        $this->sendToUser($user, ['mail_title' => '', 'mail_body' => ''])
            ->assertRedirect($create)
            ->assertSessionHasErrors(['mail_title' => 'メールタイトルを入力してください', 'mail_body' => 'メール本文を入力してください']);

        $this->get($create)
            ->assertOk()
            ->assertSee('メールタイトルを入力してください')
            ->assertSee('メール本文を入力してください')
            ->assertSee('aria-invalid="true"', false);
        Mail::assertNothingSent();
    }

    /**
     * 文字数の境界：タイトル255文字・本文10,000文字は送れ、256文字・10,001文字は送れない（AC-12）
     */
    #[Test]
    public function 文字数の上限は境界で判定される(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = User::factory()->create();

        $this->sendToUser($user, ['mail_title' => str_repeat('あ', 255), 'mail_body' => str_repeat('い', 10000)])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));
        Mail::assertSentCount(1);

        $this->sendToUser($user, ['mail_title' => str_repeat('あ', 256), 'mail_body' => 'x'])
            ->assertSessionHasErrors(['mail_title' => 'メールタイトルは255文字以内で入力してください']);
        $this->sendToUser($user, ['mail_title' => 'x', 'mail_body' => str_repeat('い', 10001)])
            ->assertSessionHasErrors(['mail_body' => 'メール本文は10000文字以内で入力してください']);
        Mail::assertSentCount(1);
    }

    /**
     * 文字列でないタイトル・本文（配列）は送れない
     */
    #[Test]
    public function 配列のタイトルと本文は送れない(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = User::factory()->create();

        $this->sendToUser($user, ['mail_title' => ['a'], 'mail_body' => ['b']])->assertSessionHasErrors(['mail_title', 'mail_body']);

        Mail::assertNothingSent();
    }

    /**
     * 入力エラーで戻ったとき、タイトル・本文と、持ち回った戻り先が残る（AC-12・17）
     */
    #[Test]
    public function 入力エラーで戻ると入力と戻り先が残る(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $back = route('admin.users.search', ['name' => '山田', 'page' => 2], false);
        $create = route('admin.mail.user', ['user_id' => $user->id]);

        $this->from($create)->sendToUser($user, ['mail_title' => '残るタイトル', 'mail_body' => str_repeat('い', 10001), 'back' => $back])->assertSessionHasErrors('mail_body');

        $html = $this->get($create)->assertOk()->getContent();
        $this->assertStringContainsString('value="残るタイトル"', $html);
        $this->assertStringContainsString('>'.str_repeat('い', 10001).'</textarea>', $html);
        $this->assertStringContainsString('<input type="hidden" name="back" value="'.e($back).'">', $html);
        $this->assertStringContainsString('href="'.e($back).'"', $html);
    }

    /**
     * 宛先にメールアドレスを足して送っても無視され、本来の宛先にだけ届く（AC-13）
     */
    #[Test]
    public function 足したメールアドレスは無視される(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = User::factory()->create(['email' => 'yamada@example.com']);

        $this->sendToUser($user, ['email' => 'attacker@example.com', 'to' => 'attacker@example.com', 'bcc' => 'attacker@example.com']);

        Mail::assertSentCount(1);
        Mail::assertSent(AdminMessageMail::class, function (AdminMessageMail $mail): bool {
            $this->assertCount(1, $mail->to);
            $this->assertFalse($mail->hasTo('attacker@example.com'));
            $this->assertFalse($mail->hasBcc('attacker@example.com'));

            return $mail->hasTo('yamada@example.com');
        });
    }

    /**
     * 存在しない宛先・不正な種類・不正なIDには送れず、404になる（AC-13）
     */
    #[Test]
    public function 不正な宛先には送れない(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = User::factory()->create();

        $this->sendToUser($user, ['id' => 999999])->assertNotFound();
        $this->sendToUser($user, ['type' => 'inquiry', 'id' => $user->id + 1000])->assertNotFound();
        $this->sendToUser($user, ['type' => 'admin'])->assertNotFound();
        $this->sendToUser($user, ['type' => ['user']])->assertNotFound();
        $this->sendToUser($user, ['type' => null])->assertNotFound();
        $this->sendToUser($user, ['id' => 'abc'])->assertNotFound();
        $this->sendToUser($user, ['id' => 0])->assertNotFound();
        $this->sendToUser($user, ['id' => ['1']])->assertNotFound();
        $this->sendToUser($user, ['id' => null])->assertNotFound();

        Mail::assertNothingSent();
    }

    /**
     * 送信に失敗したら、作成ページに戻して入力を残し、赤字で案内する。ログに残すが本文・例外のメッセージ（宛先のメールアドレスが入りうる）は書かない（AC-15）
     */
    #[Test]
    public function 送信に失敗すると作成ページに戻り案内が出る(): void
    {
        Log::spy();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('smtp down'));
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $back = route('admin.users.search', ['name' => '山田'], false);
        // 再読み込みしても戻り先を失わないよう、戻り先は作成ページのURLのクエリに付く
        $create = url(route('admin.mail.user', ['user_id' => $user->id, 'back' => $back], false));

        $this->sendToUser($user, ['mail_title' => '失敗タイトル', 'mail_body' => '秘密の本文', 'back' => $back])
            ->assertRedirect($create)
            ->assertSessionHasErrors(['send' => self::FAILED_MESSAGE])
            ->assertSessionMissing('list_status');

        $html = $this->get($create)->assertOk()->getContent();
        $this->assertStringContainsString(self::FAILED_MESSAGE, $html);
        $this->assertStringContainsString('<p class="mt-6 text-base text-danger" role="alert">'.self::FAILED_MESSAGE.'</p>', $html);
        $this->assertStringContainsString('value="失敗タイトル"', $html);
        $this->assertStringContainsString('>秘密の本文</textarea>', $html);
        $this->assertStringContainsString('<input type="hidden" name="back" value="'.e($back).'">', $html);

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($user): bool {
            $this->assertSame(['type' => 'user', 'id' => $user->id, 'exception' => RuntimeException::class], $context);

            return true;
        });
    }

    /**
     * 送信に失敗したあと、同じ内容でやり直せる（失敗で二重送信の印を残さない）
     */
    #[Test]
    public function 失敗のあとはやり直せる(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $calls = 0;
        Mail::shouldReceive('send')->twice()->andReturnUsing(function () use (&$calls): void {
            if (++$calls === 1) {
                throw new RuntimeException('smtp down');
            }
        });

        $this->sendToUser($user)->assertSessionHasErrors('send');
        $this->sendToUser($user)->assertSessionHasNoErrors()->assertSessionHas('list_status', 'メールを送信しました。');

        $this->assertSame(2, $calls);
    }

    /**
     * 同じ宛先に同じ内容を続けて送ろうとしても、メールは1通だけ。2通目も送信済みとして一覧に戻る（AC-14）
     */
    #[Test]
    public function 同じ内容の連続した送信は1通だけ送る(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = User::factory()->create();

        $this->sendToUser($user)->assertSessionHas('list_status', 'メールを送信しました。');
        $this->sendToUser($user)->assertRedirect(route('admin.users.index'))->assertSessionHas('list_status', 'メールを送信しました。');

        Mail::assertSentCount(1);
    }

    /**
     * 内容か宛先が違えば、続けて送っても別のメールとして送る
     */
    #[Test]
    public function 内容や宛先が違えば続けて送れる(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->sendToUser($user);
        $this->sendToUser($user, ['mail_body' => '別の本文']);
        $this->sendToUser($user, ['mail_title' => '別のタイトル']);
        $this->sendToUser($other);

        Mail::assertSentCount(4);
    }

    /**
     * CSRFトークンが古い（419）とき、エラー画面を出さずにメール作成ページへ戻り、入力と戻り先を残して赤字で案内する。メールは送らない
     */
    #[Test]
    public function トークンが古いときはメール作成ページに入力を残して戻る(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->create();
        $user = User::factory()->create();
        $message = 'ページの有効期限が切れました。もう一度送信してください。';
        $this->app['env'] = 'local';

        foreach ([
            ['user', $user->id, 'admin.mail.user', ['user_id' => $user->id], 'users'],
            ['inquiry', $inquiry->id, 'admin.mail.inquiry', ['inquiry_id' => $inquiry->id], 'inquiries'],
        ] as [$type, $id, $createRoute, $parameters, $list]) {
            $back = route('admin.'.$list.'.search', ['page' => 2], false);
            $create = route($createRoute, $parameters + ['back' => $back]);

            $this->withSession(['_token' => 'valid-token'])
                ->post(route('admin.mail.send'), ['_token' => 'stale-token', 'type' => $type, 'id' => $id, 'mail_title' => '残るタイトル', 'mail_body' => '残る本文', 'back' => $back])
                ->assertRedirect($create)
                ->assertSessionHasErrors(['send' => $message]);

            $html = $this->get($create)->assertOk()->getContent();
            $this->assertStringContainsString('<p class="mt-6 text-base text-danger" role="alert">'.$message.'</p>', $html);
            $this->assertStringContainsString('value="残るタイトル"', $html);
            $this->assertStringContainsString('>残る本文</textarea>', $html);
            $this->assertStringContainsString('<input type="hidden" name="back" value="'.e($back).'">', $html);
        }

        Mail::assertNothingSent();
    }

    /**
     * トークンが古く、宛先のIDが不正なときは、種類が有効ならその種類の一覧へ、種類も不正なら会員一覧へ戻して案内する。管理者がログインしていなければログインページへ
     */
    #[Test]
    public function トークンが古く宛先も不正なときは一覧へ戻る(): void
    {
        Mail::fake();
        $this->app['env'] = 'local';
        $message = 'ページの有効期限が切れました。もう一度送信してください。';

        $this->withSession(['_token' => 'valid-token'])->post(route('admin.mail.send'), ['_token' => 'stale-token', 'type' => 'user', 'id' => 1])
            ->assertRedirect(route('admin.login'));

        $this->loginAsAdmin();
        $cases = [
            [['type' => 'inquiry', 'id' => 'abc'], 'admin.inquiries.index'],
            [['type' => 'inquiry', 'id' => 0], 'admin.inquiries.index'],
            [['type' => 'user', 'id' => ['1']], 'admin.users.index'],
            [['type' => 'user'], 'admin.users.index'],
            [['type' => 'admin', 'id' => 1], 'admin.users.index'],
            [['type' => ['inquiry'], 'id' => 1], 'admin.users.index'],
            [[], 'admin.users.index'],
        ];
        foreach ($cases as [$input, $list]) {
            $this->withSession(['_token' => 'valid-token'])->post(route('admin.mail.send'), ['_token' => 'stale-token', ...$input])
                ->assertRedirect(route($list))
                ->assertSessionHasErrors(['send' => $message]);
        }

        Mail::assertNothingSent();
    }

    /**
     * Alpine が動く前（JS が動かないとき）にタイトル欄で Enter を押しても、確認なしに送信されない。
     * 確認モーダルの送信ボタンは初期状態で disabled で、フォームの暗黙の送信（最初の送信ボタン）が止まる。メール作成ページの他の送信ボタンはない
     */
    #[Test]
    public function 確認モーダルの送信ボタンは初期状態で押せない(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create();

        $html = $this->get(route('admin.mail.user', ['user_id' => $user->id]))->assertOk()->getContent();

        // メール作成のフォームの中だけを見る（ヘッダーのログアウトにも送信ボタンがある）
        $this->assertSame(1, preg_match('#<form\s[^>]*action="'.preg_quote(route('admin.mail.send'), '#').'".*?</form>#s', $html, $form));
        $html = $form[0];
        $this->assertSame(1, preg_match_all('/<button\s[^>]*type="submit"[^>]*>/', $html, $matches));
        $this->assertMatchesRegularExpression('/\sdisabled(\s|=)/', $matches[0][0]);
        $this->assertStringContainsString('x-bind:disabled="sending || ! confirming"', $matches[0][0]);
        // フォームの中の送信ボタンはそれ1つだけ（「送信する」は type="button" で確認を開く）
        $this->assertSame(1, substr_count($html, 'type="submit"'));
    }

    /**
     * 入力エラーで戻した作成ページのURLには戻り先がクエリで付き、再読み込み（前のページがその作成ページ自身で、入力も消えている）しても戻り先を失わない
     */
    #[Test]
    public function 入力エラーで戻したあとの再読み込みでも戻り先を失わない(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $back = route('admin.users.search', ['name' => '山田', 'page' => 2], false);
        $create = route('admin.mail.user', ['user_id' => $user->id]);

        $this->from($create)->sendToUser($user, ['mail_title' => '', 'back' => $back])
            ->assertRedirect(route('admin.mail.user', ['user_id' => $user->id, 'back' => $back]));

        // 1回目の表示で old の入力は消える。再読み込みでは前のページが作成ページ自身になる
        $url = route('admin.mail.user', ['user_id' => $user->id, 'back' => $back]);
        $this->get($url)->assertOk();
        $html = $this->from($url)->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('<input type="hidden" name="back" value="'.e($back).'">', $html);
        $this->assertStringContainsString('href="'.e($back).'"', $html);
    }

    /**
     * 戻り先は「入力に残っていた値 → クエリの back → 前のページ」の順に、その種類の一覧・検索結果として使えるものを採用する
     */
    #[Test]
    public function 戻り先の候補は順に使えるものを採用する(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $create = route('admin.mail.user', ['user_id' => $user->id]);
        $query = route('admin.users.search', ['name' => 'クエリ'], false);
        $previous = route('admin.users.search', ['name' => '前のページ'], false);
        $old = route('admin.users.search', ['name' => '入力'], false);

        // クエリの back は前のページより優先する
        $this->from(url($previous))->get($create.'?back='.urlencode($query))->assertSee('href="'.e($query).'"', false);
        // 入力に残っていた値はクエリより優先する
        $this->withSession(['_old_input' => ['back' => $old]])->from(url($previous))->get($create.'?back='.urlencode($query))->assertSee('href="'.e($old).'"', false);
        // 使えないクエリ（外部のURL・ほかの種類の一覧・配列）は採用せず、前のページに進む（withSession で入れた入力は残るので消す）
        $this->flushSession();
        foreach (['https://evil.example/', route('admin.inquiries.index', absolute: false), '/admin/purchase'] as $bad) {
            $this->from(url($previous))->get($create.'?back='.urlencode($bad))->assertSee('href="'.e($previous).'"', false);
        }
        $this->from(url($previous))->get($create.'?back[]=x')->assertSee('href="'.e($previous).'"', false);
        // どれも使えない（前のページも注文一覧）ときは一覧の1ページ目
        $this->from(route('admin.purchase.index'))->get($create.'?back='.urlencode('https://evil.example/'))->assertSee('href="'.route('admin.users.index', absolute: false).'"', false);
    }

    /**
     * 本文の先頭・末尾の空白（全角スペースの字下げを含む）は消えずにメールに載り、タイトルの前後の空白は取り除かれる
     */
    #[Test]
    public function 本文の字下げは残りタイトルの前後の空白は取り除かれる(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = User::factory()->create();

        $this->sendToUser($user, ['mail_title' => '　 字下げ　 ', 'mail_body' => "　　拝啓\n    本文\n末尾の空白　"])->assertSessionHasNoErrors();

        Mail::assertSent(AdminMessageMail::class, function (AdminMessageMail $mail): bool {
            $mail->assertHasSubject('【しおや農園】字下げ');
            $this->assertSame("　　拝啓\n    本文\n末尾の空白　", $mail->body);
            $mail->assertSeeInText("　　拝啓\n\u{A0}\u{A0}\u{A0}\u{A0}本文\n末尾の空白");

            return true;
        });
    }

    /**
     * 空白だけの本文・タイトル（全角スペース・改行を含む）は、空と同じく「入力してください」のエラーになる
     */
    #[Test]
    public function 空白だけの本文とタイトルは必須のエラーになる(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $user = User::factory()->create();

        foreach (['   ', '　　', " \n　\t", "\n\n"] as $blank) {
            $this->sendToUser($user, ['mail_title' => $blank, 'mail_body' => $blank])
                ->assertSessionHasErrors(['mail_title' => 'メールタイトルを入力してください', 'mail_body' => 'メール本文を入力してください']);
        }

        Mail::assertNothingSent();
    }

    /**
     * タイトル欄の Enter は、日本語入力の変換を確定する Enter（isComposing・keyCode 229）では確認を開かない。フォームは「戻る」の復元で状態を戻す
     */
    #[Test]
    public function タイトル欄の_enterは変換中は何もしない(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create();

        $html = $this->get(route('admin.mail.user', ['user_id' => $user->id]))->assertOk()->getContent();

        $this->assertStringContainsString('x-on:keydown.enter="if ($event.isComposing || $event.keyCode === 229) { return }; $event.preventDefault(); confirming = true"', $html);
        $this->assertStringContainsString('x-on:pageshow.window="confirming = false; sending = false"', $html);
    }

    /**
     * TrimStrings の除外は項目名（mail_body）だけで、ほかのフォームの text などの項目は今までどおり前後の空白が取り除かれる
     */
    #[Test]
    public function 前後の空白を残すのはメール本文の項目だけ(): void
    {
        Route::post('/_trim-test', fn (Request $request) => response()->json($request->only(['text', 'mail_title', 'mail_body'])));

        $this->postJson('/_trim-test', ['text' => '　 他の項目 ', 'mail_title' => '　 タイトル ', 'mail_body' => '　 本文 '])
            ->assertExactJson(['text' => '他の項目', 'mail_title' => 'タイトル', 'mail_body' => '　 本文 ']);
    }
}
