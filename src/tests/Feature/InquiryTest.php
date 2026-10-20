<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * お問い合わせフォーム・サンクスページのテスト
 */
class InquiryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 正しい入力内容
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'last_name' => '山田',
            'first_name' => '太郎',
            'email' => 'sample@example.com',
            'detail' => "直売所はありますか？\n営業時間も知りたいです。",
        ], $overrides);
    }

    /**
     * 未ログインでもフォームが表示され、見出し・入力欄・ボタン・タイトルがある
     */
    #[Test]
    public function 未ログインでもフォームが表示される(): void
    {
        $response = $this->get(route('top.inquiry'));

        $response->assertOk();
        $response->assertViewIs('inquiry');
        $response->assertSee('<title>お問い合わせ | Shioya Farm</title>', false);
        $response->assertSee('お問い合わせフォーム');
        $response->assertSee('お名前');
        $response->assertSee('メールアドレス');
        $response->assertSee('お問い合わせ内容');
        $response->assertSee('送信する');
        $response->assertSee('例：山田');
        $response->assertSee('例：太郎');
        $response->assertSee('例：sample@example.com');
        $response->assertSee('例：直売所はあるのか知りたい');
        $response->assertSee('action="'.route('top.inquiry.store').'"', false);
        $response->assertSee('novalidate', false);
    }

    /**
     * フォームには共通ヘッダー（ナビ）と共通フッターがある
     */
    #[Test]
    public function フォームには共通ヘッダーとフッターがある(): void
    {
        $response = $this->get(route('top.inquiry'));

        $response->assertSee('オンラインショップ');
        $response->assertSee('しおや農園について');
        $response->assertSee('送料案内');
        $response->assertSee('利用規約');
    }

    /**
     * ログイン中はヘッダーにマイページが表示される
     */
    #[Test]
    public function ログイン中はヘッダーにマイページが表示される(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('top.inquiry'));

        $response->assertOk();
        $response->assertSee('マイページ');
    }

    /**
     * トップページからお問い合わせフォームへのリンクがある
     */
    #[Test]
    public function トップページにフォームへのリンクがある(): void
    {
        $this->get(route('top.index'))
            ->assertSee('href="'.route('top.inquiry').'"', false);
    }

    /**
     * 正しく送信すると未対応の状態で保存し、サンクスページへリダイレクトする
     */
    #[Test]
    public function 送信すると未対応で保存されサンクスページへ移動する(): void
    {
        $input = $this->validInput();

        $response = $this->post(route('top.inquiry.store'), $input);

        $response->assertRedirect(route('top.inquiry.thanks'));
        $this->assertDatabaseCount('inquiries', 1);
        $this->assertDatabaseHas('inquiries', [
            'last_name' => '山田',
            'first_name' => '太郎',
            'email' => 'sample@example.com',
            'detail' => $input['detail'],
            'addressed' => false,
        ]);
    }

    /**
     * 送信にaddressedを含めても対応済みにはならない
     */
    #[Test]
    public function addressedを送っても対応済みにならない(): void
    {
        $this->post(route('top.inquiry.store'), $this->validInput(['addressed' => true]));

        $this->assertDatabaseCount('inquiries', 1);
        $this->assertFalse((bool) Inquiry::first()->addressed);
    }

    /**
     * 送信直後はサンクスページが表示され、共通ヘッダー・フッターは表示されない
     */
    #[Test]
    public function 送信直後のサンクスページが表示されヘッダーとフッターは無い(): void
    {
        $response = $this->followingRedirects()->post(route('top.inquiry.store'), $this->validInput());

        $response->assertOk();
        $response->assertViewIs('inquiry_thanks');
        $response->assertSee('<title>お問い合わせ完了 | Shioya Farm</title>', false);
        $response->assertSee('Thank you !');
        $response->assertSee('お問い合わせありがとうございます。');
        $response->assertSee('3営業日以内にご連絡いたします。');
        $response->assertSee('ホームに戻る');
        $response->assertSee('href="'.route('top.index').'"', false);
        $response->assertDontSee('<header', false);
        $response->assertDontSee('<footer', false);
        $response->assertDontSee('オンラインショップ');
        $response->assertDontSee('送料案内');
    }

    /**
     * サンクスページを直接開くとフォームへリダイレクトする
     */
    #[Test]
    public function サンクスページを直接開くとフォームへ戻る(): void
    {
        $this->get(route('top.inquiry.thanks'))->assertRedirect(route('top.inquiry'));
    }

    /**
     * サンクスページを再読み込みするとフォームへリダイレクトし、二重に保存されない
     */
    #[Test]
    public function サンクスページを再読み込みするとフォームへ戻り二重保存されない(): void
    {
        $this->post(route('top.inquiry.store'), $this->validInput())
            ->assertRedirect(route('top.inquiry.thanks'));

        $this->get(route('top.inquiry.thanks'))->assertOk();

        $this->get(route('top.inquiry.thanks'))->assertRedirect(route('top.inquiry'));
        $this->assertDatabaseCount('inquiries', 1);
    }

    /**
     * 必須項目の組み合わせ（項目名とエラーメッセージ）
     *
     * @return array<string, array{string, string}>
     */
    public static function 必須項目の組み合わせ(): array
    {
        return [
            '姓' => ['last_name', '姓を入力してください'],
            '名' => ['first_name', '名を入力してください'],
            'メールアドレス' => ['email', 'メールアドレスを入力してください'],
            'お問い合わせ内容' => ['detail', 'お問い合わせ内容を入力してください'],
        ];
    }

    /**
     * 必須項目が未入力だとエラーになり、保存されない
     */
    #[Test]
    #[DataProvider('必須項目の組み合わせ')]
    public function 必須項目が未入力だとエラーになる(string $field, string $message): void
    {
        $response = $this->from(route('top.inquiry'))
            ->post(route('top.inquiry.store'), $this->validInput([$field => '']));

        $response->assertRedirect(route('top.inquiry'));
        $response->assertSessionHasErrors([$field => $message]);
        $this->assertDatabaseCount('inquiries', 0);
    }

    /**
     * 何も入力せずに送信すると4項目すべてのエラーが各入力欄の近くに表示される
     */
    #[Test]
    public function 何も入力しないと全項目のエラーが表示される(): void
    {
        $response = $this->followingRedirects()->post(route('top.inquiry.store'), []);

        $response->assertSee('姓を入力してください');
        $response->assertSee('名を入力してください');
        $response->assertSee('メールアドレスを入力してください');
        $response->assertSee('お問い合わせ内容を入力してください');
        $this->assertDatabaseCount('inquiries', 0);
    }

    /**
     * メールアドレスの形式が正しくないとエラーになる
     */
    #[Test]
    public function メールアドレスの形式が正しくないとエラーになる(): void
    {
        $response = $this->post(route('top.inquiry.store'), $this->validInput(['email' => 'test']));

        $response->assertSessionHasErrors(['email' => 'メールアドレスは有効なメールアドレス形式で入力してください']);
        $this->assertDatabaseCount('inquiries', 0);
    }

    /**
     * メールアドレスは30文字まで保存でき、31文字はエラーになる
     */
    #[Test]
    public function メールアドレスの文字数の境界値(): void
    {
        $suffix = '@example.com'; // 12文字
        $ok = str_repeat('a', 30 - strlen($suffix)).$suffix;
        $ng = str_repeat('a', 31 - strlen($suffix)).$suffix;
        $this->assertSame(30, strlen($ok));
        $this->assertSame(31, strlen($ng));

        $this->post(route('top.inquiry.store'), $this->validInput(['email' => $ng]))
            ->assertSessionHasErrors(['email' => 'メールアドレスは30文字以内で入力してください']);
        $this->assertDatabaseCount('inquiries', 0);

        $this->post(route('top.inquiry.store'), $this->validInput(['email' => $ok]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inquiries', ['email' => $ok]);
    }

    /**
     * 姓・名は255文字まで保存でき、256文字はエラーになる
     */
    #[Test]
    public function 姓と名の文字数の境界値(): void
    {
        $this->post(route('top.inquiry.store'), $this->validInput([
            'last_name' => str_repeat('あ', 256),
            'first_name' => str_repeat('い', 256),
        ]))->assertSessionHasErrors([
            'last_name' => '姓は255文字以内で入力してください',
            'first_name' => '名は255文字以内で入力してください',
        ]);
        $this->assertDatabaseCount('inquiries', 0);

        $this->post(route('top.inquiry.store'), $this->validInput([
            'last_name' => str_repeat('あ', 255),
            'first_name' => str_repeat('い', 255),
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inquiries', [
            'last_name' => str_repeat('あ', 255),
            'first_name' => str_repeat('い', 255),
        ]);
    }

    /**
     * お問い合わせ内容は2000文字まで保存でき、2001文字はエラーになる
     */
    #[Test]
    public function お問い合わせ内容の文字数の境界値(): void
    {
        $this->post(route('top.inquiry.store'), $this->validInput(['detail' => str_repeat('あ', 2001)]))
            ->assertSessionHasErrors(['detail' => 'お問い合わせ内容は2000文字以内で入力してください']);
        $this->assertDatabaseCount('inquiries', 0);

        $this->post(route('top.inquiry.store'), $this->validInput(['detail' => str_repeat('あ', 2000)]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inquiries', ['detail' => str_repeat('あ', 2000)]);
    }

    /**
     * 1分あたり10回までは送信でき、超えた回は保存せずに入力を残してフォームへ戻し、メッセージを表示する
     */
    #[Test]
    public function 送信は1分あたり10回までで超えるとフォームへ戻る(): void
    {
        foreach (range(1, 10) as $i) {
            $this->post(route('top.inquiry.store'), $this->validInput())
                ->assertRedirect(route('top.inquiry.thanks'));
        }

        $input = $this->validInput(['detail' => "11回目の問い合わせ\n2行目"]);
        $response = $this->post(route('top.inquiry.store'), $input);

        $response->assertRedirect(route('top.inquiry'));
        $this->assertDatabaseCount('inquiries', 10);

        $page = $this->get(route('top.inquiry'));
        $page->assertOk();
        $page->assertSee('送信回数が多すぎます。しばらく時間をおいてから<br class="hidden md:inline">再度お試しください。', false);
        $page->assertSee('value="山田"', false);
        $page->assertSee('value="sample@example.com"', false);
        $page->assertSee("11回目の問い合わせ\n2行目", false);
    }

    /**
     * 回数制限のメッセージは、制限にかかったときだけ表示される
     */
    #[Test]
    public function 通常はフォームに回数制限のメッセージが出ない(): void
    {
        $this->get(route('top.inquiry'))->assertDontSee('送信回数が多すぎます');
    }

    /**
     * 回数制限はお問い合わせの送信だけにかかり、他のページには影響しない
     */
    #[Test]
    public function 回数制限は他のページに影響しない(): void
    {
        foreach (range(1, 11) as $i) {
            $this->post(route('top.inquiry.store'), $this->validInput());
        }

        $this->get(route('top.inquiry'))->assertOk();
        $this->get(route('top.index'))->assertOk();
        $this->get(route('shop.items.index'))->assertOk();
    }

    /**
     * 送信ボタンは送信中に押せなくなる設定になっている（二重送信の防止）
     */
    #[Test]
    public function 送信ボタンは送信中に無効になる設定がある(): void
    {
        $response = $this->get(route('top.inquiry'));

        $response->assertSee('x-data="{ sending: false }"', false);
        $response->assertSee('x-on:submit="sending = true"', false);
        $response->assertSee('x-bind:disabled="sending"', false);
    }

    /**
     * エラーで戻ったとき、入力した内容（改行を含む）が残っている
     */
    #[Test]
    public function エラーで戻ると入力内容が残っている(): void
    {
        $input = $this->validInput(['last_name' => '']);

        $response = $this->followingRedirects()->from(route('top.inquiry'))
            ->post(route('top.inquiry.store'), $input);

        $response->assertSee('value="太郎"', false);
        $response->assertSee('value="sample@example.com"', false);
        $response->assertSee("直売所はありますか？\n営業時間も知りたいです。", false);
    }

    /**
     * 項目に配列を送るとエラーになり、フォームページも500にならずに表示される
     */
    #[Test]
    #[DataProvider('必須項目の組み合わせ')]
    public function 項目に配列を送るとエラーになりフォームが表示される(string $field): void
    {
        $response = $this->followingRedirects()->from(route('top.inquiry'))
            ->post(route('top.inquiry.store'), $this->validInput([$field => ['x']]));

        $response->assertOk();
        $response->assertViewIs('inquiry');
        $this->assertDatabaseCount('inquiries', 0);
    }

    /**
     * HTMLを含む入力はそのまま保存され、エラー画面ではエスケープして表示される
     */
    #[Test]
    public function htm_lを含む入力は文字列として保存されエスケープされる(): void
    {
        $script = '<script>alert(1)</script>';

        $this->post(route('top.inquiry.store'), $this->validInput(['detail' => $script]));
        $this->assertDatabaseHas('inquiries', ['detail' => $script]);

        $response = $this->followingRedirects()->post(route('top.inquiry.store'), $this->validInput([
            'last_name' => '',
            'detail' => $script,
        ]));

        $response->assertSee(e($script), false);
        $response->assertDontSee($script, false);
    }
}
