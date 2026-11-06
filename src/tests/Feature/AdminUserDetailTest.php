<?php

namespace Tests\Feature;

use App\Livewire\AdminUserDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Constraints\SeeInOrder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の会員一覧の詳細モーダル（Livewire の AdminUserDetail）のテスト
 */
class AdminUserDetailTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 会員の詳細を開く（一覧の「詳細」ボタンと同じ、open-user-detail のイベント）
     */
    private function openFor(User $user): Testable
    {
        return Livewire::test(AdminUserDetail::class)->dispatch('open-user-detail', id: $user->id);
    }

    /**
     * コンポーネントの現在の表示に、文言が順に含まれていることを確認する
     *
     * @param  list<string>  $values
     */
    private function assertTextInOrder(Testable $component, array $values): void
    {
        $this->assertThat(array_map(e(...), $values), new SeeInOrder($component->html()));
    }

    /**
     * 開く前は、モーダルを出さない
     */
    #[Test]
    public function 開く前はモーダルを表示しない(): void
    {
        $this->loginAsAdmin();

        Livewire::test(AdminUserDetail::class)
            ->assertSet('showDetail', false)
            ->assertDontSee('メールアドレス');
    }

    /**
     * 退会していない会員は、5項目だけが決められた順に出る。退会日と「削除する」は出ない
     */
    #[Test]
    public function 退会していない会員の詳細は五項目だけが表示される(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create([
            'last_name' => '山田', 'first_name' => '太郎', 'prefecture' => '北海道', 'city' => '札幌市中央区1-2-3',
            'tell' => '09011112222', 'email' => 'yamada@example.com',
        ]);

        $component = $this->openFor($user)
            ->assertSet('showDetail', true)
            ->assertSet('detailId', $user->id)
            ->assertDontSee('退会日')
            ->assertDontSee('削除する')
            ->assertDontSee('削除します。よろしいですか？')
            ->assertDontSee('<form', false);
        $this->assertTextInOrder($component, ['姓', '山田', '名', '太郎', '住所', '北海道札幌市中央区1-2-3', '電話番号', '09011112222', 'メールアドレス', 'yamada@example.com', '閉じる']);
        $this->assertStringContainsString('x-on:click="confirming = false; $wire.showDetail = false"', $component->html());
    }

    /**
     * 退会済みの会員は、退会日（YYYY/MM/DD）と赤い「削除する」、削除の確認が出る
     */
    #[Test]
    public function 退会済みの会員の詳細には退会日と削除の確認が表示される(): void
    {
        $this->loginAsAdmin();
        $user = $this->makeWithdrawnUser('退会', '花子', ['email' => 'gone@example.com']);
        $user->forceFill(['deleted_at' => '2026-11-01 09:30:00'])->save();

        $component = $this->openFor($user)
            ->assertSee('削除します。よろしいですか？')
            ->assertSee('注文・予約の記録も消えます。必要なら先にエクスポートしてください。')
            ->assertSee('キャンセル');

        $this->assertTextInOrder($component, ['メールアドレス', 'gone@example.com', '退会日', '2026/11/01', '削除する', '閉じる']);
        $html = $component->html();
        // 確認の「削除する」は、会員のIDを DELETE /admin/users/delete へ送るフォーム
        $this->assertStringContainsString('action="'.route('admin.users.delete').'"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('<input type="hidden" name="id" value="'.$user->id.'">', $html);
        $this->assertStringContainsString('name="_token"', $html);
        // 赤い「削除する」（退会ボタンと同じ部品）
        $this->assertStringContainsString('bg-delete', $html);
        // 削除の2つのボタンと詳細の中の「削除する」は、管理画面用のボタン（font-sans。SPは文字16px・高さ44px、PCは14px・30px）。マイページ用の font-maru は使わない
        $this->assertSame(3, substr_count($html, 'lg:h-[30px] lg:text-sm'));
        $this->assertStringNotContainsString('font-maru', $html);
        // 確認の中はフォーカスを閉じ込め（x-trap。閉じたら開く前の「削除する」へ戻る）、最初のフォーカスは「キャンセル」
        $this->assertStringContainsString('x-trap="confirming"', $html);
        $this->assertMatchesRegularExpression('#<button[^>]*autofocus[^>]*>キャンセル</button>#u', $html);
        // 確認を開くときに、送信中の状態を戻す（ブラウザの「戻る」で固まらないように）
        $this->assertStringContainsString('x-on:click="deleting = false; confirming = true"', $html);
        $this->assertStringNotContainsString('pageshow', $html);
    }

    /**
     * 存在しない会員は何も開かない
     */
    #[Test]
    public function 存在しない会員は何も開かない(): void
    {
        $this->loginAsAdmin();

        Livewire::test(AdminUserDetail::class)
            ->dispatch('open-user-detail', id: 999999)
            ->assertSet('showDetail', false)
            ->assertSet('detailId', null)
            ->assertDontSee('メールアドレス');
    }

    /**
     * 別の会員を開くと、前の会員の内容は残らない
     */
    #[Test]
    public function 別の会員を開くと前の内容は残らない(): void
    {
        $this->loginAsAdmin();
        $first = $this->makeUser('前の', '会員');
        $second = $this->makeUser('次の', '会員');

        $this->openFor($first)
            ->dispatch('open-user-detail', id: $second->id)
            ->assertSee('次の')
            ->assertDontSee('前の');
    }

    /**
     * 管理者としてログインしていなければ開けない（会員としてのログインでも）
     */
    #[Test]
    public function 管理者でなければ開けない(): void
    {
        $user = $this->makeUser('山田', '太郎');

        Livewire::test(AdminUserDetail::class)->dispatch('open-user-detail', id: $user->id)->assertForbidden();

        $this->loginAsMember();
        Livewire::test(AdminUserDetail::class)->dispatch('open-user-detail', id: $user->id)->assertForbidden();
    }

    /**
     * 開いている会員のIDは、ブラウザから書き換えられない
     */
    #[Test]
    public function 開いている会員のidは書き換えられない(): void
    {
        $this->loginAsAdmin();
        $user = $this->makeUser('山田', '太郎');
        $other = $this->makeUser('佐藤', '花子');

        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->openFor($user)->set('detailId', $other->id);
    }

    /**
     * パスワードなどの認証情報は、モーダルの表示にも、ブラウザに渡る状態（wire:snapshot）にも含まれない
     */
    #[Test]
    public function 認証情報は表示にも状態にも含まれない(): void
    {
        $this->loginAsAdmin();
        $user = $this->makeWithdrawnUser('山田', '太郎', [
            'stripe_customer_id' => 'cus_secret123',
            'two_factor_secret' => 'two-factor-secret-value',
            'two_factor_recovery_codes' => 'recovery-codes-value',
        ]);

        $component = $this->openFor($user);
        // 表示（HTML）と、ブラウザに渡る状態（スナップショット）の両方
        $outputs = [$component->html(), json_encode($component->snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];

        foreach ([$user->password, (string) $user->remember_token, 'cus_secret123', 'two-factor-secret-value', 'recovery-codes-value'] as $secret) {
            foreach ($outputs as $output) {
                $this->assertStringNotContainsString($secret, $output);
            }
        }
        $this->assertStringContainsString('detailId', $outputs[1]);
    }

    /**
     * 氏名・メールアドレスの HTML の記号は、文字のまま表示される
     */
    #[Test]
    public function 詳細のhtmlの記号はエスケープされる(): void
    {
        $this->loginAsAdmin();
        $user = $this->makeUser('<b>テスト</b>', '<i>名</i>');

        $html = $this->openFor($user)->html();

        $this->assertStringContainsString('&lt;b&gt;テスト&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>テスト</b>', $html);
        $this->assertStringNotContainsString('<i>名</i>', $html);
    }
}
