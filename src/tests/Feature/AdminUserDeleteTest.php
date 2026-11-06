<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Delivery_address;
use App\Models\Purchase_bank_transfer;
use App\Models\Purchase_history;
use App\Models\Purchase_history_item;
use App\Models\Reservation;
use App\Models\Reservation_bank_transfer;
use App\Models\User;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の退会済みの会員の物理削除（DELETE admin.users.delete）のテスト
 */
class AdminUserDeleteTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 会員に、発送済みの注文（明細・振込先つき）・予約（振込先つき）・配送先住所2件を持たせる
     *
     * @return array{purchase: Purchase_history, reservation: Reservation}
     */
    private function attachRecords(User $user, bool $shipped = true): array
    {
        $purchase = $this->makeOrder($user, overrides: ['shipped' => $shipped]);
        $this->attachPurchaseBankTransfer($purchase);
        $reservation = $this->makeAdminReservation($user, overrides: ['shipped' => $shipped]);
        $this->attachReservationBankTransfer($reservation);
        Delivery_address::factory()->count(2)->create(['user_id' => $user->id]);

        return ['purchase' => $purchase, 'reservation' => $reservation];
    }

    /**
     * 関連するテーブルの件数を返す
     *
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'users' => User::withTrashed()->count(),
            'purchase_histories' => Purchase_history::count(),
            'purchase_history_items' => Purchase_history_item::count(),
            'purchase_bank_transfers' => Purchase_bank_transfer::count(),
            'reservations' => Reservation::count(),
            'reservation_bank_transfers' => Reservation_bank_transfer::count(),
            'delivery_addresses' => Delivery_address::count(),
        ];
    }

    /**
     * 管理者として、会員の物理削除を送る
     */
    private function deleteUser(mixed $id): TestResponse
    {
        return $this->delete(route('admin.users.delete'), ['id' => $id]);
    }

    /**
     * 会員と、その会員の注文・予約・振込情報・配送先住所が削除され、一覧に戻って案内が出る。他の会員のデータは残る
     */
    #[Test]
    public function 退会済みの会員と関連データが完全に削除される(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser('削除', '対象');
        $this->attachRecords($target);
        $other = $this->makeUser('残る', '会員');
        $otherRecords = $this->attachRecords($other);
        $otherWithdrawn = $this->makeWithdrawnUser('残る', '退会者');
        $this->attachRecords($otherWithdrawn);
        $before = $this->counts();

        $this->deleteUser($target->id)
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('list_status', '会員を削除しました。')
            ->assertSessionHasNoErrors();

        // 会員は論理削除ではなく、行ごと消える
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertSame([
            'users' => $before['users'] - 1,
            'purchase_histories' => $before['purchase_histories'] - 1,
            'purchase_history_items' => $before['purchase_history_items'] - 1,
            'purchase_bank_transfers' => $before['purchase_bank_transfers'] - 1,
            'reservations' => $before['reservations'] - 1,
            'reservation_bank_transfers' => $before['reservation_bank_transfers'] - 1,
            'delivery_addresses' => $before['delivery_addresses'] - 2,
        ], $this->counts());
        foreach (['purchase_histories', 'reservations', 'delivery_addresses'] as $table) {
            $this->assertDatabaseMissing($table, ['user_id' => $target->id]);
            $this->assertDatabaseHas($table, ['user_id' => $other->id]);
            $this->assertDatabaseHas($table, ['user_id' => $otherWithdrawn->id]);
        }
        $this->assertDatabaseHas('purchase_bank_transfers', ['purchase_history_id' => $otherRecords['purchase']->id]);
        $this->assertDatabaseHas('reservation_bank_transfers', ['reservation_id' => $otherRecords['reservation']->id]);
        // 商品は消えない
        $this->assertDatabaseCount('items', 2 * 3);
    }

    /**
     * 削除後の一覧に案内が出て、一覧・注文一覧・予約一覧から消える
     */
    #[Test]
    public function 削除後は案内が出て一覧から消える(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser('削除', '対象');
        $this->attachRecords($target);
        $this->makeUser('残る', '会員');

        $this->followingRedirects()->delete(route('admin.users.delete'), ['id' => $target->id])
            ->assertOk()
            ->assertViewIs('admin.users')
            ->assertSee('会員を削除しました。')
            ->assertSee('残る 会員')
            ->assertDontSee('削除 対象');

        $this->get(route('admin.purchase.index'))->assertOk()->assertDontSee('削除 対象');
        $this->get(route('admin.reservations.index'))->assertOk()->assertDontSee('削除 対象');
    }

    /**
     * 注文・予約・住所のない退会済みの会員も削除できる
     */
    #[Test]
    public function 関連データのない退会済みの会員も削除できる(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();

        $this->deleteUser($target->id)->assertRedirect(route('admin.users.index'))->assertSessionHas('list_status', '会員を削除しました。');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    /**
     * 未発送の注文が1件でも残っていれば削除できず、データは何も変わらない
     */
    #[Test]
    public function 未発送の注文があると削除できない(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();
        $this->attachRecords($target);
        $unshipped = $this->makeOrder($target, overrides: ['shipped' => false]);
        $before = $this->counts();

        $this->deleteUser($target->id)
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors(['delete' => '未発送の注文・予約があるため削除できません。'])
            ->assertSessionMissing('list_status');

        $this->assertSame($before, $this->counts());
        $this->assertDatabaseHas('purchase_histories', ['id' => $unshipped->id, 'shipped' => false]);
        $this->assertNotNull(User::onlyTrashed()->find($target->id));
    }

    /**
     * 未発送の予約が1件でも残っていれば削除できず、データは何も変わらない
     */
    #[Test]
    public function 未発送の予約があると削除できない(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();
        $this->attachRecords($target);
        $this->makeAdminReservation($target, overrides: ['shipped' => false]);
        $before = $this->counts();

        $this->deleteUser($target->id)
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors(['delete' => '未発送の注文・予約があるため削除できません。']);

        $this->assertSame($before, $this->counts());
        $this->assertNotNull(User::onlyTrashed()->find($target->id));
    }

    /**
     * 未発送だった注文・予約を発送済みにすれば、削除できる
     */
    #[Test]
    public function 発送済みにすれば削除できる(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();
        $records = $this->attachRecords($target, shipped: false);

        $this->deleteUser($target->id)->assertSessionHasErrors('delete');
        $this->assertNotNull(User::onlyTrashed()->find($target->id));

        $this->post(route('admin.purchase.update'), ['id' => $records['purchase']->id, 'shipped' => true])->assertOk();
        $this->deleteUser($target->id)->assertSessionHasErrors('delete');
        $this->post(route('admin.reservations.update'), ['id' => $records['reservation']->id, 'shipped' => true])->assertOk();

        $this->deleteUser($target->id)->assertSessionHas('list_status', '会員を削除しました。');
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    /**
     * 他の会員の未発送の注文・予約は、削除の判定に影響しない
     */
    #[Test]
    public function 他の会員の未発送は削除の判定に影響しない(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();
        $this->attachRecords($target);
        $other = $this->makeUser('他', '会員');
        $this->attachRecords($other, shipped: false);

        $this->deleteUser($target->id)->assertSessionHas('list_status', '会員を削除しました。');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('purchase_histories', ['user_id' => $other->id, 'shipped' => false]);
    }

    /**
     * 退会していない会員は、直接リクエストを送っても削除できない
     */
    #[Test]
    public function 退会していない会員は削除できない(): void
    {
        $this->loginAsAdmin();
        $member = $this->makeUser('会員', '一郎');
        $this->attachRecords($member);
        $before = $this->counts();

        $this->from(route('admin.users.index'))->deleteUser($member->id)
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors(['delete' => '退会していない会員、または存在しない会員は削除できません。']);

        $this->assertSame($before, $this->counts());
        $this->assertNull($member->fresh()->deleted_at);
    }

    /**
     * 存在しない会員は削除できない（エラーにする）
     */
    #[Test]
    public function 存在しない会員は削除できない(): void
    {
        $this->loginAsAdmin();
        $withdrawn = $this->makeWithdrawnUser();

        $this->deleteUser(999999)
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors(['delete' => '退会していない会員、または存在しない会員は削除できません。']);

        $this->assertNotNull(User::onlyTrashed()->find($withdrawn->id));
    }

    /**
     * IDがない・整数でない・配列のときはエラーになり、何も削除されない
     */
    #[Test]
    public function idが不正なときはエラーになる(): void
    {
        $this->loginAsAdmin();
        $withdrawn = $this->makeWithdrawnUser();

        $this->delete(route('admin.users.delete'))->assertSessionHasErrors(['id' => '削除する会員を指定してください。']);
        $this->deleteUser('abc')->assertSessionHasErrors(['id' => '削除する会員が正しくありません。']);
        $this->deleteUser("{$withdrawn->id}abc")->assertSessionHasErrors('id');
        $this->deleteUser([$withdrawn->id])->assertSessionHasErrors('id');
        $this->deleteUser('')->assertSessionHasErrors('id');

        $this->assertNotNull(User::onlyTrashed()->find($withdrawn->id));
    }

    /**
     * 未ログイン・会員としてのログインでは削除できず、管理者用のログインページへ移動する
     */
    #[Test]
    public function 未ログインと会員では削除できない(): void
    {
        $withdrawn = $this->makeWithdrawnUser();

        $this->deleteUser($withdrawn->id)->assertRedirect(route('admin.login'));

        $this->loginAsMember();
        $this->deleteUser($withdrawn->id)->assertRedirect(route('admin.login'));

        $this->assertNotNull(User::onlyTrashed()->find($withdrawn->id));
    }

    /**
     * 削除したメールアドレスで、会員登録できる
     */
    #[Test]
    public function 削除した会員のメールアドレスで会員登録できる(): void
    {
        $this->loginAsAdmin();
        Area::factory()->create(['prefecture' => '東京都']);
        $target = $this->makeWithdrawnUser('削除', '対象', ['email' => 'again@example.com']);

        // 退会済みのままでは、メールアドレスを再利用できない
        $input = [
            'last_name' => '山田', 'first_name' => '太郎', 'prefecture' => '東京都', 'city' => '千代田区千代田1-1',
            'tell' => '09011112222', 'email' => 'again@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        ];
        $this->post(route('register.store'), $input)->assertSessionHasErrors('email');

        $this->deleteUser($target->id)->assertSessionHas('list_status');

        $this->post(route('register.store'), $input)->assertSessionHasNoErrors();
        $this->assertSame(1, User::where('email', 'again@example.com')->count());
    }

    /**
     * Stripe の顧客データの削除は呼ばない（外部への通信もしない）
     */
    #[Test]
    public function stripeの顧客データは削除しない(): void
    {
        Http::fake();
        // 呼ばれると、期待のないメソッド呼び出しで失敗する
        $this->mock(PaymentGatewayInterface::class);
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser('削除', '対象', ['stripe_customer_id' => 'cus_deleted123']);
        $this->attachRecords($target);

        $this->deleteUser($target->id)->assertSessionHas('list_status');

        Http::assertNothingSent();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    /**
     * 判定と削除は、同じトランザクションの中で行う
     */
    #[Test]
    public function 判定と削除は同じトランザクションの中で行われる(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();
        $outside = DB::transactionLevel();
        $levelAtDelete = null;
        User::deleting(function () use (&$levelAtDelete): void {
            $levelAtDelete = DB::transactionLevel();
        });

        $this->deleteUser($target->id)->assertSessionHas('list_status');

        $this->assertNotNull($levelAtDelete);
        $this->assertGreaterThan($outside, $levelAtDelete);
    }

    /**
     * 削除に失敗したときは、何も消えない（一部だけ消えた状態にならない）
     */
    #[Test]
    public function 削除に失敗したときは何も消えない(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();
        $this->attachRecords($target);
        $before = $this->counts();
        User::deleting(function (): never {
            throw new RuntimeException('削除の失敗');
        });

        try {
            $this->withoutExceptionHandling()->deleteUser($target->id);
            $this->fail('例外が出るはず');
        } catch (RuntimeException $e) {
            $this->assertSame('削除の失敗', $e->getMessage());
        }

        $this->assertSame($before, $this->counts());
    }

    /**
     * 削除の前に見ていた検索条件・ページの一覧へ戻る（成功・失敗とも）
     */
    #[Test]
    public function 削除のあとは削除の前に見ていた一覧へ戻る(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser('削除', '対象');
        $unshipped = $this->makeWithdrawnUser('未発送', '会員');
        $this->makeOrder($unshipped, overrides: ['shipped' => false]);
        $member = $this->makeUser('会員', '一郎');
        $searchUrl = route('admin.users.search', ['status' => 'withdrawn', 'name' => '削除', 'page' => 1]);
        $pageUrl = route('admin.users.index', ['page' => 2]);

        // 成功：検索結果
        $this->from($searchUrl)->deleteUser($target->id)->assertRedirect($searchUrl)->assertSessionHas('list_status', '会員を削除しました。');
        // 失敗（未発送）：ページ番号つきの一覧
        $this->from($pageUrl)->deleteUser($unshipped->id)->assertRedirect($pageUrl)->assertSessionHasErrors('delete');
        // 失敗（退会していない・存在しない）
        $this->from($searchUrl)->deleteUser($member->id)->assertRedirect($searchUrl)->assertSessionHasErrors('delete');
        $this->from($searchUrl)->deleteUser(999999)->assertRedirect($searchUrl)->assertSessionHasErrors('delete');
        // 失敗（入力の誤り）
        $this->from($searchUrl)->deleteUser('abc')->assertRedirect($searchUrl)->assertSessionHasErrors('id');
    }

    /**
     * 戻り先は会員一覧・検索結果のURLだけで、ほかのページ・外部のURLのときは会員一覧（全件）に戻る
     */
    #[Test]
    public function 戻り先が会員一覧以外のときは会員一覧に戻る(): void
    {
        $this->loginAsAdmin();
        $index = route('admin.users.index');

        foreach ([route('admin.purchase.index'), 'https://evil.example.com/admin/users', 'https://evil.example.com/?next='.$index, $index.'/delete', url('/admin/users2')] as $referer) {
            $target = $this->makeWithdrawnUser();
            $this->from($referer)->deleteUser($target->id)->assertRedirect($index);
        }
        // 戻り先がないとき（前のページの記録も Referer もない）
        $target = $this->makeWithdrawnUser();
        $this->deleteUser($target->id)->assertRedirect($index);
    }

    /**
     * 戻り先はパスとクエリだけで判定し、スキームやホストが違っても（プロキシの背後など）、自分のホストの相対URLとして戻る。外部のホストには移動しない
     */
    #[Test]
    public function 戻り先はパスとクエリだけで判定し外部のホストには移動しない(): void
    {
        $this->loginAsAdmin();
        $path = '/admin/users/search?status=withdrawn&name=%E5%B1%B1%E7%94%B0&page=2';

        foreach (['https://localhost'.$path, 'http://internal-host:8080'.$path, 'https://evil.example.com'.$path] as $referer) {
            $target = $this->makeWithdrawnUser();
            $response = $this->from($referer)->deleteUser($target->id);

            $response->assertRedirect('http://localhost'.$path);
            $this->assertStringStartsWith('http://localhost/', (string) $response->headers->get('Location'), $referer);
        }
    }

    /**
     * 成功の案内「会員を削除しました。」は、エラーと同じ検索欄の下に、背景・枠なしの緑の文字で1回だけ出る。layout の案内（x-admin-status）には出ない
     */
    #[Test]
    public function 成功の案内は検索欄の下に緑の文字で出てレイアウトの案内には出ない(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();

        $this->from(route('admin.users.index'))->deleteUser($target->id)->assertSessionHas('list_status')->assertSessionMissing('status');
        $html = $this->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '会員を削除しました。'));
        $this->assertMatchesRegularExpression('#role="search".*?</form>\s*<p class="mt-3 w-full text-center text-base text-accent-hover" role="status">会員を削除しました。</p>#su', $html);
        // layout の案内（灰色の枠の x-admin-status）は出ない
        $this->assertStringNotContainsString('border border-line-strong bg-surface', $html);
        // 次の表示では消える
        $this->assertStringNotContainsString('会員を削除しました。', $this->get(route('admin.users.index'))->getContent());
    }

    /**
     * 失敗（未発送）の案内は、赤字のエラーとして同じ場所に出て、緑の成功の案内は出ない
     */
    #[Test]
    public function 失敗の案内は赤字で出て成功の案内は出ない(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();
        $this->makeOrder($target, overrides: ['shipped' => false]);

        $this->from(route('admin.users.index'))->deleteUser($target->id);
        $html = $this->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#</form>\s*<ul class="mt-3 w-full text-center text-base text-danger" role="alert">\s*<li>未発送の注文・予約があるため削除できません。</li>#su', $html);
        $this->assertStringNotContainsString('role="status"', $html);
    }

    /**
     * 419の案内は、削除できなかった知らせとして、赤字のエラーで同じ場所に出る
     */
    #[Test]
    public function トークンが古いときの案内は赤字で検索欄の下に出る(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();
        $this->app['env'] = 'local';

        $this->withSession(['_token' => 'valid-token'])->from(route('admin.users.index'))
            ->delete(route('admin.users.delete'), ['_token' => 'stale-token', 'id' => $target->id]);
        $html = $this->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'ページの有効期限が切れました。もう一度削除してください。'));
        $this->assertMatchesRegularExpression('#role="alert">\s*<li>ページの有効期限が切れました。もう一度削除してください。</li>#su', $html);
        $this->assertStringNotContainsString('border border-line-strong bg-surface', $html);
    }

    /**
     * 最後の1件を消してページがなくなったときは、戻り先の一覧が最終ページを表示する
     */
    #[Test]
    public function 最後の1件を消してページがなくなると最終ページが表示される(): void
    {
        $this->loginAsAdmin();
        // 2ページ目の1件（いちばん古い）だけを退会済みにする
        $users = $this->makeUsers(11);
        $users[0]->delete();
        $pageUrl = route('admin.users.index', ['page' => 2]);

        $this->from($pageUrl)->followingRedirects()->delete(route('admin.users.delete'), ['id' => $users[0]->id])
            ->assertOk()
            ->assertSee('会員を削除しました。')
            ->assertViewHas('users', fn ($paginator): bool => $paginator->currentPage() === 1 && $paginator->total() === 10 && count($paginator->items()) === 10);
    }

    /**
     * データベースのエラー（ロック待ちのタイムアウト・デッドロックなど）のときは、500にせず、同じ一覧へ戻って案内し、ログに残す。何も消えない
     */
    #[Test]
    public function データベースのエラーのときは案内を出して一覧へ戻る(): void
    {
        Log::spy();
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();
        $this->attachRecords($target);
        $before = $this->counts();
        User::deleting(function (): never {
            throw new QueryException('mysql', 'delete from `users` where `id` = ?', [1], new RuntimeException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction'));
        });
        $searchUrl = route('admin.users.search', ['status' => 'withdrawn']);

        $this->from($searchUrl)->deleteUser($target->id)
            ->assertRedirect($searchUrl)
            ->assertSessionHasErrors(['delete' => '削除できませんでした。時間をおいてもう一度お試しください。'])
            ->assertSessionMissing('list_status');

        $this->assertSame($before, $this->counts());
        Log::shouldHaveReceived('error')->once();
    }

    /**
     * 同時実行のエラー（デッドロック）も同じく案内を出す
     */
    #[Test]
    public function デッドロックのときも案内を出して一覧へ戻る(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();
        User::deleting(function (): never {
            throw new QueryException('mysql', 'delete from `users` where `id` = ?', [1], new RuntimeException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction'));
        });

        $this->deleteUser($target->id)->assertSessionHasErrors(['delete' => '削除できませんでした。時間をおいてもう一度お試しください。']);

        $this->assertNotNull(User::onlyTrashed()->find($target->id));
    }

    /**
     * 同時実行のエラー以外のデータベースのエラー（制約違反など）は、隠さずそのまま投げる（ログの「削除に失敗」にもしない）
     */
    #[Test]
    public function 同時実行以外のデータベースのエラーはそのまま投げる(): void
    {
        Log::spy();
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();
        User::deleting(function (): never {
            throw new QueryException('mysql', 'delete from `users` where `id` = ?', [1], new RuntimeException('Integrity constraint violation: 1451 Cannot delete or update a parent row'));
        });

        try {
            $this->withoutExceptionHandling()->deleteUser($target->id);
            $this->fail('例外が出るはず');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Integrity constraint violation', $e->getMessage());
        }

        Log::shouldNotHaveReceived('error');
        $this->assertNotNull(User::onlyTrashed()->find($target->id));
    }

    /**
     * CSRFトークンが古いとき、ログイン中なら見ていた会員一覧へ案内つきで戻り、会員は削除されない
     */
    #[Test]
    public function トークンが古いと案内つきで会員一覧へ戻り削除されない(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();
        $this->app['env'] = 'local';
        $searchUrl = route('admin.users.search', ['status' => 'withdrawn']);

        $response = $this->withSession(['_token' => 'valid-token'])->from($searchUrl)
            ->delete(route('admin.users.delete'), ['_token' => 'stale-token', 'id' => $target->id]);

        $response->assertRedirect($searchUrl);
        $response->assertSessionHasErrors(['delete' => 'ページの有効期限が切れました。もう一度削除してください。']);
        $response->assertSessionMissing('status');
        $response->assertSessionMissing('list_status');
        $this->assertNotNull(User::onlyTrashed()->find($target->id));
    }

    /**
     * CSRFトークンが古く、すでにログアウトしているときは、管理者用のログインページへ移動する
     */
    #[Test]
    public function トークンが古く未ログインならログインページへ移動する(): void
    {
        $target = $this->makeWithdrawnUser();
        $this->app['env'] = 'local';

        $this->withSession(['_token' => 'valid-token'])
            ->delete(route('admin.users.delete'), ['_token' => 'stale-token', 'id' => $target->id])
            ->assertRedirect(route('admin.login'))
            ->assertSessionMissing('status')
            ->assertSessionMissing('errors');

        $this->assertNotNull(User::onlyTrashed()->find($target->id));
    }

    /**
     * その会員のパスワード再設定のトークン（メールアドレスが一致する行）は消える。他の会員のものは残る
     */
    #[Test]
    public function パスワード再設定のトークンも削除される(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser('削除', '対象', ['email' => 'gone@example.com']);
        $other = $this->makeUser('残る', '会員');
        DB::table('password_reset_tokens')->insert([
            ['email' => 'gone@example.com', 'token' => 'hash-1', 'created_at' => now()],
            ['email' => $other->email, 'token' => 'hash-2', 'created_at' => now()],
        ]);

        $this->deleteUser($target->id)->assertSessionHas('list_status');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'gone@example.com']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $other->email]);
    }

    /**
     * 未発送で削除できないときは、トークンも消えない
     */
    #[Test]
    public function 削除できないときはトークンも残る(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser('削除', '対象', ['email' => 'gone@example.com']);
        $this->makeOrder($target, overrides: ['shipped' => false]);
        DB::table('password_reset_tokens')->insert(['email' => 'gone@example.com', 'token' => 'hash-1', 'created_at' => now()]);

        $this->deleteUser($target->id)->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'gone@example.com']);
    }

    /**
     * セッションは消さない。sessions.user_id は既定のガードのIDで、会員と同じ番号の管理者のセッションの行も残る
     */
    #[Test]
    public function 同じ番号の管理者のセッションは消えない(): void
    {
        $this->loginAsAdmin();
        $target = $this->makeWithdrawnUser();
        // 管理者のセッションの行には、管理者のID（会員のIDと同じ番号のことがある）が user_id に入る
        DB::table('sessions')->insert([
            ['id' => 'admin-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'guest-session', 'user_id' => null, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->deleteUser($target->id)->assertSessionHas('list_status');

        $this->assertSame(['admin-session', 'guest-session'], DB::table('sessions')->orderBy('id')->pluck('id')->all());
    }

    /**
     * users・注文・予約を参照する外部キーは、すべて cascade で削除される（会員を消すと、関連データも残らない）
     */
    #[Test]
    public function usersと注文と予約を参照する外部キーはすべてcascadeである(): void
    {
        $checked = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (in_array($foreignKey['foreign_table'], ['users', 'purchase_histories', 'reservations'], true)) {
                    $this->assertSame('cascade', $foreignKey['on_delete'], "{$table} → {$foreignKey['foreign_table']}");
                    $checked[] = "{$table}.{$foreignKey['columns'][0]}";
                }
            }
        }

        sort($checked);
        $this->assertSame([
            'delivery_addresses.user_id',
            'purchase_bank_transfers.purchase_history_id',
            'purchase_histories.user_id',
            'purchase_history_items.purchase_history_id',
            'reservation_bank_transfers.reservation_id',
            'reservations.user_id',
        ], $checked);
    }
}
