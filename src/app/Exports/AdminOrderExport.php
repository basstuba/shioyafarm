<?php

namespace App\Exports;

use App\Enums\AdminListType;
use App\Models\Purchase_history;
use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
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
 * 管理画面の注文一覧・予約一覧のエクスポートに共通の列・書式・セルの書き方
 *
 * 列は 管理番号・注文者（予約者）・日付・商品名・商品重量・商品数量・配送先氏名・配送先住所・配送先電話番号。
 * 会員が入力する名前・配送先などが「=」などで始まっても数式にならないよう、文字列のセルはすべて文字列型で書き出す（数式インジェクションの対策）。数値（管理番号・数量）は数値のまま。
 */
abstract class AdminOrderExport extends DefaultValueBinder implements FromGenerator, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithHeadings, WithTitle
{
    /** 配送先電話番号の列（先頭の0が消えないよう、文字列で出す） */
    private const string TELL_COLUMN = 'I';

    /** 数値のまま出す列（管理番号・商品数量） */
    private const array NUMBER_COLUMNS = ['A', 'F'];

    /**
     * どの一覧のエクスポートか（見出し・シート名・ファイル名の「注文」「予約」は、ここから決まる）
     */
    abstract protected static function listType(): AdminListType;

    /**
     * ファイル名（出力した日の日本時間。$at を渡すと、その日時の日付にする。発送状況が「取消済」のときは、取消済みの一覧だとわかる名前にする）
     */
    public static function fileName(bool $canceled = false, ?CarbonInterface $at = null): string
    {
        return static::listType()->subject().'一覧_'.($canceled ? '取消済_' : '').($at ?? Date::now())->format('Ymd').'.xlsx';
    }

    /**
     * ダウンロードのファイル名の ASCII 側（日本語が使えない環境向けの代わりの名前。一覧の種類の値（purchase・reservation）を使う）
     */
    public static function asciiFileName(bool $canceled = false, ?CarbonInterface $at = null): string
    {
        return static::listType()->value.'_'.($canceled ? 'canceled_' : '').($at ?? Date::now())->format('Ymd').'.xlsx';
    }

    #[Override]
    public function title(): string
    {
        return static::listType()->subject().'一覧';
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function headings(): array
    {
        $subject = static::listType()->subject();

        return ["{$subject}管理番号", "{$subject}者", "{$subject}日", "{$subject}商品", '商品重量', '商品数量', '配送先氏名', '配送先住所', '配送先電話番号'];
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
        // 数値の列（管理番号・商品数量）以外は、すべて文字列型で書く（電話番号の先頭の0を残し、「=」で始まる値を数式にしない）。
        // 値は文字列として渡されるため、型ではなく列で判定する
        if (! in_array($cell->getColumn(), self::NUMBER_COLUMNS, true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /**
     * 注文・予約の1商品分の行（会員を Eager Load 済みであること。商品名・重さは注文した時点の値）
     *
     * @return list<int|string>
     */
    protected function row(Purchase_history|Reservation $order, string $itemName, int $itemWeight, int $quantity): array
    {
        return [
            $order->id,
            $order->user->full_name,
            $order->created_at?->format('Y/m/d') ?? '',
            $itemName,
            $itemWeight.'kg',
            $quantity,
            $order->delivery_name,
            $order->delivery_prefecture.$order->delivery_city,
            $order->delivery_tell,
        ];
    }
}
