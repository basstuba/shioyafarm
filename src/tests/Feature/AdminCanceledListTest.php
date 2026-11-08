<?php

namespace Tests\Feature;

use App\Enums\AdminListType;
use App\Enums\ShippedUpdateResult;
use App\Exports\PurchaseExport;
use App\Exports\ReservationExport;
use App\Models\Purchase_history;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AdminListCriteria;
use App\Services\AdminPurchaseService;
use App\Services\AdminReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 取消済みの注文・予約が、一覧の表示・検索・発送済みの更新・エクスポートでどう扱われるかのテスト
 *
 * 注文と予約は同じ作りのため、データプロバイダーで両方に同じ確認をする。
 */
class AdminCanceledListTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 注文・予約の両方で確かめるときのデータ
     *
     * @return array<string, array{AdminListType}>
     */
    public static function types(): array
    {
        return ['注文' => [AdminListType::Purchase], '予約' => [AdminListType::Reservation]];
    }

    /**
     * 注文または予約を作る
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeTarget(AdminListType $type, array $overrides = [], ?User $user = null): Purchase_history|Reservation
    {
        return match ($type) {
            AdminListType::Purchase => $this->makeOrder($user, overrides: $overrides),
            AdminListType::Reservation => $this->makeAdminReservation($user, overrides: $overrides),
        };
    }

    /**
     * 取消済み・発送済み・未発送を1件ずつ作る（作った順）
     *
     * @return array{canceled: Purchase_history|Reservation, shipped: Purchase_history|Reservation, unshipped: Purchase_history|Reservation}
     */
    private function makeThree(AdminListType $type): array
    {
        return [
            'canceled' => $this->makeTarget($type, ['canceled_at' => '2026-11-05 10:00:00']),
            'shipped' => $this->makeTarget($type, ['shipped' => true]),
            'unshipped' => $this->makeTarget($type),
        ];
    }

    /**
     * 管理者として一覧を検索し、表示された注文・予約のIDを（新しい順に）返す
     *
     * @param  array<string, string>  $query
     * @return list<int>
     */
    private function searchIds(AdminListType $type, array $query): array
    {
        $this->loginAsAdmin();
        $key = $type === AdminListType::Purchase ? 'purchases' : 'reservations';

        return array_map(
            fn (Purchase_history|Reservation $record): int => $record->id,
            $this->get(route($type->searchRoute(), $query))->assertOk()->viewData($key)->items(),
        );
    }

    /**
     * 管理者としてエクスポートし、出力された管理番号を返す
     *
     * @param  array<string, string>  $conditions
     * @return list<int>
     */
    private function exportedIds(AdminListType $type, array $conditions = []): array
    {
        $this->loginAsAdmin();
        $response = $this->post(route('admin.export'), ['type' => $type->value, ...$conditions])->assertOk();

        return array_values(array_unique(array_column($this->rows($this->sheet($response)), 0)));
    }

    /**
     * ダウンロードされた .xlsx のシートを読み込む
     */
    private function sheet(TestResponse $response): Worksheet
    {
        $file = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $file);

        return IOFactory::load($file->getFile()->getPathname())->getActiveSheet();
    }

    /**
     * シートの行を（見出しを除いて）配列にして返す
     *
     * @return list<list<mixed>>
     */
    private function rows(Worksheet $sheet): array
    {
        return array_slice($sheet->toArray(null, false, false, false), 1);
    }

    /**
     * 発送状況の選択欄に「取消済」が加わり、何も選ばないときの表示はPC・SPとも「発送状況」（切り替えの Alpine もない）
     */
    #[Test]
    #[DataProvider('types')]
    public function 発送状況の選択欄に取消済が加わる(AdminListType $type): void
    {
        $this->loginAsAdmin();

        $html = $this->get(route($type->listRoute()))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<option value="" selected>発送状況</option>\s*<option value="0"[^>]*>未発送</option>\s*<option value="1"[^>]*>発送済</option>\s*<option value="canceled"[^>]*>取消済</option>#u', $html);
        $this->assertStringNotContainsString('発送済 / 未発送', $html);
    }

    /**
     * 「取消済」で検索したあとは、選択欄が「取消済」を選んだ状態で戻る
     */
    #[Test]
    #[DataProvider('types')]
    public function 取消済で検索すると選択欄が取消済のまま戻る(AdminListType $type): void
    {
        $this->loginAsAdmin();

        $html = $this->get(route($type->searchRoute(), ['shipped' => 'canceled']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<option value="canceled" selected>取消済</option>#u', $html);
        $this->assertDoesNotMatchRegularExpression('#<option value=""[^>]*selected>#u', $html);
    }

    /**
     * 取消済みの行は一覧に残り、チェックの代わりに「取消済」と出て、文字は灰色、地は未発送と同じ（発送済みの色にしない）（AC-11）
     */
    #[Test]
    #[DataProvider('types')]
    public function 取消済みの行はチェックの代わりに取消済と出て灰色になる(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $three = $this->makeThree($type);
        $subject = $type->subject();
        $key = $type === AdminListType::Purchase ? 'purchases' : 'reservations';

        $html = $this->get(route($type->listRoute()))->assertOk()->assertViewHas($key, fn ($p): bool => $p->total() === 3)->getContent();

        // 新しい順：未発送、発送済み、取消済み。行ごとの開始タグ（class と x-data の有無）を取り出す
        preg_match_all('#<tr\s+class="(grid[^"]*)"(\s+x-data="[^"]*")?#u', $html, $rows, PREG_SET_ORDER);
        $this->assertCount(3, $rows);
        // 未発送
        $this->assertStringContainsString('bg-white lg:even:bg-admin-bar', $rows[0][1]);
        $this->assertStringNotContainsString('text-admin-gray', $rows[0][1]);
        // 発送済み
        $this->assertStringContainsString('bg-admin-shipped', $rows[1][1]);
        // 取消済み：地は未発送と同じ、文字は灰色。チェックの状態を持たない（x-data なし）
        $this->assertStringContainsString('bg-white lg:even:bg-admin-bar', $rows[2][1]);
        $this->assertStringNotContainsString('bg-admin-shipped', $rows[2][1]);
        $this->assertStringContainsString('text-admin-gray', $rows[2][1]);
        $this->assertCount(2, array_filter($rows, fn (array $row): bool => ! empty($row[2])));
        // 取消済みの行にはチェックがなく、「取消済」が出る。ほかの行には出ない
        $this->assertSame(1, substr_count($html, '>取消済</span>'));
        $this->assertStringContainsString('<span class="flex h-11 items-center justify-end text-base lg:justify-center">取消済</span>', $html);
        $this->assertStringNotContainsString("aria-label=\"{$subject}{$three['canceled']->id}の発送済み\"", $html);
        $this->assertStringContainsString("aria-label=\"{$subject}{$three['unshipped']->id}の発送済み\"", $html);
        $this->assertStringContainsString("aria-label=\"{$subject}{$three['shipped']->id}の発送済み\"", $html);
        // 詳細は開ける
        $this->assertStringContainsString("{$subject}{$three['canceled']->id}の詳細を見る", $html);
        // SP のカードのラベルも灰色（ふだんは濃い灰色）
        $this->assertSame(3, substr_count($html, 'mr-2 text-admin-gray lg:hidden'));
    }

    /**
     * 「取消済」は取消済みだけ、「未発送」は未発送で未取消だけ、「発送済」は発送済みだけ。空は全件（AC-13）
     */
    #[Test]
    #[DataProvider('types')]
    public function 発送状況ごとに該当するものだけが表示される(AdminListType $type): void
    {
        $three = $this->makeThree($type);

        $this->assertSame([$three['canceled']->id], $this->searchIds($type, ['shipped' => 'canceled']));
        $this->assertSame([$three['unshipped']->id], $this->searchIds($type, ['shipped' => '0']));
        $this->assertSame([$three['shipped']->id], $this->searchIds($type, ['shipped' => '1']));
        $this->assertSame([$three['unshipped']->id, $three['shipped']->id, $three['canceled']->id], $this->searchIds($type, ['shipped' => '']));
        $this->assertSame([$three['unshipped']->id, $three['shipped']->id, $three['canceled']->id], $this->searchIds($type, []));
    }

    /**
     * 取消済みで発送済みの（ありえないが残っている）データも、「未発送」には出ない
     */
    #[Test]
    #[DataProvider('types')]
    public function 未発送の検索に取消済みは出ない(AdminListType $type): void
    {
        $unshipped = $this->makeTarget($type);
        $this->makeTarget($type, ['canceled_at' => '2026-11-05 10:00:00']);

        $this->assertSame([$unshipped->id], $this->searchIds($type, ['shipped' => '0']));
    }

    /**
     * 「取消済」は日付・名前と組み合わせられる
     */
    #[Test]
    #[DataProvider('types')]
    public function 取消済は日付と名前と組み合わせられる(AdminListType $type): void
    {
        $yamada = $this->makeUser('山田', '太郎');
        $sato = $this->makeUser('佐藤', '花子');
        $target = $this->makeTarget($type, ['canceled_at' => '2026-11-05 10:00:00', 'created_at' => '2026-11-04 09:00:00'], $yamada);
        $this->makeTarget($type, ['canceled_at' => '2026-11-05 10:00:00', 'created_at' => '2026-11-04 09:00:00'], $sato);
        $this->makeTarget($type, ['canceled_at' => '2026-11-05 10:00:00', 'created_at' => '2026-11-03 09:00:00'], $yamada);
        $this->makeTarget($type, ['created_at' => '2026-11-04 09:00:00'], $yamada);

        $this->assertSame([$target->id], $this->searchIds($type, ['shipped' => 'canceled', 'date' => '2026-11-04', 'name' => '山田']));
    }

    /**
     * 発送状況に決められた値以外を送ると、入力の誤りとして一覧に戻りエラーになる
     */
    #[Test]
    #[DataProvider('types')]
    public function 発送状況が不正な値ならエラーになる(AdminListType $type): void
    {
        $this->loginAsAdmin();

        foreach (['2', 'cancel', 'Canceled', '01'] as $value) {
            $this->get(route($type->searchRoute(), ['shipped' => $value]))
                ->assertRedirect(route($type->listRoute()))
                ->assertSessionHasErrors('shipped');
        }
    }

    /**
     * 取消済みの注文・予約は、発送済みの更新のリクエストを直接送っても発送済みにならない。409 を返す（AC-12）
     */
    #[Test]
    #[DataProvider('types')]
    public function 取消済みは発送済みにできない(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeTarget($type, ['canceled_at' => '2026-11-05 10:00:00']);

        $this->postJson(route($type->updateRoute()), ['id' => $target->id, 'shipped' => true])
            ->assertStatus(409)
            ->assertExactJson(['message' => '取り消されているため、発送状況を変更できません。ページを再読み込みしてください。']);

        $fresh = $target::query()->findOrFail($target->id);
        $this->assertFalse($fresh->shipped);
        $this->assertNotNull($fresh->canceled_at);
    }

    /**
     * 取消済みでない注文・予約は、これまでどおり更新できる。同じ値を送っても（MySQL は更新の件数が 0 になる）成功する
     */
    #[Test]
    #[DataProvider('types')]
    public function 取消済みでなければ同じ値でも更新は成功する(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeTarget($type, ['shipped' => true]);

        $this->postJson(route($type->updateRoute()), ['id' => $target->id, 'shipped' => true])
            ->assertOk()
            ->assertExactJson(['id' => $target->id, 'shipped' => true]);
    }

    /**
     * 更新の画面側：409 のときは、取り消されているという案内を出す
     */
    #[Test]
    #[DataProvider('types')]
    public function 一覧のスクリプトは409のとき取消の案内を出す(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->makeTarget($type);

        $html = $this->get(route($type->listRoute()))->assertOk()->getContent();

        $this->assertStringContainsString('response.status === 409', $html);
        $this->assertStringContainsString(trim((string) Blade::render('@js($m)', ['m' => '取り消されているため、発送状況を変更できません。ページを再読み込みしてください。'])), $html);
    }

    /**
     * エクスポートは、発送状況が空・未発送・発送済のとき取消済みを含まない（AC-15）
     */
    #[Test]
    #[DataProvider('types')]
    public function エクスポートは取消済みを含まない(AdminListType $type): void
    {
        $three = $this->makeThree($type);

        $this->assertSame([$three['unshipped']->id, $three['shipped']->id], $this->exportedIds($type));
        $this->assertSame([$three['unshipped']->id, $three['shipped']->id], $this->exportedIds($type, ['shipped' => '']));
        $this->assertSame([$three['unshipped']->id], $this->exportedIds($type, ['shipped' => '0']));
        $this->assertSame([$three['shipped']->id], $this->exportedIds($type, ['shipped' => '1']));
    }

    /**
     * エクスポートは、発送状況が「取消済」のとき、取消済みだけを出力する。ほかの検索条件は反映され、列は変わらない（AC-15）
     */
    #[Test]
    #[DataProvider('types')]
    public function エクスポートは取消済のとき取消済みだけを出力する(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $yamada = $this->makeUser('山田', '太郎');
        $sato = $this->makeUser('佐藤', '花子');
        $target = $this->makeTarget($type, ['canceled_at' => '2026-11-05 10:00:00'], $yamada);
        $this->makeTarget($type, ['canceled_at' => '2026-11-05 10:00:00'], $sato);
        $this->makeTarget($type, [], $yamada);

        $this->assertSame([$target->id], $this->exportedIds($type, ['shipped' => 'canceled', 'name' => '山田']));
        $this->assertCount(2, $this->exportedIds($type, ['shipped' => 'canceled']));

        $sheet = $this->sheet($this->post(route('admin.export'), ['type' => $type->value, 'shipped' => 'canceled']));
        $subject = $type->subject();
        $this->assertSame(
            ["{$subject}管理番号", "{$subject}者", "{$subject}日", "{$subject}商品", '商品重量', '商品数量', '配送先氏名', '配送先住所', '配送先電話番号'],
            $sheet->toArray(null, false, false, false)[0],
        );
    }

    /**
     * 発送状況が空のとき、出力の対象が0件（全件が取消済み）なら、一覧には表示されるがエクスポートは押せない（AC-16）
     */
    #[Test]
    #[DataProvider('types')]
    public function 出力の対象が0件のときエクスポートは押せない(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $canceled = $this->makeTarget($type, ['canceled_at' => '2026-11-05 10:00:00']);

        // 空：一覧には表示されるが、出力は0件
        $html = $this->get(route($type->listRoute()))->assertOk()->getContent();
        $this->assertStringContainsString("{$type->subject()}{$canceled->id}の詳細を見る", $html);
        $this->assertStringContainsString('type="submit" disabled>エクスポート</button>', $html);
        $this->assertStringNotContainsString('該当する'.$type->subject().'はありません。', $html);
        // 未発送：一覧もなし
        $html = $this->get(route($type->searchRoute(), ['shipped' => '0']))->assertOk()->getContent();
        $this->assertStringContainsString('type="submit" disabled>エクスポート</button>', $html);
        // 取消済：押せる
        $html = $this->get(route($type->searchRoute(), ['shipped' => 'canceled']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#type="submit"\s*>エクスポート</button>#u', $html);
        $this->assertStringContainsString('name="shipped" value="canceled"', $html);
    }

    /**
     * 取消済みと未発送がある一覧では、エクスポートは押せる
     */
    #[Test]
    #[DataProvider('types')]
    public function 出力の対象があればエクスポートは押せる(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->makeThree($type);

        $html = $this->get(route($type->listRoute()))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#type="submit"\s*>エクスポート</button>#u', $html);
    }

    /**
     * 検索欄に入力の誤りがあるときは、これまでどおりエクスポートを押せなくする
     */
    #[Test]
    #[DataProvider('types')]
    public function 検索の入力エラーがあるときはエクスポートは押せない(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->makeTarget($type);

        $html = $this->followingRedirects()->get(route($type->searchRoute(), ['date' => 'abc']))->assertOk()->getContent();

        $this->assertStringContainsString('type="submit" disabled>エクスポート</button>', $html);
    }

    /**
     * 発送済みの更新は、行をロックしたトランザクションの中で、判定と更新を一緒に行う（取消が間に入らない）
     */
    #[Test]
    #[DataProvider('types')]
    public function 発送済みの更新は同じトランザクションの中で判定と更新をする(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeTarget($type);
        $outside = DB::transactionLevel();
        $levelAtSave = null;
        $target::saving(function () use (&$levelAtSave): void {
            $levelAtSave = DB::transactionLevel();
        });
        DB::enableQueryLog();

        $this->postJson(route($type->updateRoute()), ['id' => $target->id, 'shipped' => true])->assertOk();

        $this->assertGreaterThan($outside, $levelAtSave);
        // 判定のための別の問い合わせ（存在の確認）を、更新のあとに投げない：取得 1 回と更新 1 回
        $queries = array_column(DB::getQueryLog(), 'query');
        $table = $type->table();
        $this->assertCount(1, array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'select') && str_contains($sql, "from \"{$table}\"") && ! str_contains($sql, 'count(*)') && str_contains($sql, 'limit 1')));
        $this->assertCount(1, array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'update')));
        $this->assertTrue($target::query()->findOrFail($target->id)->shipped);
    }

    /**
     * 発送済みの更新の結果：更新できた／取消済み／存在しない（Service の戻り値）。取消済み・存在しない行は何も変えない
     */
    #[Test]
    public function 発送済みの更新の結果は存在と取消で決まる(): void
    {
        $purchases = app(AdminPurchaseService::class);
        $reservations = app(AdminReservationService::class);
        $order = $this->makeOrder();
        $canceledOrder = $this->makeOrder(overrides: ['canceled_at' => '2026-11-05 10:00:00']);
        $reservation = $this->makeAdminReservation();
        $canceledReservation = $this->makeAdminReservation(overrides: ['canceled_at' => '2026-11-05 10:00:00']);

        $this->assertSame(ShippedUpdateResult::Updated, $purchases->updateShipped($order->id, true));
        // 同じ値でも成功（MySQL は更新の件数が 0 になる）
        $this->assertSame(ShippedUpdateResult::Updated, $purchases->updateShipped($order->id, true));
        $this->assertSame(ShippedUpdateResult::Updated, $purchases->updateShipped($order->id, false));
        $this->assertSame(ShippedUpdateResult::Canceled, $purchases->updateShipped($canceledOrder->id, true));
        $this->assertSame(ShippedUpdateResult::NotFound, $purchases->updateShipped(999999, true));
        $this->assertSame(ShippedUpdateResult::Updated, $reservations->updateShipped($reservation->id, true));
        $this->assertSame(ShippedUpdateResult::Canceled, $reservations->updateShipped($canceledReservation->id, true));
        $this->assertSame(ShippedUpdateResult::NotFound, $reservations->updateShipped(999999, true));
        $this->assertFalse(Purchase_history::findOrFail($canceledOrder->id)->shipped);
        $this->assertFalse(Reservation::findOrFail($canceledReservation->id)->shipped);
    }

    /**
     * 存在しない行・物理削除された行への更新は、リクエストを通して 404 と「見つかりません」を返す。取消済みの409とは区別する
     */
    #[Test]
    #[DataProvider('types')]
    public function 存在しない行への更新は404を返す(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeTarget($type);
        $deleted = $this->makeTarget($type, ['shipped' => true]);
        $deleted->delete();
        $service = $type === AdminListType::Purchase ? app(AdminPurchaseService::class) : app(AdminReservationService::class);
        $this->assertSame(ShippedUpdateResult::NotFound, $service->updateShipped($deleted->id, true));

        foreach ([$deleted->id, 999999] as $id) {
            $this->postJson(route($type->updateRoute()), ['id' => $id, 'shipped' => false])
                ->assertNotFound()
                ->assertExactJson(['message' => $type->subject().'が見つかりません。']);
        }
        // 値が不正なときは、存在しないIDでも 422
        $this->postJson(route($type->updateRoute()), ['id' => 999999, 'shipped' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('shipped');
        $this->assertFalse($target->fresh()->shipped);
    }

    /**
     * 404・409 の文言は ShippedUpdateResult にあり、コントローラーの JSON も一覧のスクリプトもそこから読む（直書きは 1 か所だけ）
     */
    #[Test]
    #[DataProvider('types')]
    public function 更新の結果の文言は列挙型から読む(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $target = $this->makeTarget($type, ['canceled_at' => '2026-11-05 10:00:00']);
        $subject = $type->subject();
        $conflict = '取り消されているため、発送状況を変更できません。ページを再読み込みしてください。';

        // JSON の本文
        $this->assertNull(ShippedUpdateResult::Updated->message($type));
        $this->assertSame("{$subject}が見つかりません。", ShippedUpdateResult::NotFound->message($type));
        $this->assertSame($conflict, ShippedUpdateResult::Canceled->message($type));
        // 一覧の案内
        $this->assertNull(ShippedUpdateResult::Updated->listMessage($type));
        $this->assertSame("{$subject}が見つかりません。ページを再読み込みしてください。", ShippedUpdateResult::NotFound->listMessage($type));
        $this->assertSame($conflict, ShippedUpdateResult::Canceled->listMessage($type));

        $this->postJson(route($type->updateRoute()), ['id' => $target->id, 'shipped' => true])->assertStatus(409)->assertExactJson(['message' => $conflict]);
        $html = $this->get(route($type->listRoute()))->assertOk()->getContent();
        // 一覧のスクリプトには、@js で出力された文言が入る（コンソールで文字列として壊れないよう、日本語は \u エスケープ）
        $this->assertStringContainsString(trim((string) Blade::render('@js($m)', ['m' => $conflict])), $html);
        $this->assertStringContainsString(trim((string) Blade::render('@js($m)', ['m' => "{$subject}が見つかりません。ページを再読み込みしてください。"])), $html);
        // 文言の直書きは列挙型の 1 か所だけ
        foreach (['Http/Controllers/AdminPurchaseController.php', 'Http/Controllers/AdminReservationController.php', 'Http/Responses/OrderCancelResponder.php', '../resources/views/admin/partials/order-list.blade.php'] as $path) {
            $this->assertStringNotContainsString('取り消されているため', (string) file_get_contents(app_path($path)), $path);
            $this->assertStringNotContainsString('が見つかりません。ページを再読み込み', (string) file_get_contents(app_path($path)), $path);
        }
    }

    /**
     * 一覧の x-data の JS の文字列（URL・CSRFトークン・文言）は、すべて @js で出力される（{{ }} を JS の文字列に埋め込まない）
     */
    #[Test]
    #[DataProvider('types')]
    public function 一覧のx_dataは_jsで出力される(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->makeTarget($type);

        $html = $this->get(route($type->listRoute()))->assertOk()->getContent();
        // 即時保存の x-data は共通部品 <x-admin-instant-save> にある
        $source = (string) file_get_contents(resource_path('views/components/admin-instant-save.blade.php'));
        $script = substr($source, (int) strpos($source, 'x-data="{'), (int) strpos($source, '}"', (int) strpos($source, 'x-data="{')) - (int) strpos($source, 'x-data="{'));

        $this->assertStringNotContainsString("'{{", $script);
        $this->assertStringContainsString('@js(', $script);
        // 出力された HTML には、URL もトークンも、単一引用符の JS 文字列として入る
        $this->assertStringContainsString("fetch('".str_replace('/', '\\/', route($type->updateRoute()))."'", $html);
        $this->assertMatchesRegularExpression("#'X-CSRF-TOKEN': '[A-Za-z0-9]{40}'#", $html);
    }

    /**
     * 一覧のスクリプトは、404 のとき「見つかりません」の案内を出す
     */
    #[Test]
    #[DataProvider('types')]
    public function 一覧のスクリプトは404のとき見つかりませんの案内を出す(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->makeTarget($type);

        $html = $this->get(route($type->listRoute()))->assertOk()->getContent();

        $this->assertStringContainsString('response.status === 404', $html);
        $this->assertStringContainsString(trim((string) Blade::render('@js($m)', ['m' => $type->subject().'が見つかりません。ページを再読み込みしてください。'])), $html);
    }

    /**
     * 出力件数を別に数えるかは、発送状況が空のときだけ（AdminListCriteria に 1 つにまとめた規則）
     */
    #[Test]
    public function 出力件数を別に数えるのは発送状況が空のときだけ(): void
    {
        $this->assertTrue(AdminListCriteria::fromConditions([])->exportNeedsOwnCount());
        $this->assertTrue(AdminListCriteria::fromConditions(['shipped' => ''])->exportNeedsOwnCount());
        foreach (['0', '1', 'canceled'] as $shipped) {
            $this->assertFalse(AdminListCriteria::fromConditions(['shipped' => $shipped])->exportNeedsOwnCount(), $shipped);
        }
    }

    /**
     * 発送状況が「取消済」のエクスポートのファイル名は「〜一覧_取消済_YYYYMMDD.xlsx」。それ以外は今までどおり。列とシート名は変わらない（AC-15）
     */
    #[Test]
    #[DataProvider('types')]
    public function 取消済のエクスポートはファイル名に取消済が入る(AdminListType $type): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 7, 0, 30, 0, 'Asia/Tokyo'));
        $this->loginAsAdmin();
        $this->makeThree($type);
        $subject = $type->subject();

        $canceled = $this->post(route('admin.export'), ['type' => $type->value, 'shipped' => 'canceled'])->assertOk();
        $disposition = $canceled->headers->get('Content-Disposition');
        $this->assertStringContainsString("filename*=utf-8''".rawurlencode("{$subject}一覧_取消済_20261107.xlsx"), $disposition);
        $this->assertStringContainsString("filename={$type->value}_canceled_20261107.xlsx", $disposition);
        $this->assertSame("{$subject}一覧", $this->sheet($canceled)->getTitle());

        foreach ([[], ['shipped' => ''], ['shipped' => '0'], ['shipped' => '1']] as $conditions) {
            $disposition = $this->post(route('admin.export'), ['type' => $type->value, ...$conditions])->assertOk()->headers->get('Content-Disposition');
            $this->assertStringContainsString("filename*=utf-8''".rawurlencode("{$subject}一覧_20261107.xlsx"), $disposition);
            $this->assertStringContainsString("filename={$type->value}_20261107.xlsx", $disposition);
            $this->assertStringNotContainsString('取消済', rawurldecode($disposition));
        }
    }

    /**
     * エクスポートのファイル名の日付は、日本語と ASCII で必ず揃う（日時を 1 回だけ取る。日付の変わり目で 2 回目の取得が翌日になってもずれない）
     */
    #[Test]
    #[DataProvider('types')]
    public function ファイル名の日付は1回の取得で揃う(AdminListType $type): void
    {
        $this->loginAsAdmin();
        $this->makeThree($type);
        $subject = $type->subject();
        $first = Carbon::create(2026, 11, 7, 23, 59, 59, 'Asia/Tokyo');
        $second = $first->copy()->addSecond();
        $this->assertNotSame($first->format('Ymd'), $second->format('Ymd'));

        // 日時を渡すと、その日時の日付が日本語・ASCII の両方に入る
        $export = $type === AdminListType::Purchase ? PurchaseExport::class : ReservationExport::class;
        $this->assertSame("{$subject}一覧_取消済_20261107.xlsx", $export::fileName(true, $first));
        $this->assertSame("{$type->value}_canceled_20261107.xlsx", $export::asciiFileName(true, $first));
        $this->assertSame("{$subject}一覧_20261108.xlsx", $export::fileName(false, $second));

        // 応答では、日本語と ASCII のどちらも同じ日付。日時の取得は download() の中の 1 回だけ
        $source = (string) file_get_contents(app_path('Http/Controllers/AdminExportController.php'));
        $this->assertSame(1, substr_count($source, 'Date::now()'));
        Carbon::setTestNow(Carbon::create(2026, 11, 7, 23, 59, 59, 'Asia/Tokyo'));
        $disposition = $this->post(route('admin.export'), ['type' => $type->value])->assertOk()->headers->get('Content-Disposition');
        $this->assertStringContainsString(rawurlencode("{$subject}一覧_20261107.xlsx"), $disposition);
        $this->assertStringContainsString("filename={$type->value}_20261107.xlsx", $disposition);
    }

    /**
     * 「取消済」の判定は検索条件（AdminListCriteria）で行い、エクスポートのコントローラーは文字列を比べない
     */
    #[Test]
    public function エクスポートのコントローラーは発送状況の文字列を比べない(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/AdminExportController.php'));

        $this->assertStringNotContainsString("['shipped']", $source);
        $this->assertStringContainsString('AdminListCriteria::fromConditions', $source);
    }

    /**
     * 「発送済」の検索では、取消済みを含めない（一覧の件数とエクスポートの件数が構造上同じになる）。取消済みで発送済みの（ありえない）データも出ない
     */
    #[Test]
    #[DataProvider('types')]
    public function 発送済の検索に取消済みは出ない(AdminListType $type): void
    {
        $shipped = $this->makeTarget($type, ['shipped' => true]);
        $this->makeTarget($type, ['shipped' => true, 'canceled_at' => '2026-11-05 10:00:00']);
        $this->makeTarget($type, ['canceled_at' => '2026-11-05 10:00:00']);

        $this->assertSame([$shipped->id], $this->searchIds($type, ['shipped' => '1']));
        $this->assertSame([$shipped->id], $this->exportedIds($type, ['shipped' => '1']));
        $this->loginAsAdmin();
        $this->assertSame(1, $this->get(route($type->searchRoute(), ['shipped' => '1']))->viewData('exportCount'));
    }
}
