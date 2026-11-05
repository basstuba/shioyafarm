<?php

namespace App\Exports;

use App\Models\Purchase_history;
use App\Models\Purchase_history_item;
use Generator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\LazyCollection;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Override;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * 管理画面の注文一覧のエクスポート（1商品1行。注文管理番号・注文者・配送先は全行に入れる）
 *
 * 注文は500件ずつに分けて読み込む（読み込みを分けているだけで、書き出すセルはメモリに保持する。件数が非常に多いときは、メモリの上限に注意する）。
 * 会員が入力する注文者名・配送先などが「=」などで始まっても数式にならないよう、文字列のセルはすべて文字列型で書き出す（数式インジェクションの対策）。数値（注文管理番号・数量）は数値のまま。
 */
class PurchaseExport extends DefaultValueBinder implements FromGenerator, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithHeadings, WithTitle
{
    /** 配送先電話番号の列（先頭の0が消えないよう、文字列で出す） */
    private const string TELL_COLUMN = 'I';

    /** 数値のまま出す列（注文管理番号・商品数量） */
    private const array NUMBER_COLUMNS = ['A', 'F'];

    /**
     * @param  LazyCollection<int, Purchase_history>  $purchases  注文者・購入商品を Eager Load した、新しい順の注文
     */
    public function __construct(private readonly LazyCollection $purchases) {}

    /**
     * ファイル名（出力した日の日本時間）
     */
    public static function fileName(): string
    {
        return '注文一覧_'.Date::now()->format('Ymd').'.xlsx';
    }

    /**
     * ダウンロードのファイル名の ASCII 側（日本語が使えない環境向けの代わりの名前）
     */
    public static function asciiFileName(): string
    {
        return 'purchase_'.Date::now()->format('Ymd').'.xlsx';
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function headings(): array
    {
        return ['注文管理番号', '注文者', '注文日', '注文商品', '商品重量', '商品数量', '配送先氏名', '配送先住所', '配送先電話番号'];
    }

    /**
     * 1商品1行。同じ注文の商品は明細の順
     *
     * @return Generator<int, list<int|string>>
     */
    #[Override]
    public function generator(): Generator
    {
        foreach ($this->purchases as $purchase) {
            foreach ($purchase->purchaseHistoryItems as $line) {
                yield $this->row($purchase, $line);
            }
        }
    }

    #[Override]
    public function title(): string
    {
        return '注文一覧';
    }

    /**
     * @return array<string, int>
     */
    #[Override]
    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 18, 'C' => 14, 'D' => 20, 'E' => 12, 'F' => 12, 'G' => 18, 'H' => 28, 'I' => 18];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function columnFormats(): array
    {
        return [self::TELL_COLUMN => NumberFormat::FORMAT_TEXT];
    }

    #[Override]
    public function bindValue(Cell $cell, mixed $value): bool
    {
        // 数値の列（注文管理番号・商品数量）以外は、すべて文字列型で書く（電話番号の先頭の0を残し、「=」で始まる値を数式にしない）。
        // 値は文字列として渡されるため、型ではなく列で判定する
        if (! in_array($cell->getColumn(), self::NUMBER_COLUMNS, true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /**
     * 注文の1商品分の行
     *
     * @return list<int|string>
     */
    private function row(Purchase_history $purchase, Purchase_history_item $line): array
    {
        return [
            $purchase->id,
            $purchase->user->full_name,
            $purchase->created_at?->format('Y/m/d') ?? '',
            $line->item->name,
            $line->item->weight.'kg',
            $line->quantity,
            $purchase->delivery_name,
            $purchase->delivery_prefecture.$purchase->delivery_city,
            $purchase->delivery_tell,
        ];
    }
}
