<?php

namespace Tests\Feature;

use App\Exports\PurchaseExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面のエクスポート（admin.export）のテスト
 */
class AdminExportTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 管理者としてエクスポートする
     *
     * @param  array<string, mixed>  $data
     */
    private function export(array $data = ['type' => 'purchase']): TestResponse
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
     * シートの行を（見出しを除いて）配列にして返す。電話番号など数字に見える値も、セルの値のまま返す
     *
     * @return list<list<mixed>>
     */
    private function rows(Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, false, false, false);

        return array_slice($rows, 1);
    }

    /**
     * 出力した日（日本時間）のファイル名で .xlsx がダウンロードされ、シート名は「注文一覧」
     */
    #[Test]
    public function ファイル名は出力した日の日付でシート名は注文一覧になる(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 4, 0, 30, 0, 'Asia/Tokyo'));
        $this->makeOrder();

        $response = $this->export()->assertOk();

        $this->assertSame('注文一覧_20261104.xlsx', PurchaseExport::fileName());
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $disposition = $response->headers->get('Content-Disposition');
        // 日本語の名前（filename*）と、ASCII 側の有効な名前（filename）
        $this->assertStringContainsString("filename*=utf-8''".rawurlencode('注文一覧_20261104.xlsx'), $disposition);
        $this->assertStringContainsString('filename=purchase_20261104.xlsx', $disposition);
        $this->assertStringNotContainsString('filename=_', $disposition);
        $this->assertSame('注文一覧', $this->sheet($response)->getTitle());
    }

    /**
     * 列の順と、1商品1行で注文管理番号・注文者・配送先が全行に入ること
     */
    #[Test]
    public function 列の順と1商品1行で共通の項目が全行に入る(): void
    {
        $user = $this->makeUser('山田', '太郎');
        $multi = $this->makeOrder(
            $user,
            '2026-11-04 09:30:00',
            [['じゃがいも', 2, 330, 2], ['玉ねぎ', 5, 795, 1], ['にんじん', 2, 418, 1]],
            overrides: ['delivery_name' => '山田 花子', 'delivery_prefecture' => '北海道', 'delivery_city' => '札幌市', 'delivery_tell' => '09012345678'],
        );
        $single = $this->makeOrder($this->makeUser('佐藤', '花子'), '2026-11-05 10:00:00', [['人参', 10, 500, 3]], overrides: ['delivery_prefecture' => '東京都', 'delivery_city' => '千代田区', 'delivery_tell' => '0312345678']);

        $sheet = $this->sheet($this->export());

        $this->assertSame(
            ['注文管理番号', '注文者', '注文日', '注文商品', '商品重量', '商品数量', '配送先氏名', '配送先住所', '配送先電話番号'],
            $sheet->toArray(null, false, false, false)[0],
        );
        // 新しい注文が上。同じ注文の商品は明細の順
        $this->assertSame([
            [$single->id, '佐藤 花子', '2026/11/05', '人参', '10kg', 3, $single->delivery_name, '東京都千代田区', '0312345678'],
            [$multi->id, '山田 太郎', '2026/11/04', 'じゃがいも', '2kg', 2, '山田 花子', '北海道札幌市', '09012345678'],
            [$multi->id, '山田 太郎', '2026/11/04', '玉ねぎ', '5kg', 1, '山田 花子', '北海道札幌市', '09012345678'],
            [$multi->id, '山田 太郎', '2026/11/04', 'にんじん', '2kg', 1, '山田 花子', '北海道札幌市', '09012345678'],
        ], $this->rows($sheet));
    }

    /**
     * 電話番号の先頭の0が消えない（文字列で出る）
     */
    #[Test]
    public function 電話番号は文字列で先頭の0が消えない(): void
    {
        $this->makeOrder(overrides: ['delivery_tell' => '09012345678']);

        $sheet = $this->sheet($this->export());

        $cell = $sheet->getCell('I2');
        $this->assertSame('09012345678', $cell->getValue());
        $this->assertSame('s', $cell->getDataType());
    }

    /**
     * 会員が入力した値が「=」などで始まっても数式にならず、文字列のまま出力される（数式インジェクションの対策）。数値は数値のまま
     */
    #[Test]
    public function 数式に見える値は文字列として出力され数式にならない(): void
    {
        $formula = '=HYPERLINK("http://example.com/","クリック")';
        $user = $this->makeUser('=1+1', '+SUM(A1)');
        $order = $this->makeOrder($user, overrides: [
            'delivery_name' => $formula,
            'delivery_prefecture' => '@北海道',
            'delivery_city' => '-札幌市',
            'delivery_tell' => '=0901234567',
        ]);

        $sheet = $this->sheet($this->export());

        foreach (['B2' => '=1+1 +SUM(A1)', 'G2' => $formula, 'H2' => '@北海道-札幌市', 'I2' => '=0901234567'] as $coordinate => $expected) {
            $cell = $sheet->getCell($coordinate);
            $this->assertSame($expected, $cell->getValue(), $coordinate);
            $this->assertSame('s', $cell->getDataType(), $coordinate);
            $this->assertFalse($cell->isFormula(), $coordinate);
        }
        // 注文管理番号・数量は数値のまま
        $this->assertSame('n', $sheet->getCell('A2')->getDataType());
        $this->assertSame($order->id, $sheet->getCell('A2')->getValue());
        $this->assertSame('n', $sheet->getCell('F2')->getDataType());
    }

    /**
     * 退会済みの会員の注文も含まれる
     */
    #[Test]
    public function 退会済みの会員の注文も含まれる(): void
    {
        $user = $this->makeUser('伊藤', '健');
        $this->makeOrder($user);
        $user->delete();

        $rows = $this->rows($this->sheet($this->export()));

        $this->assertCount(1, $rows);
        $this->assertSame('伊藤 健', $rows[0][1]);
    }

    /**
     * 検索中は、その条件に合う注文が全件（10件を超えても）出力される。ページ番号は無視する
     */
    #[Test]
    public function 検索条件に合う注文が全件出力されページ番号は無視される(): void
    {
        $yamada = $this->makeUser('山田', '太郎');
        for ($i = 0; $i < 12; $i++) {
            $this->makeOrder($yamada, '2026-11-04 10:00:00');
        }
        $this->makeOrder($yamada, '2026-11-03 10:00:00');
        $this->makeOrder($this->makeUser('佐藤', '花子'), '2026-11-04 10:00:00');

        $rows = $this->rows($this->sheet($this->export(['type' => 'purchase', 'date' => '2026-11-04', 'name' => '山田', 'shipped' => '', 'page' => 2])));

        $this->assertCount(12, $rows);
        $this->assertSame(['山田 太郎'], array_values(array_unique(array_column($rows, 1))));
        $this->assertSame(['2026/11/04'], array_values(array_unique(array_column($rows, 2))));
    }

    /**
     * 検索していないときは全件（500件ずつ読み込む境目をまたいでも、すべて出力される）
     */
    #[Test]
    public function 検索していないときは全件が出力される(): void
    {
        $this->makeOrders(25);

        $rows = $this->rows($this->sheet($this->export()));

        $this->assertCount(25, $rows);
        $ids = array_column($rows, 0);
        $sorted = $ids;
        rsort($sorted);
        $this->assertSame($sorted, $ids);
    }

    /**
     * 「未発送」で検索してエクスポートすると未発送の注文だけが出力される
     */
    #[Test]
    public function 未発送で検索すると未発送の注文だけが出力される(): void
    {
        $unshipped = $this->makeOrder();
        $this->makeOrder(overrides: ['shipped' => true]);

        $rows = $this->rows($this->sheet($this->export(['type' => 'purchase', 'shipped' => '0'])));

        $this->assertSame([$unshipped->id], array_column($rows, 0));
        // 列は増えない
        $this->assertCount(9, $rows[0]);
    }

    /**
     * 注文がないときは、見出しだけのファイルになる
     */
    #[Test]
    public function 注文がないときは見出しだけが出力される(): void
    {
        $sheet = $this->sheet($this->export());

        $this->assertSame([], $this->rows($sheet));
        $this->assertSame('注文管理番号', $sheet->getCell('A1')->getValue());
    }

    /**
     * 種類の指定がないとき・不正なときはエラーで、ファイルは出力されない
     */
    #[Test]
    public function 種類が不正なときはエラーになる(): void
    {
        $this->makeOrder();

        $this->export(['type' => 'unknown'])
            ->assertRedirect(route('admin.purchase.index'))
            ->assertSessionHasErrors(['type' => 'エクスポートの種類が正しくありません。']);
        $this->post(route('admin.export'), [])->assertSessionHasErrors(['type' => 'エクスポートの種類を指定してください。']);
        $this->post(route('admin.export'), ['type' => ['purchase']])->assertSessionHasErrors('type');
    }

    /**
     * 検索条件の誤り（日付でない注文日など）もエラーになる
     */
    #[Test]
    public function 検索条件が不正なときはエラーになる(): void
    {
        $this->export(['type' => 'purchase', 'date' => 'abc'])->assertSessionHasErrors('date');
        $this->post(route('admin.export'), ['type' => 'purchase', 'shipped' => '9'])->assertSessionHasErrors('shipped');
    }

    /**
     * 未ログインはログインページへ移動し、ファイルは出力されない
     */
    #[Test]
    public function 未ログインではエクスポートできない(): void
    {
        $this->makeOrder();

        $this->post(route('admin.export'), ['type' => 'purchase'])
            ->assertRedirect(route('admin.login'))
            ->assertHeaderMissing('Content-Disposition');
    }

    /**
     * エクスポートの応答も no-store（保存させない）
     */
    #[Test]
    public function エクスポートの応答は保存させない(): void
    {
        $response = $this->export();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
