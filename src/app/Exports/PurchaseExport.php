<?php

namespace App\Exports;

use App\Enums\AdminListType;
use App\Models\Purchase_history;
use Generator;
use Illuminate\Support\LazyCollection;
use Override;

/**
 * 管理画面の注文一覧のエクスポート（1商品1行。注文管理番号・注文者・配送先は全行に入れる）
 *
 * 注文は500件ずつに分けて読み込む（読み込みを分けているだけで、書き出すセルはメモリに保持する。件数が非常に多いときは、メモリの上限に注意する）。
 * 列とセルの書き方は AdminOrderExport。
 */
class PurchaseExport extends AdminOrderExport
{
    /**
     * @param  LazyCollection<int, Purchase_history>  $purchases  注文者・購入商品を Eager Load した、新しい順の注文
     */
    public function __construct(private readonly LazyCollection $purchases) {}

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
                yield $this->row($purchase, $line->item_name, $line->item_weight, $line->quantity);
            }
        }
    }

    #[Override]
    protected static function listType(): AdminListType
    {
        return AdminListType::Purchase;
    }
}
