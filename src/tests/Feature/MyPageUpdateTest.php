<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * 登録情報変更ページ（GET /mypage/edit）と登録情報の更新（POST /mypage/update）のテスト
 */
class MyPageUpdateTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;

    /** 変更前のパスワード */
    private const string OLD_PASSWORD = 'oldpassword1';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeAreas(['北海道', '東京都', '沖縄県']);
        $this->user = User::factory()->create([
            'last_name' => '塩谷',
            'first_name' => '花子',
            'prefecture' => '北海道',
            'city' => '札幌市中央区1-1-1',
            'tell' => '09012345678',
            'email' => 'hanako@example.com',
            'password' => Hash::make(self::OLD_PASSWORD),
        ]);
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
            'prefecture' => '沖縄県',
            'city' => 'UAT変更町1-2-3',
            'tell' => '08011112222',
            'email' => 'changed@example.com',
            'current_password' => self::OLD_PASSWORD,
            'password' => '',
            'password_confirmation' => '',
        ], $overrides);
    }

    /**
     * 登録情報変更ページへのPOSTを送り、エラー時に戻る先を登録情報変更ページにする
     *
     * @param  array<string, mixed>  $overrides
     */
    private function update(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->user)
            ->from(route('shop.mypage.edit'))
            ->post(route('shop.mypage.update'), $this->validInput($overrides));
    }

    // ---- 登録情報変更ページ ----

    /**
     * 現在の内容が入った状態で開く（パスワード欄は空）
     */
    #[Test]
    public function 登録情報変更ページに現在の内容が入っている(): void
    {
        $response = $this->actingAs($this->user)->get(route('shop.mypage.edit'));

        $response->assertOk();
        $response->assertViewIs('shop.user_edit');
        $response->assertSee('action="'.route('shop.mypage.update').'"', false);
        $response->assertSee('name="last_name"', false);
        $response->assertSee('value="塩谷"', false);
        $response->assertSee('value="花子"', false);
        $response->assertSee('value="札幌市中央区1-1-1"', false);
        $response->assertSee('value="09012345678"', false);
        $response->assertSee('value="hanako@example.com"', false);
        // 都道府県は現在の地域が選択されている
        $this->assertMatchesRegularExpression('/<option [^>]*value="北海道"\s+selected\s*>/u', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/<option [^>]*value="沖縄県"\s+selected/u', $response->getContent());
        $response->assertSee('value="沖縄県"', false);
        // パスワード欄は空（値を出さない）
        $response->assertSeeInOrder(['name="password"', 'type="password"'], false);
        $response->assertSeeInOrder(['name="password_confirmation"', 'type="password"'], false);
        $response->assertDontSee(self::OLD_PASSWORD);
        $response->assertSeeInOrder(['現在のパスワード', 'name="current_password"', 'type="password"', 'メールアドレス・パスワードを変更する場合は入力してください。', 'name="password"'], false);
        $response->assertSee('変更する');
        $response->assertSee('マイページに戻る');
        $response->assertSee('href="'.route('shop.mypage.index').'"', false);
    }

    // ---- 更新 ----

    /**
     * 変更するとマイページへ移動し、メッセージと変更後の内容が表示される。パスワードは空のままなら変わらない
     */
    #[Test]
    public function 変更するとマイページへ移動し内容が変わる(): void
    {
        $response = $this->update();

        $response->assertRedirect(route('shop.mypage.index'));
        $response->assertSessionHas('flash_message', '登録情報を変更しました');

        $this->user->refresh();
        $this->assertSame('山田', $this->user->last_name);
        $this->assertSame('太郎', $this->user->first_name);
        $this->assertSame('沖縄県', $this->user->prefecture);
        $this->assertSame('UAT変更町1-2-3', $this->user->city);
        $this->assertSame('08011112222', $this->user->tell);
        $this->assertSame('changed@example.com', $this->user->email);
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->user->password));

        $page = $this->get(route('shop.mypage.index'));
        $page->assertSee('登録情報を変更しました');
        $page->assertSee('山田 太郎');
        $page->assertSee('沖縄県UAT変更町1-2-3');
        $page->assertSee('08011112222');
        $page->assertSee('changed@example.com');
    }

    /**
     * 今の登録内容をそのまま送るための入力値
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sameInput(array $overrides = []): array
    {
        return [
            'last_name' => '塩谷',
            'first_name' => '花子',
            'prefecture' => '北海道',
            'city' => '札幌市中央区1-1-1',
            'tell' => '09012345678',
            'email' => 'hanako@example.com',
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
            ...$overrides,
        ];
    }

    /**
     * 何も変えずに送ると、保存せず（UPDATEは0回）登録情報変更ページに戻り、「変更された項目はありません」と表示する
     */
    #[Test]
    public function 何も変えないときは保存せず登録情報変更ページに戻る(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($this->user)
            ->from(route('shop.mypage.edit'))
            ->post(route('shop.mypage.update'), $this->sameInput());
        $updates = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with($query['query'], 'update '));
        DB::disableQueryLog();

        $response->assertRedirect(route('shop.mypage.edit'));
        $response->assertSessionHas('info_message', '変更された項目はありません');
        $response->assertSessionMissing('flash_message');
        $response->assertSessionHasNoErrors();
        $this->assertCount(0, $updates);

        $page = $this->get(route('shop.mypage.edit'));
        $page->assertSee('変更された項目はありません');
        $page->assertSee('role="status"', false);
        $page->assertSee('value="札幌市中央区1-1-1"', false);
    }

    /**
     * 変更があるときは従来どおりマイページへ移動し「登録情報を変更しました」と表示する（1項目だけでも）
     */
    #[Test]
    public function 一項目だけ変えるとマイページへ移動して変更メッセージが出る(): void
    {
        $response = $this->actingAs($this->user)
            ->from(route('shop.mypage.edit'))
            ->post(route('shop.mypage.update'), $this->sameInput(['tell' => '08011112222']));

        $response->assertRedirect(route('shop.mypage.index'));
        $response->assertSessionHas('flash_message', '登録情報を変更しました');
        $response->assertSessionMissing('info_message');
        $this->assertSame('08011112222', $this->user->fresh()->tell);
    }

    /**
     * 項目は同じで、パスワードだけ入力した（現在のパスワードあり）ときは変更ありとして保存する
     */
    #[Test]
    public function パスワードだけ入力したときは変更ありになる(): void
    {
        $this->actingAs($this->user)
            ->from(route('shop.mypage.edit'))
            ->post(route('shop.mypage.update'), $this->sameInput([
                'current_password' => self::OLD_PASSWORD,
                'password' => 'newpassword9',
                'password_confirmation' => 'newpassword9',
            ]))
            ->assertRedirect(route('shop.mypage.index'))
            ->assertSessionHas('flash_message', '登録情報を変更しました');

        $this->assertTrue(Hash::check('newpassword9', $this->user->fresh()->password));
    }

    /**
     * メールアドレスが大文字小文字だけ違うときは変更ありとして保存する（現在のパスワードは不要のまま）
     */
    #[Test]
    public function メールアドレスの大文字小文字だけの違いは変更ありになる(): void
    {
        $this->actingAs($this->user)
            ->from(route('shop.mypage.edit'))
            ->post(route('shop.mypage.update'), $this->sameInput(['email' => 'Hanako@Example.com']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('shop.mypage.index'))
            ->assertSessionHas('flash_message', '登録情報を変更しました');

        $this->assertSame('Hanako@Example.com', $this->user->fresh()->email);
    }

    /**
     * 前後の空白だけの違いは変更なしになる（TrimStrings で除かれる）
     */
    #[Test]
    public function 前後の空白だけの違いは変更なしになる(): void
    {
        $this->actingAs($this->user)
            ->from(route('shop.mypage.edit'))
            ->post(route('shop.mypage.update'), $this->sameInput(['city' => '  札幌市中央区1-1-1 ', 'email' => ' hanako@example.com ']))
            ->assertRedirect(route('shop.mypage.edit'))
            ->assertSessionHas('info_message', '変更された項目はありません');
    }

    /**
     * パスワードを入力したときは新しいパスワードになる
     */
    #[Test]
    public function パスワードを入力すると新しいパスワードになる(): void
    {
        $this->update(['password' => 'newpassword9', 'password_confirmation' => 'newpassword9'])
            ->assertRedirect(route('shop.mypage.index'));

        $this->user->refresh();
        $this->assertTrue(Hash::check('newpassword9', $this->user->password));
        $this->assertFalse(Hash::check(self::OLD_PASSWORD, $this->user->password));
        $this->assertNotSame('newpassword9', $this->user->password);
    }

    /**
     * 自分のメールアドレスのままなら重複エラーにならない
     */
    #[Test]
    public function 自分のメールアドレスのままなら重複エラーにならない(): void
    {
        $this->update(['email' => 'hanako@example.com'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('shop.mypage.index'));
    }

    /**
     * 画面から送られても、Stripe の顧客IDやIDは書き換えられない
     */
    #[Test]
    public function 登録情報以外の項目は書き換えられない(): void
    {
        $this->user->forceFill(['stripe_customer_id' => 'cus_original'])->save();

        $this->update(['stripe_customer_id' => 'cus_hacked', 'id' => 99999, 'remember_token' => 'x']);

        $fresh = User::find($this->user->id);
        $this->assertNotNull($fresh);
        $this->assertSame('cus_original', $fresh->stripe_customer_id);
    }

    /**
     * Fortify 標準のパスワード変更（PUT /user/password）は無効で、ルートがない
     */
    #[Test]
    public function fortify標準のパスワード変更のルートはない(): void
    {
        $this->assertFalse(Route::has('user-password.update'));

        $this->actingAs($this->user)
            ->put('/user/password', ['current_password' => self::OLD_PASSWORD, 'password' => 'newpassword9', 'password_confirmation' => 'newpassword9'])
            ->assertNotFound();
    }

    /**
     * 姓名・住所・電話番号だけの変更では、現在のパスワードは不要
     */
    #[Test]
    public function 氏名や住所だけの変更では現在のパスワードは不要(): void
    {
        $this->update(['email' => 'hanako@example.com', 'current_password' => ''])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('shop.mypage.index'));

        $this->assertSame('山田', $this->user->fresh()->last_name);
        $this->assertSame('沖縄県', $this->user->fresh()->prefecture);
    }

    /**
     * メールアドレスが大文字小文字の違いだけなら、変更とみなさず現在のパスワードは不要
     */
    #[Test]
    public function メールアドレスが大文字小文字の違いだけなら現在のパスワードは不要(): void
    {
        $this->update(['email' => 'HANAKO@example.com', 'current_password' => ''])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('shop.mypage.index'));
    }

    /**
     * 現在のパスワードの入力が必要な変更（メールアドレスだけ／パスワードだけ）
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function changesNeedingCurrentPassword(): array
    {
        return [
            'メールアドレスだけ変更' => [['email' => 'changed@example.com']],
            'パスワードだけ変更' => [['email' => 'hanako@example.com', 'password' => 'newpassword9', 'password_confirmation' => 'newpassword9']],
        ];
    }

    /**
     * メールアドレス・パスワードを変えるとき、現在のパスワードが未入力ならエラーになり、変更されない
     *
     * @param  array<string, mixed>  $change
     */
    #[Test]
    #[DataProvider('changesNeedingCurrentPassword')]
    public function 現在のパスワードが未入力だとエラーになる(array $change): void
    {
        $this->update([...$change, 'current_password' => ''])
            ->assertRedirect(route('shop.mypage.edit'))
            ->assertSessionHasErrors(['current_password' => 'メールアドレス・パスワードを変更する場合は、現在のパスワードを入力してください']);

        $this->assertNotChanged();
    }

    /**
     * 現在のパスワードが誤りだとエラーになり、変更されない
     *
     * @param  array<string, mixed>  $change
     */
    #[Test]
    #[DataProvider('changesNeedingCurrentPassword')]
    public function 現在のパスワードが誤りだとエラーになる(array $change): void
    {
        $this->update([...$change, 'current_password' => 'wrongpassword1'])
            ->assertRedirect(route('shop.mypage.edit'))
            ->assertSessionHasErrors(['current_password' => '現在のパスワードが正しくありません']);

        $this->assertNotChanged();
    }

    /**
     * 現在のパスワードが正しければ変更できる
     *
     * @param  array<string, mixed>  $change
     */
    #[Test]
    #[DataProvider('changesNeedingCurrentPassword')]
    public function 現在のパスワードが正しければ変更できる(array $change): void
    {
        $this->update([...$change, 'current_password' => self::OLD_PASSWORD])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('shop.mypage.index'));

        $this->assertSame('山田', $this->user->fresh()->last_name);
    }

    /**
     * 現在のパスワードが配列など文字列でなくても、500にならずエラーになる
     */
    #[Test]
    public function 現在のパスワードが配列でもエラーになる(): void
    {
        $this->update(['current_password' => ['a']])
            ->assertSessionHasErrors(['current_password' => '現在のパスワードは文字で入力してください']);
    }

    /**
     * 現在のパスワードのエラーで戻ったとき、パスワード類以外の入力は残り、パスワードは再表示しない
     */
    #[Test]
    public function 現在のパスワードのエラーで戻ると入力が残りパスワードは表示されない(): void
    {
        $this->update(['city' => '残る住所', 'current_password' => 'wrongpassword1']);

        $response = $this->actingAs($this->user)->get(route('shop.mypage.edit'));

        $response->assertSee('value="残る住所"', false);
        $response->assertSee('現在のパスワードが正しくありません');
        $response->assertDontSee('wrongpassword1');
    }

    /**
     * 登録情報が変更前のままであることを確認する
     */
    private function assertNotChanged(): void
    {
        $fresh = User::find($this->user->id);
        $this->assertNotNull($fresh);
        $this->assertSame('塩谷', $fresh->last_name);
        $this->assertSame('hanako@example.com', $fresh->email);
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $fresh->password));
    }

    /**
     * Fortify 標準の登録情報更新（PUT /user/profile-information）は無効で、ルートがない
     */
    #[Test]
    public function fortify標準の登録情報更新のルートはない(): void
    {
        $this->assertFalse(Route::has('user-profile-information.update'));

        $this->actingAs($this->user)
            ->put('/user/profile-information', ['name' => 'x', 'email' => 'x@example.com'])
            ->assertNotFound();
    }

    /**
     * パスワードを変えると、ほかの端末のセッションはログアウト状態になり、今の端末はログインのまま
     */
    #[Test]
    public function パスワードを変えるとほかの端末のセッションが無効になる(): void
    {
        $oldHash = $this->user->password;
        $loginKey = Auth::guard('web')->getName();

        // 今の端末：ログインして、パスワードを変える
        $this->post(route('login.store'), ['email' => 'hanako@example.com', 'password' => self::OLD_PASSWORD]);
        $this->from(route('shop.mypage.edit'))
            ->post(route('shop.mypage.update'), $this->validInput(['password' => 'newpassword9', 'password_confirmation' => 'newpassword9']))
            ->assertRedirect(route('shop.mypage.index'));

        $this->user->refresh();
        $this->assertNotSame($oldHash, $this->user->password);

        // 今の端末は、変更後もログインのまま
        Auth::forgetGuards();
        $this->get(route('shop.mypage.index'))->assertOk();

        // ほかの端末（変更前のパスワードのハッシュを持つセッション）は、次のリクエストでログアウト状態になる
        Auth::forgetGuards();
        $this->flushSession();
        $this->withSession([$loginKey => $this->user->id, 'password_hash_web' => $oldHash])
            ->get(route('shop.mypage.index'))
            ->assertRedirect(route('login'));
    }

    /**
     * パスワードを変えても、会員の更新は1回の UPDATE で済む（ハッシュ化と保存を重ねない）
     */
    #[Test]
    public function パスワード変更でも会員の更新は1回で済む(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->update(['password' => 'newpassword9', 'password_confirmation' => 'newpassword9'])
            ->assertRedirect(route('shop.mypage.index'));
        $updates = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with($query['query'], 'update "users"'));
        DB::disableQueryLog();

        $this->assertCount(1, $updates);
        $this->assertTrue(Hash::check('newpassword9', $this->user->fresh()->password));
    }

    /**
     * ログイン保持（remember）の cookie を持つ端末は、パスワードを変えると cookie が新しいパスワードで作り直される
     */
    #[Test]
    public function パスワードを変えるとログイン保持のcookieが作り直される(): void
    {
        $recaller = Auth::guard('web')->getRecallerName();
        $this->user->forceFill(['remember_token' => 'remembertoken'])->save();

        $response = $this->withCookie($recaller, $this->user->id.'|remembertoken|'.Auth::guard('web')->hashPasswordForCookie($this->user->password))
            ->update(['password' => 'newpassword9', 'password_confirmation' => 'newpassword9']);

        $response->assertRedirect(route('shop.mypage.index'));
        $cookie = $response->getCookie($recaller, false);
        $this->assertNotNull($cookie);
        $this->assertStringEndsWith('|'.Auth::guard('web')->hashPasswordForCookie($this->user->fresh()->password), app('encrypter')->decrypt((string) $cookie->getValue(), false));
    }

    /**
     * パスワードを変えないときは、ほかの端末のセッションはそのまま
     */
    #[Test]
    public function パスワードを変えないときはほかの端末のセッションは有効のまま(): void
    {
        $hash = $this->user->password;
        $loginKey = Auth::guard('web')->getName();

        $this->post(route('login.store'), ['email' => 'hanako@example.com', 'password' => self::OLD_PASSWORD]);
        $this->update()->assertRedirect(route('shop.mypage.index'));

        $this->assertSame($hash, $this->user->fresh()->password);

        Auth::forgetGuards();
        $this->flushSession();
        $this->withSession([$loginKey => $this->user->id, 'password_hash_web' => $hash])
            ->get(route('shop.mypage.index'))
            ->assertOk();
    }

    /**
     * 入力に誤りがあるときのエラーメッセージ（会員登録と同じ文言）
     *
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            '姓が空' => [['last_name' => ''], 'last_name', '姓を入力してください'],
            '姓が256文字' => [['last_name' => str_repeat('あ', 256)], 'last_name', '姓は255文字以内で入力してください'],
            '名が空' => [['first_name' => ''], 'first_name', '名を入力してください'],
            '名が256文字' => [['first_name' => str_repeat('あ', 256)], 'first_name', '名は255文字以内で入力してください'],
            '地域が未選択' => [['prefecture' => ''], 'prefecture', 'お住まいの地域を選択してください'],
            '地域が存在しない' => [['prefecture' => 'ない県'], 'prefecture', 'お住まいの地域を一覧から選択してください'],
            'ご住所が空' => [['city' => ''], 'city', 'ご住所を入力してください'],
            'ご住所が256文字' => [['city' => str_repeat('あ', 256)], 'city', 'ご住所は255文字以内で入力してください'],
            '電話番号が空' => [['tell' => ''], 'tell', '電話番号を入力してください'],
            '電話番号にハイフン' => [['tell' => '090-1234-5678'], 'tell', '電話番号はハイフンなしの半角数字10〜11桁で入力してください'],
            '電話番号が9桁' => [['tell' => '090123456'], 'tell', '電話番号はハイフンなしの半角数字10〜11桁で入力してください'],
            '電話番号が12桁' => [['tell' => '090123456789'], 'tell', '電話番号はハイフンなしの半角数字10〜11桁で入力してください'],
            'メールアドレスが空' => [['email' => ''], 'email', 'メールアドレスを入力してください'],
            'メールアドレスの形式が誤り' => [['email' => 'abc'], 'email', 'メールアドレスは有効なメールアドレス形式で入力してください'],
            'メールアドレスが31文字' => [['email' => str_repeat('a', 19).'@example.com'], 'email', 'メールアドレスは30文字以内で入力してください'],
            'パスワードが短い' => [['password' => 'pass123', 'password_confirmation' => 'pass123'], 'password', 'パスワードは8文字以上で入力してください'],
            '確認用パスワードが一致しない' => [['password' => 'newpassword9', 'password_confirmation' => 'newpassword8'], 'password', 'パスワードと確認用パスワードが一致しません'],
            '確認用パスワードが空' => [['password' => 'newpassword9', 'password_confirmation' => ''], 'password', 'パスワードと確認用パスワードが一致しません'],
            '姓が配列' => [['last_name' => ['a']], 'last_name', '姓は文字で入力してください'],
            'メールアドレスが配列' => [['email' => ['a@example.com']], 'email', 'メールアドレスは文字で入力してください'],
        ];
    }

    /**
     * 入力に誤りがあるとエラーが表示され、入力した内容が残り、変更されない
     *
     * @param  array<string, mixed>  $overrides
     */
    #[Test]
    #[DataProvider('invalidInputs')]
    public function 入力に誤りがあるとエラーになり変更されない(array $overrides, string $field, string $message): void
    {
        $response = $this->update($overrides);

        $response->assertRedirect(route('shop.mypage.edit'));
        $response->assertSessionHasErrors([$field => $message]);

        $fresh = User::find($this->user->id);
        $this->assertNotNull($fresh);
        $this->assertSame('塩谷', $fresh->last_name);
        $this->assertSame('花子', $fresh->first_name);
        $this->assertSame('北海道', $fresh->prefecture);
        $this->assertSame('札幌市中央区1-1-1', $fresh->city);
        $this->assertSame('09012345678', $fresh->tell);
        $this->assertSame('hanako@example.com', $fresh->email);
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $fresh->password));
    }

    /**
     * ほかの会員のメールアドレスは重複エラーになる
     */
    #[Test]
    public function ほかの会員のメールアドレスは重複エラーになる(): void
    {
        User::factory()->create(['email' => 'test@test.com']);

        $this->update(['email' => 'test@test.com'])
            ->assertRedirect(route('shop.mypage.edit'))
            ->assertSessionHasErrors(['email' => 'このメールアドレスはすでに登録されています']);

        $this->assertSame('hanako@example.com', $this->user->fresh()->email);
    }

    /**
     * 退会済み（論理削除済み）の会員のメールアドレスも重複エラーになる
     */
    #[Test]
    public function 退会済みの会員のメールアドレスも重複エラーになる(): void
    {
        $withdrawn = User::factory()->create(['email' => 'withdrawn@example.com']);
        $withdrawn->delete();

        $this->update(['email' => 'withdrawn@example.com'])
            ->assertSessionHasErrors(['email' => 'このメールアドレスはすでに登録されています']);

        $this->assertSame('hanako@example.com', $this->user->fresh()->email);
    }

    /**
     * エラーで戻ったとき、入力した内容が残り、パスワードは再表示しない
     */
    #[Test]
    public function エラーで戻ると入力した内容が残りパスワードは表示されない(): void
    {
        $this->update(['last_name' => '', 'city' => 'エラー時の住所', 'password' => 'secret-password-1', 'password_confirmation' => 'secret-password-2']);

        $response = $this->actingAs($this->user)->get(route('shop.mypage.edit'));

        $response->assertSee('value="エラー時の住所"', false);
        $response->assertSee('value="太郎"', false);
        $response->assertSee('姓を入力してください');
        $response->assertDontSee('secret-password-1');
        $response->assertDontSee('secret-password-2');
    }

    /**
     * 他の会員の情報は変更できない（ログイン中の会員だけが変更される）
     */
    #[Test]
    public function ログイン中の会員だけが変更される(): void
    {
        $other = User::factory()->create(['last_name' => '他人', 'email' => 'other@example.com']);

        $this->update();

        $this->assertSame('他人', $other->fresh()->last_name);
        $this->assertSame('other@example.com', $other->fresh()->email);
    }
}
