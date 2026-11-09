<?php

namespace App\Models;

use App\Support\ItemLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 購入履歴の明細
 */
class Purchase_history_item extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_history_id',
        'item_id',
        'item_name',
        'item_weight',
        'quantity',
        'price',
        'subtotal',
    ];

    /**
     * 注文した時点の商品名と重さ（「じゃがいも 2kg」）
     */
    public function itemLabel(): string
    {
        return ItemLabel::of($this->item_name, $this->item_weight);
    }

    /**
     * 明細が属する購入履歴
     *
     * @return BelongsTo<Purchase_history, $this>
     */
    public function purchaseHistory(): BelongsTo
    {
        return $this->belongsTo(Purchase_history::class);
    }

    /**
     * 購入した商品（論理削除済みを含む。表示には注文した時点の item_name・item_weight を使う）
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }
}
