<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin_two_factor_code;
use App\Repositories\Interfaces\AdminTwoFactorCodeRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 管理者の二段階認証のコードのリポジトリのテスト（失敗回数の増加・再送の置き換えが、DB の実際の値で判定されること）
 */
class AdminTwoFactorCodeRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * リポジトリ（インターフェースの束縛から取り出す）
     */
    private function repository(): AdminTwoFactorCodeRepositoryInterface
    {
        return app(AdminTwoFactorCodeRepositoryInterface::class);
    }

    /**
     * 失敗回数を増やすと、増やした後の回数が返る
     */
    #[Test]
    public function 失敗回数を1つずつ増やして上限で止まる(): void
    {
        $admin = Admin::factory()->create();
        Admin_two_factor_code::factory()->create(['admin_id' => $admin->id]);

        foreach ([1, 2, 3, 4, 5] as $expected) {
            $this->assertSame($expected, $this->repository()->incrementAttempts($admin, 5, now()));
        }

        // 上限に達したあとは増やせない（null）。DB の値も上限のまま
        $this->assertNull($this->repository()->incrementAttempts($admin, 5, now()));
        $this->assertNull($this->repository()->incrementAttempts($admin, 5, now()));
        $this->assertSame(5, Admin_two_factor_code::query()->firstOrFail()->attempts);
    }

    /**
     * 読み込んだ時の値ではなく、DB の実際の値で数える（読み込んだあとに他のリクエストが増やしていても、上限を超えない）
     */
    #[Test]
    public function 読み込んだあとに_d_bの値が変わっていても実際の値で数える(): void
    {
        $admin = Admin::factory()->create();
        $stale = Admin_two_factor_code::factory()->create(['admin_id' => $admin->id]);
        $this->assertSame(0, $stale->attempts);

        // 読み込んだ後に、他のリクエストが4回まで増やした
        Admin_two_factor_code::query()->where('admin_id', $admin->id)->update(['attempts' => 4]);

        $this->assertSame(5, $this->repository()->incrementAttempts($admin, 5, now()));
        $this->assertNull($this->repository()->incrementAttempts($admin, 5, now()));
        $this->assertSame(5, Admin_two_factor_code::query()->firstOrFail()->attempts);
    }

    /**
     * コードが無いときは増やさず null
     */
    #[Test]
    public function コードが無いときは失敗回数を増やせない(): void
    {
        $admin = Admin::factory()->create();

        $this->assertNull($this->repository()->incrementAttempts($admin, 5, now()));
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
    }

    /**
     * ほかの管理者のコードの失敗回数は増やさない
     */
    #[Test]
    public function ほかの管理者の失敗回数は増えない(): void
    {
        [$a, $b] = [Admin::factory()->create(), Admin::factory()->create()];
        Admin_two_factor_code::factory()->create(['admin_id' => $a->id]);
        $other = Admin_two_factor_code::factory()->create(['admin_id' => $b->id]);

        $this->repository()->incrementAttempts($a, 5, now());

        $this->assertSame(0, $other->fresh()->attempts);
    }

    /**
     * 再送の置き換えは、前回の送信が基準の時刻以前のときだけ。置き換えると sent_at が進むので、続けて呼んでも2回目は置き換わらない
     */
    #[Test]
    public function 再送の置き換えは1回だけ成功する(): void
    {
        $this->freezeTime();
        $admin = Admin::factory()->create();
        Admin_two_factor_code::factory()->create(['admin_id' => $admin->id, 'attempts' => 3, 'sent_at' => now()->subMinutes(2)]);
        $threshold = now()->subMinute();
        $expires = now()->addMinutes(10);

        // 同時に2回呼ばれた状況（どちらも、置き換える前の sent_at が1分以上前に見えている）
        $this->assertTrue($this->repository()->replaceIfSentBefore($admin, $threshold, 'hash-1', $expires, now()));
        $this->assertFalse($this->repository()->replaceIfSentBefore($admin, $threshold, 'hash-2', $expires, now()));

        $record = Admin_two_factor_code::query()->firstOrFail();
        $this->assertSame('hash-1', $record->code);
        $this->assertSame(3, $record->attempts);
    }

    /**
     * 前回の送信が基準の時刻より新しければ置き換えない（ちょうど基準の時刻なら置き換える）
     */
    #[Test]
    public function 再送の置き換えの境界(): void
    {
        $this->freezeTime();
        $admin = Admin::factory()->create();
        $record = Admin_two_factor_code::factory()->create(['admin_id' => $admin->id, 'sent_at' => now()->subSeconds(59)]);
        $threshold = now()->subSeconds(60);

        $this->assertFalse($this->repository()->replaceIfSentBefore($admin, $threshold, 'new', now()->addMinutes(10), now()));
        $this->assertNotSame('new', $record->fresh()->code);

        $record->update(['sent_at' => $threshold]);

        $this->assertTrue($this->repository()->replaceIfSentBefore($admin, $threshold, 'new', now()->addMinutes(10), now()));
        $this->assertSame('new', $record->fresh()->code);
    }

    /**
     * ログイン時の置き換えは、失敗回数を0に戻す
     */
    #[Test]
    public function ログイン時の置き換えは失敗回数を戻す(): void
    {
        $admin = Admin::factory()->create();
        Admin_two_factor_code::factory()->create(['admin_id' => $admin->id, 'attempts' => 4]);

        $this->repository()->replace($admin, 'new', now()->addMinutes(10), now());

        $this->assertSame(0, Admin_two_factor_code::query()->firstOrFail()->attempts);
        $this->assertDatabaseCount('admin_two_factor_codes', 1);
    }

    /**
     * 同時に2回置き換えても（ログインのダブルクリック）、重複エラーにならず、1件のまま、あとの内容になる
     */
    #[Test]
    public function 置き換えを続けて呼んでも1件のまま重複エラーにならない(): void
    {
        $admin = Admin::factory()->create();

        $this->repository()->replace($admin, 'first', now()->addMinutes(10), now());
        $this->repository()->replace($admin, 'second', now()->addMinutes(10), now());

        $this->assertDatabaseCount('admin_two_factor_codes', 1);
        $record = Admin_two_factor_code::query()->firstOrFail();
        $this->assertSame('second', $record->code);
        $this->assertSame(0, $record->attempts);
        $this->assertNotNull($record->created_at);
        $this->assertNotNull($record->updated_at);
    }

    /**
     * 置き換えは、読んでから書かず、重複のエラーも起こさない（最初の SELECT で「無い」と見えたあとに、別のリクエストが先に作っていた状況）
     */
    #[Test]
    public function 別のリクエストが先に作っていても置き換えは成功する(): void
    {
        $admin = Admin::factory()->create();
        $stale = $this->repository()->findByAdmin($admin);
        $this->assertNull($stale);

        // 別のリクエストが先に作った
        Admin_two_factor_code::factory()->create(['admin_id' => $admin->id, 'attempts' => 3]);

        $this->repository()->replace($admin, 'mine', now()->addMinutes(10), now());

        $this->assertDatabaseCount('admin_two_factor_codes', 1);
        $this->assertSame('mine', Admin_two_factor_code::query()->firstOrFail()->code);
        $this->assertSame(0, Admin_two_factor_code::query()->firstOrFail()->attempts);
    }

    /**
     * 期限が切れたコードには、失敗回数を使えない（期限の確認も、増やす UPDATE の条件に含む）
     */
    #[Test]
    public function 期限切れのコードには失敗回数を使えない(): void
    {
        $this->freezeTime();
        $admin = Admin::factory()->create();
        Admin_two_factor_code::factory()->create(['admin_id' => $admin->id, 'expires_at' => now()->subSecond()]);

        $this->assertNull($this->repository()->incrementAttempts($admin, 5, now()));
        $this->assertSame(0, Admin_two_factor_code::query()->firstOrFail()->attempts);

        // 期限の1秒前までは使える
        Admin_two_factor_code::query()->update(['expires_at' => now()->addSecond()]);
        $this->assertSame(1, $this->repository()->incrementAttempts($admin, 5, now()));
    }

    /**
     * 一致するコードのハッシュで、期限内なら、1回の DELETE で消して true。2回目は false
     */
    #[Test]
    public function 一致するコードだけを1回の削除で使える(): void
    {
        $this->freezeTime();
        $admin = Admin::factory()->create();
        Admin_two_factor_code::factory()->create(['admin_id' => $admin->id, 'code' => 'hash-a']);

        $this->assertFalse($this->repository()->consumeIfMatches($admin, 'hash-b', now()));
        $this->assertDatabaseCount('admin_two_factor_codes', 1);

        $this->assertTrue($this->repository()->consumeIfMatches($admin, 'hash-a', now()));
        $this->assertFalse($this->repository()->consumeIfMatches($admin, 'hash-a', now()));
        $this->assertDatabaseCount('admin_two_factor_codes', 0);
    }

    /**
     * 期限が切れたコードは、ハッシュが一致しても使えず、消えもしない
     */
    #[Test]
    public function 期限切れのコードは一致しても使えない(): void
    {
        $this->freezeTime();
        $admin = Admin::factory()->create();
        Admin_two_factor_code::factory()->create(['admin_id' => $admin->id, 'code' => 'hash-a', 'expires_at' => now()->subSecond()]);

        $this->assertFalse($this->repository()->consumeIfMatches($admin, 'hash-a', now()));
        $this->assertDatabaseCount('admin_two_factor_codes', 1);
    }

    /**
     * 再送で置き換えられた前のコードは使えず、新しいコードも消えない（再送と同時に、前のコードが入力された状況）
     */
    #[Test]
    public function 再送で置き換えられた前のコードは使えず新しいコードは消えない(): void
    {
        $this->freezeTime();
        $admin = Admin::factory()->create();
        Admin_two_factor_code::factory()->create(['admin_id' => $admin->id, 'code' => 'old-hash', 'sent_at' => now()->subMinutes(2)]);

        $this->assertTrue($this->repository()->replaceIfSentBefore($admin, now()->subMinute(), 'new-hash', now()->addMinutes(10), now()));

        $this->assertFalse($this->repository()->consumeIfMatches($admin, 'old-hash', now()));
        $this->assertSame('new-hash', Admin_two_factor_code::query()->firstOrFail()->code);
        $this->assertTrue($this->repository()->consumeIfMatches($admin, 'new-hash', now()));
    }
}
