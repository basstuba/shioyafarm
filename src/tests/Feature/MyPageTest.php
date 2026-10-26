<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\MyPageTestHelpers;
use Tests\TestCase;

/**
 * マイページ（GET /mypage）の表示のテスト
 *
 * 一覧のページ送り・詳細モーダルは MyPageLivewireTest、登録情報の変更は MyPageUpdateTest、退会は MyPageWithdrawTest。
 */
class MyPageTest extends TestCase
{
    use MyPageTestHelpers;
    use RefreshDatabase;

    /**
     * 未ログインでマイページ系のページを開くと、ログインページへ移動する
     *
     * @return array<string, array{string, string}>
     */
    public static function guestRoutes(): array
    {
        return [
            'マイページ' => ['get', 'shop.mypage.index'],
            '登録情報変更ページ' => ['get', 'shop.mypage.edit'],
            '登録情報の更新' => ['post', 'shop.mypage.update'],
            '退会' => ['delete', 'shop.mypage.delete'],
        ];
    }

    #[Test]
    #[DataProvider('guestRoutes')]
    public function 未ログインはログインページへ移動する(string $method, string $routeName): void
    {
        $this->{$method}(route($routeName))->assertRedirect(route('login'));
    }

    /**
     * 未ログインでマイページを開き、ログインすると開こうとしたページへ戻る
     */
    #[Test]
    #[DataProvider('guestRoutesForGet')]
    public function ログイン後に開こうとしたページへ戻る(string $routeName): void
    {
        $user = User::factory()->create();

        $this->get(route($routeName))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route($routeName));
    }

    /**
     * ログイン後に戻るページを確認するルート（GETのもの）
     *
     * @return array<string, array{string}>
     */
    public static function guestRoutesForGet(): array
    {
        return [
            'マイページ' => ['shop.mypage.index'],
            '登録情報変更ページ' => ['shop.mypage.edit'],
        ];
    }

    /**
     * ファーストビュー・ページ内リンク・5つのセクションが表示される
     */
    #[Test]
    public function マイページに各セクションとページ内リンクが表示される(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('shop.mypage.index'));

        $response->assertOk();
        $response->assertViewIs('shop.my_page');
        $response->assertSee('storage/image/tool/my-page-fv-flowers-photo.webp', false);
        $response->assertSeeInOrder(['<h1', 'マイページ'], false);
        foreach (['profile', 'addresses', 'purchases', 'reservations', 'withdraw'] as $id) {
            $response->assertSee('href="#'.$id.'"', false);
            $response->assertSee('id="'.$id.'"', false);
        }
        $response->assertSeeInOrder(['登録情報', 'お届け先住所一覧', '購入履歴', '予約履歴', '退会手続き']);
        $response->assertSee('登録情報を変更する');
        $response->assertSee('href="'.route('shop.mypage.edit').'"', false);
        $response->assertSee('＋新しい住所を追加');
        $response->assertSee('退会をご希望の場合は、こちらからお手続きください。');
        $response->assertSee('退会すると、ログインやマイページのご利用ができなくなり、元に戻すことはできません。本当に退会してよろしいですか？');
        $response->assertSee('action="'.route('shop.mypage.delete').'"', false);
        $response->assertSee('name="_method" value="DELETE"', false);
    }

    /**
     * 登録情報に、ログイン中の会員の氏名・住所（都道府県＋ご住所）・電話番号・メールアドレスが表示される
     */
    #[Test]
    public function 登録情報にログイン中の会員の内容が表示される(): void
    {
        $user = User::factory()->create([
            'last_name' => '塩谷',
            'first_name' => '花子',
            'prefecture' => '沖縄県',
            'city' => '那覇市テスト町1-2-3',
            'tell' => '08011112222',
            'email' => 'hanako@example.com',
        ]);
        $other = User::factory()->create(['last_name' => '他人', 'city' => '他人市9-9-9', 'email' => 'other@example.com']);

        $response = $this->actingAs($user)->get(route('shop.mypage.index'));

        $response->assertSee('塩谷 花子');
        $response->assertSee('沖縄県那覇市テスト町1-2-3');
        $response->assertSee('08011112222');
        $response->assertSee('hanako@example.com');
        $response->assertDontSee($other->email);
        $response->assertDontSee('他人市9-9-9');
    }

    /**
     * 住所・購入履歴・予約履歴が0件のときは「〜はありません」を表示し、ページ送りは出ない
     */
    #[Test]
    public function 履歴が0件のときはないことを表示しページ送りは出ない(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('shop.mypage.index'));

        $response->assertSee('登録されているお届け先住所はありません');
        $response->assertSee('購入履歴はありません');
        $response->assertSee('予約履歴はありません');
        $response->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 履歴があるときは、ほかの会員の分を含めずに自分の分だけを表示する
     */
    #[Test]
    public function 自分の購入履歴と予約だけが表示される(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->makePurchase($user, [['じゃがいも', 2, 330, 1]]);
        $this->makePurchase($other, [['他人の大根', 5, 777, 1]]);
        $this->makeReservation($user);
        $this->makeReservation($other, 'card', ['total_price' => 98765]);

        $response = $this->actingAs($user)->get(route('shop.mypage.index'));

        $response->assertSee('じゃがいも 2kg');
        $response->assertDontSee('他人の大根');
        $response->assertDontSee('購入履歴はありません');
        $response->assertDontSee('予約履歴はありません');
        $response->assertSee('北の恵みセット 10kg');
        $response->assertDontSee('98,765円');
    }

    /**
     * 登録情報を変更したあとのフラッシュメッセージが表示される
     */
    #[Test]
    public function フラッシュメッセージが表示される(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->withSession(['flash_message' => '登録情報を変更しました'])
            ->get(route('shop.mypage.index'));

        $response->assertSee('登録情報を変更しました');
    }
}
