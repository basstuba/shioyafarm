<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の会員一覧の検索（admin.users.search）のテスト
 */
class AdminUserSearchTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 検索結果の会員IDを（新しい順に）返す
     *
     * @param  array<string, string>  $query
     * @return list<int>
     */
    private function searchIds(array $query): array
    {
        return array_map(
            fn (User $user): int => $user->id,
            $this->search($query)->assertOk()->viewData('users')->items(),
        );
    }

    /**
     * 管理者として検索する
     *
     * @param  array<string, mixed>  $query
     */
    private function search(array $query): TestResponse
    {
        $this->loginAsAdmin();

        return $this->get(route('admin.users.search', $query));
    }

    /**
     * 氏名は、姓名のどの入力でも見つかる（スペースの有無、姓だけ、名だけ）。他の会員は出ない
     */
    #[Test]
    public function 氏名は姓名のどの入力でも見つかる(): void
    {
        $target = $this->makeUser('山田', '太郎');
        $this->makeUser('佐藤', '花子');

        foreach (['山田太郎', '山田 太郎', '山田　太郎', '山田', '太郎', '田太', '  山田太郎  '] as $name) {
            $this->assertSame([$target->id], $this->searchIds(['name' => $name]), $name);
        }
    }

    /**
     * 姓や名の中にスペースがある会員も、スペースなしの入力で見つかる
     */
    #[Test]
    public function 氏名の中にスペースがある会員も見つかる(): void
    {
        $target = $this->makeUser('山 田', '太　郎');

        $this->assertSame([$target->id], $this->searchIds(['name' => '山田太郎']));
    }

    /**
     * LIKE のワイルドカードと退避文字は文字として扱い、全件にならない
     */
    #[Test]
    public function ワイルドカードは文字として扱われ他の会員に一致しない(): void
    {
        $this->makeUser('山田', '太郎');

        foreach (['%', '_', '山_太郎', '山%', '!', '山!田'] as $name) {
            $this->assertSame([], $this->searchIds(['name' => $name]), $name);
        }
    }

    /**
     * ワイルドカードの記号そのものを氏名に持つ会員は、その記号で見つかる
     */
    #[Test]
    public function 記号を氏名に含む会員はその記号で見つかる(): void
    {
        $percent = $this->makeUser('100%', '太郎');
        $underscore = $this->makeUser('山_田', '花子');
        $this->makeUser('山田', '次郎');

        $this->assertSame([$percent->id], $this->searchIds(['name' => '%']));
        $this->assertSame([$underscore->id], $this->searchIds(['name' => '_']));
    }

    /**
     * 退会済みの会員も氏名で見つかる
     */
    #[Test]
    public function 退会済みの会員も氏名で見つかる(): void
    {
        $withdrawn = $this->makeWithdrawnUser('伊藤', '健');

        $this->assertSame([$withdrawn->id], $this->searchIds(['name' => '伊藤']));
    }

    /**
     * 状態で絞り込める（会員は退会していない会員だけ、退会済は退会済みだけ）。空は全件
     */
    #[Test]
    public function 状態で絞り込める(): void
    {
        $member = $this->makeUser('会員', '一郎');
        $withdrawn = $this->makeWithdrawnUser('退会', '二郎');

        $this->assertSame([$member->id], $this->searchIds(['status' => 'member']));
        $this->assertSame([$withdrawn->id], $this->searchIds(['status' => 'withdrawn']));
        $this->assertSame([$withdrawn->id, $member->id], $this->searchIds(['status' => '']));
        $this->assertSame([$withdrawn->id, $member->id], $this->searchIds(['status' => '', 'name' => '']));
    }

    /**
     * 状態と氏名は組み合わせられ、両方に合う会員だけが表示される
     */
    #[Test]
    public function 状態と氏名を組み合わせるとどちらにも合う会員だけが表示される(): void
    {
        $this->makeUser('山田', '太郎');
        $withdrawnYamada = $this->makeWithdrawnUser('山田', '次郎');
        $this->makeWithdrawnUser('佐藤', '花子');

        $this->assertSame([$withdrawnYamada->id], $this->searchIds(['status' => 'withdrawn', 'name' => '山田']));
        $this->assertSame([], $this->searchIds(['status' => 'member', 'name' => '佐藤']));
    }

    /**
     * 該当する会員がいないときは案内を出し、ページ送りを出さない
     */
    #[Test]
    public function 該当なしのときは案内を出しページ送りを出さない(): void
    {
        $this->makeUsers(11);

        $response = $this->search(['name' => '存在しない氏名']);

        $response->assertOk()->assertSee('該当する会員はいません。');
        $response->assertDontSee('<table', false);
        $response->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 検索結果が10件以下のときは、ページ送りが出ない
     */
    #[Test]
    public function 検索結果が十件以下のときはページ送りが出ない(): void
    {
        $this->makeUsers(11);
        $this->makeUser('山田', '太郎');

        $this->search(['name' => '山田'])->assertOk()->assertDontSee('aria-label="ページ送り"', false);
    }

    /**
     * 検索後も入力した値が検索欄に残る
     */
    #[Test]
    public function 検索後も入力した値が検索欄に残る(): void
    {
        $html = $this->search(['status' => 'withdrawn', 'name' => '山田 太郎'])->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<option value="withdrawn"\s+selected>退会済</option>#u', $html);
        $this->assertDoesNotMatchRegularExpression('#<option value="member"\s+selected#u', $html);
        $this->assertStringContainsString('name="name" type="text" maxlength="100" value="山田 太郎"', $html);
    }

    /**
     * ページ送りのリンクが検索条件を保つ
     */
    #[Test]
    public function ページ送りのリンクが検索条件を保つ(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->makeUser('山田', "太郎{$i}");
        }

        $response = $this->search(['status' => 'member', 'name' => '山田']);
        $response->assertOk();
        $query = ['status' => 'member', 'name' => '山田', 'page' => 2];

        $response->assertSee(e(route('admin.users.search', $query)), false);
        $this->assertCount(1, $this->search($query)->viewData('users')->items());
    }

    /**
     * 不正な状態は、一覧に戻してエラーを検索欄の近くに出す（画面は壊れない）
     */
    #[Test]
    public function 不正な状態は一覧に戻ってエラーが表示される(): void
    {
        $this->loginAsAdmin();

        $this->get(route('admin.users.search', ['status' => 'abc']))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors(['status' => '状態は「会員」か「退会済」を選んでください。']);

        $this->followingRedirects()->get(route('admin.users.search', ['status' => 'abc']))
            ->assertOk()
            ->assertSee('状態は「会員」か「退会済」を選んでください。')
            ->assertSee('role="alert"', false);
    }

    /**
     * 配列などの不正な入力・長すぎる氏名・壊れた UTF-8 は、全件を表示せずエラーにする
     */
    #[Test]
    public function 不正な氏名と配列の入力はエラーになる(): void
    {
        $this->loginAsAdmin();

        $this->get(route('admin.users.search', ['status' => ['member']]))->assertRedirect(route('admin.users.index'))->assertSessionHasErrors('status');
        $this->get(route('admin.users.search', ['name' => ['a']]))->assertRedirect(route('admin.users.index'))->assertSessionHasErrors('name');
        $this->get(route('admin.users.search', ['name' => str_repeat('あ', 101)]))->assertRedirect(route('admin.users.index'))->assertSessionHasErrors(['name' => '氏名は100文字以内で入力してください。']);
        $this->get(route('admin.users.search').'?name=%FF%FE')->assertRedirect(route('admin.users.index'))->assertSessionHasErrors('name');
        // 100文字ちょうどは検索できる
        $this->get(route('admin.users.search', ['name' => str_repeat('あ', 100)]))->assertOk();
    }
}
