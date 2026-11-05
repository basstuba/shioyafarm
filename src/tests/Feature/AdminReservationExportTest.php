<?php

namespace Tests\Feature;

use App\Exports\ReservationExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の予約のエクスポート（admin.export、type=reservation）と、種類ごとのエラーの戻り先のテスト
 */
class AdminReservationExportTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 管理者としてエクスポートする
     *
     * @param  array<string, mixed>  $data
     */
    private function export(array $data = ['type' => 'reservation']): TestResponse
    {
        $this->loginAsAdmin();

        return $this->post(route('admin.export'), $data);
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
     * 出力した日（日本時間）のファイル名で .xlsx がダウンロードされ、シート名は「予約一覧」
     */
    #[Test]
    public function ファイル名は出力した日の日付でシート名は予約一覧になる(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 0, 30, 0, 'Asia/Tokyo'));
        $this->makeAdminReservation();

        $response = $this->export()->assertOk();

        $this->assertSame('予約一覧_20261105.xlsx', ReservationExport::fileName());
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString("filename*=utf-8''".rawurlencode('予約一覧_20261105.xlsx'), $disposition);
        $this->assertStringContainsString('filename=reservation_20261105.xlsx', $disposition);
        $this->assertSame('予約一覧', $this->sheet($response)->getTitle());
    }

    /**
     * 列の順と、1予約1行。発送済みの列は足さない。新しい予約が上
     */
    #[Test]
    public function 列の順と1予約1行で出力される(): void
    {
        $older = $this->makeAdminReservation(
            $this->makeUser('山田', '太郎'),
            '2026-11-04 09:30:00',
            overrides: ['quantity' => 2, 'delivery_name' => '山田 花子', 'delivery_prefecture' => '北海道', 'delivery_city' => '札幌市', 'delivery_tell' => '09012345678', 'shipped' => true],
        );
        $newer = $this->makeAdminReservation($this->makeUser('佐藤', '花子'), '2026-11-05 10:00:00', overrides: ['delivery_prefecture' => '東京都', 'delivery_city' => '千代田区', 'delivery_tell' => '0312345678']);

        $sheet = $this->sheet($this->export());

        $this->assertSame(
            ['予約管理番号', '予約者', '予約日', '予約商品', '商品重量', '商品数量', '配送先氏名', '配送先住所', '配送先電話番号'],
            $sheet->toArray(null, false, false, false)[0],
        );
        $this->assertSame([
            [$newer->id, '佐藤 花子', '2026/11/05', '北の恵みセット', '10kg', 1, $newer->delivery_name, '東京都千代田区', '0312345678'],
            [$older->id, '山田 太郎', '2026/11/04', '北の恵みセット', '10kg', 2, '山田 花子', '北海道札幌市', '09012345678'],
        ], $this->rows($sheet));
    }

    /**
     * 電話番号は文字列で先頭の0が消えず、「=」などで始まる値は数式にならない。管理番号・数量は数値のまま
     */
    #[Test]
    public function 数式に見える値は文字列として出力され電話番号の先頭の0が残る(): void
    {
        $user = $this->makeUser('=1+1', '+SUM(A1)');
        $reservation = $this->makeAdminReservation($user, overrides: [
            'delivery_name' => '=HYPERLINK("http://example.com/","クリック")',
            'delivery_prefecture' => '@北海道',
            'delivery_city' => '-札幌市',
            'delivery_tell' => '=0901234567',
        ]);
        $normal = $this->makeAdminReservation(overrides: ['delivery_tell' => '09012345678']);

        $sheet = $this->sheet($this->export());

        foreach (['B3' => '=1+1 +SUM(A1)', 'G3' => '=HYPERLINK("http://example.com/","クリック")', 'H3' => '@北海道-札幌市', 'I3' => '=0901234567', 'I2' => '09012345678'] as $coordinate => $expected) {
            $cell = $sheet->getCell($coordinate);
            $this->assertSame($expected, $cell->getValue(), $coordinate);
            $this->assertSame('s', $cell->getDataType(), $coordinate);
            $this->assertFalse($cell->isFormula(), $coordinate);
        }
        $this->assertSame('n', $sheet->getCell('A3')->getDataType());
        $this->assertSame($reservation->id, $sheet->getCell('A3')->getValue());
        $this->assertSame($normal->id, $sheet->getCell('A2')->getValue());
        $this->assertSame('n', $sheet->getCell('F3')->getDataType());
    }

    /**
     * 退会済みの会員の予約も含まれる
     */
    #[Test]
    public function 退会済みの会員の予約も含まれる(): void
    {
        $user = $this->makeUser('伊藤', '健');
        $this->makeAdminReservation($user);
        $user->delete();

        $rows = $this->rows($this->sheet($this->export()));

        $this->assertCount(1, $rows);
        $this->assertSame('伊藤 健', $rows[0][1]);
    }

    /**
     * 検索中は、その条件に合う予約が全件（10件を超えても）出力される。ページ番号は無視する
     */
    #[Test]
    public function 検索条件に合う予約が全件出力されページ番号は無視される(): void
    {
        $yamada = $this->makeUser('山田', '太郎');
        for ($i = 0; $i < 12; $i++) {
            $this->makeAdminReservation($yamada, '2026-11-04 10:00:00');
        }
        $this->makeAdminReservation($yamada, '2026-11-03 10:00:00');
        $this->makeAdminReservation($this->makeUser('佐藤', '花子'), '2026-11-04 10:00:00');

        $rows = $this->rows($this->sheet($this->export(['type' => 'reservation', 'date' => '2026-11-04', 'name' => '山田', 'shipped' => '', 'page' => 2])));

        $this->assertCount(12, $rows);
        $this->assertSame(['山田 太郎'], array_values(array_unique(array_column($rows, 1))));
    }

    /**
     * 検索していないときは全件が、新しい順で出力される
     */
    #[Test]
    public function 検索していないときは全件が出力される(): void
    {
        $this->makeAdminReservations(25);

        $rows = $this->rows($this->sheet($this->export()));

        $this->assertCount(25, $rows);
        $ids = array_column($rows, 0);
        $sorted = $ids;
        rsort($sorted);
        $this->assertSame($sorted, $ids);
    }

    /**
     * 「未発送」で検索してエクスポートすると未発送の予約だけが出力され、列は増えない
     */
    #[Test]
    public function 未発送で検索すると未発送の予約だけが出力される(): void
    {
        $unshipped = $this->makeAdminReservation();
        $this->makeAdminReservation(overrides: ['shipped' => true]);

        $rows = $this->rows($this->sheet($this->export(['type' => 'reservation', 'shipped' => '0'])));

        $this->assertSame([$unshipped->id], array_column($rows, 0));
        $this->assertCount(9, $rows[0]);
    }

    /**
     * 予約がないときは、見出しだけのファイルになる
     */
    #[Test]
    public function 予約がないときは見出しだけが出力される(): void
    {
        $sheet = $this->sheet($this->export());

        $this->assertSame([], $this->rows($sheet));
        $this->assertSame('予約管理番号', $sheet->getCell('A1')->getValue());
    }

    /**
     * 注文のエクスポートに予約は含まれず、予約のエクスポートに注文は含まれない
     */
    #[Test]
    public function 注文と予約は別のファイルになる(): void
    {
        $order = $this->makeOrder();
        $reservation = $this->makeAdminReservation();

        $this->assertSame([$reservation->id], array_column($this->rows($this->sheet($this->export())), 0));
        $this->assertSame([$order->id], array_column($this->rows($this->sheet($this->post(route('admin.export'), ['type' => 'purchase']))), 0));
    }

    /**
     * 予約の検索条件の誤りは、予約一覧に戻って予約の文言のエラーになる（ファイルは出力されない）
     */
    #[Test]
    public function 予約の検索条件の誤りは予約一覧に戻り予約の文言になる(): void
    {
        $this->makeAdminReservation();

        $this->export(['type' => 'reservation', 'date' => 'abc'])
            ->assertRedirect(route('admin.reservations.index'))
            ->assertSessionHasErrors(['date' => '予約日は日付で入力してください。'])
            ->assertHeaderMissing('Content-Disposition');

        $this->post(route('admin.export'), ['type' => 'reservation', 'name' => str_repeat('あ', 101)])
            ->assertRedirect(route('admin.reservations.index'))
            ->assertSessionHasErrors(['name' => '予約者名は100文字以内で入力してください。']);
        $this->post(route('admin.export'), ['type' => 'reservation', 'shipped' => '9'])
            ->assertRedirect(route('admin.reservations.index'))
            ->assertSessionHasErrors('shipped');

        // 戻った予約一覧にエラーが表示される
        $this->followingRedirects()
            ->post(route('admin.export'), ['type' => 'reservation', 'date' => 'abc'])
            ->assertOk()
            ->assertViewIs('admin.reservations')
            ->assertSee('予約日は日付で入力してください。');
    }

    /**
     * 未ログイン・会員としてのログインでは、予約のエクスポートはできない
     */
    #[Test]
    public function 未ログインと会員は予約をエクスポートできない(): void
    {
        $this->makeAdminReservation();

        $this->post(route('admin.export'), ['type' => 'reservation'])
            ->assertRedirect(route('admin.login'))
            ->assertHeaderMissing('Content-Disposition');

        $this->loginAsMember();
        $this->post(route('admin.export'), ['type' => 'reservation'])->assertRedirect(route('admin.login'));
    }

    /**
     * 壊れた文字（不正な UTF-8）を含む予約者名は、予約一覧に戻ってエラーになり、ファイルは出力されない
     */
    #[Test]
    public function 不正なutf8の予約者名はエクスポートできない(): void
    {
        $this->makeAdminReservation($this->makeUser('山田', '太郎'));
        $this->makeAdminReservation($this->makeUser('佐藤', '花子'));

        $this->export(['type' => 'reservation', 'name' => "山田\xFF"])
            ->assertRedirect(route('admin.reservations.index'))
            ->assertSessionHasErrors(['name' => '予約者名は文字で入力してください。'])
            ->assertHeaderMissing('Content-Disposition');
    }

    /**
     * 予約のエクスポートは Eager Load され、件数が増えてもクエリ数が変わらない
     */
    #[Test]
    public function 予約のエクスポートのクエリ数は件数に依らない(): void
    {
        $this->loginAsAdmin();
        $this->makeAdminReservations(2);
        DB::enableQueryLog();
        $this->post(route('admin.export'), ['type' => 'reservation'])->assertOk();
        $few = count(DB::getQueryLog());

        $this->makeAdminReservations(8);
        DB::flushQueryLog();
        $this->post(route('admin.export'), ['type' => 'reservation'])->assertOk();
        $many = count(DB::getQueryLog());

        $this->assertSame($few, $many);
    }
}
