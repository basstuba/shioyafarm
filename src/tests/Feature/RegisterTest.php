<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Item;
use App\Models\User;
use Database\Seeders\AreasTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 会員登録のテスト（Fortifyのルートを使う）
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['北海道', '東京都', '大阪府'] as $prefecture) {
            Area::factory()->create(['prefecture' => $prefecture]);
        }
    }

    /**
     * 正しい入力値を返す（上書きしたい項目だけ $overrides で渡す）
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'last_name' => '山田',
            'first_name' => '太郎',
            'prefecture' => '東京都',
            'city' => '千代田区千代田1-1',
            'tell' => '09011112222',
            'email' => 'new-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    /**
     * 登録ページへのPOSTを送り、エラー時に戻る先を登録ページにする
     *
     * @param  array<string, mixed>  $overrides
     */
    private function register(array $overrides = []): TestResponse
    {
        return $this->from(route('register'))->post(route('register.store'), $this->validInput($overrides));
    }

    /**
     * 未ログインで会員登録ページが表示される
     */
    #[Test]
    public function 未ログインで会員登録ページが表示される(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertViewIs('auth.register');
        $response->assertSee('<title>会員登録 | Shioya Farm</title>', false);
        $response->assertSee('会員登録');
        foreach (['last_name', 'first_name', 'prefecture', 'city', 'tell', 'email', 'password', 'password_confirmation'] as $name) {
            $response->assertSee('name="'.$name.'"', false);
        }
        $response->assertSee('action="'.route('register.store').'"', false);
        $response->assertSee('登録する');
        $response->assertSee('&copy;Shioya farm', false);
        foreach (['例：山田', '例：太郎', '例：適当市適当町３４－５６', '例：09012345678', '例：sample@example.com', '例：demo12345'] as $placeholder) {
            $response->assertSee('placeholder="'.$placeholder.'"', false);
        }
        $response->assertSeeInOrder(['name="password"', 'type="password"'], false);
        $response->assertSeeInOrder(['name="password_confirmation"', 'type="password"'], false);
    }

    /**
     * 会員登録ページは共通ヘッダー・フッターではなく認証ページ用の構成で表示され、ログインページへの導線がある
     */
    #[Test]
    public function 会員登録ページは認証ページ用のレイアウトで表示される(): void
    {
        $response = $this->get(route('register'));

        $response->assertSee('href="'.route('top.index').'"', false);
        $response->assertSee('href="'.route('login').'"', false);
        $response->assertSee('すでに会員登録がお済みの方は、');
        $response->assertDontSee('お知らせ');
        $response->assertDontSee('送料案内');
    }

    /**
     * お住まいの地域のセレクトボックスは「選択してください」と、areasテーブルの都道府県をID順に表示する
     */
    #[Test]
    public function 都道府県のセレクトボックスはareasの内容を表示する(): void
    {
        $response = $this->get(route('register'));

        $response->assertViewHas('prefectures', fn ($prefectures) => $prefectures->all() === ['北海道', '東京都', '大阪府']);
        $response->assertSeeInOrder([
            '<option value="">選択してください</option>',
            '<option class="text-ink" value="北海道"',
            '<option class="text-ink" value="東京都"',
            '<option class="text-ink" value="大阪府"',
        ], false);
    }

    /**
     * 47都道府県のシーダーの内容がセレクトボックスの選択肢になる
     */
    #[Test]
    public function シーダーの47都道府県が選択肢になる(): void
    {
        Area::query()->delete();
        $this->seed(AreasTableSeeder::class);

        $response = $this->get(route('register'));

        $response->assertViewHas('prefectures', fn ($prefectures) => $prefectures->count() === 47 && $prefectures->first() === '北海道' && $prefectures->last() === '沖縄県');
    }

    /**
     * 正しい入力で会員が登録され、ログイン状態で商品一覧ページへ遷移する（直前のページが無いとき）
     */
    #[Test]
    public function 正しい入力で会員登録するとログインして商品一覧ページへ遷移する(): void
    {
        $response = $this->register();

        $response->assertRedirect(route('shop.items.index'));
        $this->assertDatabaseHas('users', [
            'last_name' => '山田',
            'first_name' => '太郎',
            'prefecture' => '東京都',
            'city' => '千代田区千代田1-1',
            'tell' => '09011112222',
            'email' => 'new-user@example.com',
        ]);
        $this->assertDatabaseCount('users', 1);
        $this->assertAuthenticatedAs(User::where('email', 'new-user@example.com')->firstOrFail());
    }

    /**
     * 商品詳細ページから会員登録ページを開いた場合は、登録後に商品詳細ページへ戻る
     */
    #[Test]
    public function 会員登録後は登録ページを開く前のページへ戻る(): void
    {
        $item = Item::factory()->create();
        $this->get(route('shop.items.show', $item))->assertOk();
        $this->get(route('register'))->assertOk();

        $this->register()->assertRedirect(route('shop.items.show', $item));
    }

    /**
     * ログインページから会員登録ページへ移った場合も、ログインページを開く前のページへ戻る
     */
    #[Test]
    public function ログインページを経由しても会員登録後は元のページへ戻る(): void
    {
        $this->get(route('info.shipping-fee'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();

        $this->register()->assertRedirect(route('info.shipping-fee'));
    }

    /**
     * 入力エラーで会員登録ページに戻っても、戻り先は元のページのまま変わらない
     */
    #[Test]
    public function 入力エラーの後に会員登録しても元のページへ戻る(): void
    {
        $this->get(route('info.shipping-fee'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->register(['email' => ''])->assertRedirect(route('register'));
        $this->get(route('register'))->assertOk();

        $this->register()->assertRedirect(route('info.shipping-fee'));
    }

    /**
     * パスワードはハッシュ化して保存され、登録した情報でログインできる
     */
    #[Test]
    public function パスワードはハッシュ化されて保存されログインできる(): void
    {
        $this->register();

        $user = User::where('email', 'new-user@example.com')->firstOrFail();
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));

        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => 'new-user@example.com', 'password' => 'password123'])
            ->assertRedirect(route('shop.items.index'));
        $this->assertAuthenticatedAs($user);
    }

    /**
     * メールアドレスは小文字にそろえて保存される（Fortifyのlowercase_usernames設定）
     */
    #[Test]
    public function メールアドレスは小文字にそろえて保存される(): void
    {
        $this->register(['email' => 'New-User@Example.com'])->assertRedirect(route('shop.items.index'));

        $this->assertDatabaseHas('users', ['email' => 'new-user@example.com']);
    }

    /**
     * 境界値（姓名・ご住所255文字、メール30文字、電話番号10桁と11桁、パスワード8文字）は登録できる
     */
    #[Test]
    public function 境界値の入力は登録できる(): void
    {
        $email = str_repeat('a', 30 - strlen('@example.com')).'@example.com';

        $this->register([
            'last_name' => str_repeat('あ', 255),
            'first_name' => str_repeat('い', 255),
            'city' => str_repeat('う', 255),
            'tell' => '0312345678',
            'email' => $email,
            'password' => 'pass1234',
            'password_confirmation' => 'pass1234',
        ])->assertRedirect(route('shop.items.index'))->assertSessionHasNoErrors();

        $this->assertSame(30, strlen($email));
        $this->assertDatabaseHas('users', ['email' => $email, 'tell' => '0312345678']);
    }

    /**
     * 何も入力しない場合は各項目の必須エラーになり、登録されない
     */
    #[Test]
    public function 未入力の項目は必須エラーになる(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), []);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'last_name' => '姓を入力してください',
            'first_name' => '名を入力してください',
            'prefecture' => 'お住まいの地域を選択してください',
            'city' => 'ご住所を入力してください',
            'tell' => '電話番号を入力してください',
            'email' => 'メールアドレスを入力してください',
            'password' => 'パスワードを入力してください',
        ]);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * 未選択（空文字）の都道府県は「選択してください」のエラーになる
     */
    #[Test]
    public function 都道府県が未選択の場合はエラーになる(): void
    {
        $this->register(['prefecture' => ''])
            ->assertSessionHasErrors(['prefecture' => 'お住まいの地域を選択してください']);
        $this->assertGuest();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function 不正な電話番号(): array
    {
        return [
            'ハイフン入り' => ['090-1111-2222'],
            '9桁' => ['090111122'],
            '12桁' => ['090111122223'],
            '全角数字' => ['０９０１１１１２２２２'],
            '英字を含む' => ['0901111222a'],
            '数字以外の文字を含む' => ['09011112222x'],
        ];
    }

    /**
     * 電話番号がハイフン入り・全角・桁数違いの場合はエラーになる
     */
    #[Test]
    #[DataProvider('不正な電話番号')]
    public function 電話番号の形式が正しくない場合はエラーになる(string $tell): void
    {
        $this->register(['tell' => $tell])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['tell' => '電話番号はハイフンなしの半角数字10〜11桁で入力してください']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * メールアドレスの形式が正しくない場合はエラーになる
     */
    #[Test]
    public function メールアドレスの形式が正しくない場合はエラーになる(): void
    {
        $this->register(['email' => 'test'])
            ->assertSessionHasErrors(['email' => 'メールアドレスは有効なメールアドレス形式で入力してください']);
        $this->assertGuest();
    }

    /**
     * メールアドレスが31文字以上の場合はエラーになる
     */
    #[Test]
    public function メールアドレスが31文字の場合はエラーになる(): void
    {
        $email = str_repeat('a', 31 - strlen('@example.com')).'@example.com';

        $this->assertSame(31, strlen($email));
        $this->register(['email' => $email])
            ->assertSessionHasErrors(['email' => 'メールアドレスは30文字以内で入力してください']);
        $this->assertGuest();
    }

    /**
     * 登録済みのメールアドレスの場合はエラーになり、既存の会員は変更されない
     */
    #[Test]
    public function 登録済みのメールアドレスの場合はエラーになる(): void
    {
        User::factory()->create(['email' => 'test@test.com', 'last_name' => '既存']);

        $this->register(['email' => 'test@test.com'])
            ->assertSessionHasErrors(['email' => 'このメールアドレスはすでに登録されています']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['email' => 'test@test.com', 'last_name' => '既存']);
    }

    /**
     * パスワードが8文字未満の場合はエラーになる
     */
    #[Test]
    public function パスワードが8文字未満の場合はエラーになる(): void
    {
        $this->register(['password' => 'pass123', 'password_confirmation' => 'pass123'])
            ->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);
        $this->assertGuest();
    }

    /**
     * 確認用パスワードと一致しない場合は、パスワードの項目にエラーが付く
     */
    #[Test]
    public function 確認用パスワードと一致しない場合はエラーになる(): void
    {
        $this->register(['password' => 'password123', 'password_confirmation' => 'password124'])
            ->assertSessionHasErrors(['password' => 'パスワードと確認用パスワードが一致しません']);
        $this->assertGuest();
    }

    /**
     * 姓・名・ご住所が256文字の場合はエラーになる
     */
    #[Test]
    public function 姓名とご住所が256文字の場合はエラーになる(): void
    {
        $this->register([
            'last_name' => str_repeat('あ', 256),
            'first_name' => str_repeat('い', 256),
            'city' => str_repeat('う', 256),
        ])->assertSessionHasErrors([
            'last_name' => '姓は255文字以内で入力してください',
            'first_name' => '名は255文字以内で入力してください',
            'city' => 'ご住所は255文字以内で入力してください',
        ]);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * 一覧にない都道府県を送ると、一覧から選択するようエラーになる
     */
    #[Test]
    public function 一覧にない都道府県はエラーになる(): void
    {
        $this->register(['prefecture' => '架空県'])
            ->assertSessionHasErrors(['prefecture' => 'お住まいの地域を一覧から選択してください']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function 配列で送る項目(): array
    {
        return [
            '姓' => ['last_name', '姓は文字で入力してください'],
            '名' => ['first_name', '名は文字で入力してください'],
            'お住まいの地域' => ['prefecture', 'お住まいの地域は文字で入力してください'],
            'ご住所' => ['city', 'ご住所は文字で入力してください'],
            '電話番号' => ['tell', '電話番号は文字で入力してください'],
            'メールアドレス' => ['email', 'メールアドレスは文字で入力してください'],
            'パスワード' => ['password', 'パスワードは文字で入力してください'],
        ];
    }

    /**
     * 項目に配列を送っても500にならず、エラーで戻った会員登録ページも表示される
     */
    #[Test]
    #[DataProvider('配列で送る項目')]
    public function 項目に配列を送るとエラーになり登録ページも表示される(string $field, string $message): void
    {
        $values = ['last_name' => '山田', 'prefecture' => '東京都', 'email' => 'new-user@example.com'];
        $arrayValue = [$values[$field] ?? 'x'];

        $response = $this->register([$field => $arrayValue]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([$field => $message]);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);

        // old入力に配列が入った状態でリダイレクト先の登録ページを開いても500にならない
        $this->get(route('register'))
            ->assertOk()
            ->assertSee($message);
    }

    /**
     * 全項目に配列を送っても500にならず、エラーで戻った会員登録ページに各エラーが表示される
     */
    #[Test]
    public function 全項目に配列を送るとエラーになり登録ページも表示される(): void
    {
        $response = $this->followingRedirects()->register([
            'last_name' => ['x'],
            'first_name' => ['x'],
            'prefecture' => ['東京都'],
            'city' => ['x'],
            'tell' => ['09011112222'],
            'email' => ['new-user@example.com'],
            'password' => ['password123'],
        ]);

        $response->assertOk();
        $response->assertViewIs('auth.register');
        foreach (self::配列で送る項目() as [, $message]) {
            $response->assertSee($message);
        }
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * エラーで戻ったとき、パスワード以外の入力（選んだ都道府県を含む）は残り、パスワードは残らない
     */
    #[Test]
    public function エラーで戻ったときパスワード以外の入力が保持される(): void
    {
        $response = $this->followingRedirects()->register([
            'prefecture' => '大阪府',
            'password' => 'secret-password-1',
            'password_confirmation' => 'secret-password-2',
        ]);

        $response->assertSee('パスワードと確認用パスワードが一致しません');
        $response->assertSee('value="山田"', false);
        $response->assertSee('value="太郎"', false);
        $response->assertSee('<option class="text-ink" value="大阪府" selected>大阪府</option>', false);
        $response->assertDontSee('<option class="text-ink" value="東京都" selected>', false);
        $response->assertSee('value="千代田区千代田1-1"', false);
        $response->assertSee('value="09011112222"', false);
        $response->assertSee('value="new-user@example.com"', false);
        $response->assertDontSee('secret-password-1');
        $response->assertDontSee('secret-password-2');
    }

    /**
     * エラーメッセージは該当する入力欄に表示される（aria属性付き）
     */
    #[Test]
    public function エラーメッセージが入力欄の近くに表示される(): void
    {
        $response = $this->followingRedirects()->from(route('register'))->post(route('register.store'), []);

        $response->assertSee('id="last_name-error"', false);
        $response->assertSee('id="prefecture-error"', false);
        $response->assertSee('aria-describedby="prefecture-error"', false);
        $response->assertSee('お住まいの地域を選択してください');
        $response->assertSee('パスワードを入力してください');
    }

    /**
     * ログイン中に会員登録ページを開くとトップページへ移動する
     */
    #[Test]
    public function ログイン済みで会員登録ページを開くとトップページへ移動する(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('register'))->assertRedirect(route('top.index'));
    }

    /**
     * ログイン中は会員登録のPOSTもトップページへ移動し、会員は増えない
     */
    #[Test]
    public function ログイン済みで会員登録を送信しても登録されない(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('register.store'), $this->validInput())->assertRedirect(route('top.index'));

        $this->assertDatabaseCount('users', 1);
    }
}
