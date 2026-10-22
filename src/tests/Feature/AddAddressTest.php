<?php

namespace Tests\Feature;

use App\Models\Delivery_address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * 配送先住所の追加（POST /add-address）のテスト
 *
 * 購入ページのモーダルは Livewire が処理する。このルートは、JavaScript を使わないフォーム送信の受け口。
 */
class AddAddressTest extends TestCase
{
    use PurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 未ログインはログインページへ移動し、登録されない
     */
    #[Test]
    public function 未ログインはログインページへ移動する(): void
    {
        $this->makeAreas();

        $this->post(route('shop.checkout.add-address'), ['delivery_prefecture' => '東京都', 'delivery_city' => '千代田区1-1'])
            ->assertRedirect(route('login'));

        $this->assertSame(0, Delivery_address::count());
    }

    /**
     * 住所を登録して元のページへ戻り、追加した住所が選択された状態になる
     */
    #[Test]
    public function 住所を登録して戻ると追加した住所が選択される(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $item = $this->makeItem('人参', 2, 418);

        $this->actingAs($user)
            ->from(route('shop.checkout.index', $item))
            ->post(route('shop.checkout.add-address'), ['delivery_prefecture' => '東京都', 'delivery_city' => 'UAT区テスト町1-2-3'])
            ->assertRedirect(route('shop.checkout.index', $item));

        $address = Delivery_address::sole();
        $this->assertSame($user->id, $address->user_id);
        $this->assertSame('東京都', $address->delivery_prefecture);
        $this->assertSame('UAT区テスト町1-2-3', $address->delivery_city);

        $this->get(route('shop.checkout.index', $item))
            ->assertOk()
            ->assertSee('東京都UAT区テスト町1-2-3')
            ->assertSee('<option value="delivery_'.$address->id.'" selected>', false)
            ->assertSeeInOrder(['小計', '418円', '送料', '1,460円']);
    }

    /**
     * 他人のIDを送っても、登録されるのはログイン中の会員の住所
     */
    #[Test]
    public function 他人の_i_dを送っても自分の住所として登録される(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $other = $this->makeUser();

        $this->actingAs($user)
            ->post(route('shop.checkout.add-address'), ['user_id' => $other->id, 'delivery_prefecture' => '東京都', 'delivery_city' => '千代田区1-1']);

        $this->assertSame($user->id, Delivery_address::sole()->user_id);
    }

    /**
     * 入力の誤り（未入力・存在しない都道府県・256文字）はエラーになり、登録されない
     *
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function 誤った入力(): array
    {
        return [
            '都道府県が未入力' => [['delivery_prefecture' => '', 'delivery_city' => '千代田区1-1'], 'delivery_prefecture'],
            '都道府県が存在しない' => [['delivery_prefecture' => '存在県', 'delivery_city' => '千代田区1-1'], 'delivery_prefecture'],
            '住所が未入力' => [['delivery_prefecture' => '東京都', 'delivery_city' => ''], 'delivery_city'],
            '住所が256文字' => [['delivery_prefecture' => '東京都', 'delivery_city' => str_repeat('あ', 256)], 'delivery_city'],
            '住所が配列' => [['delivery_prefecture' => '東京都', 'delivery_city' => ['a']], 'delivery_city'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Test]
    #[DataProvider('誤った入力')]
    public function 誤った入力はエラーになり登録されない(array $data, string $errorKey): void
    {
        $this->makeAreas();

        $this->actingAs($this->makeUser())
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.add-address'), $data)
            ->assertRedirect(route('shop.checkout.index'))
            ->assertSessionHasErrors($errorKey);

        $this->assertSame(0, Delivery_address::count());
    }

    /**
     * 住所は255文字まで登録できる（境界値）
     */
    #[Test]
    public function 住所は255文字まで登録できる(): void
    {
        $this->makeAreas();

        $this->actingAs($this->makeUser())
            ->post(route('shop.checkout.add-address'), ['delivery_prefecture' => '東京都', 'delivery_city' => str_repeat('あ', 255)])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Delivery_address::count());
    }
}
