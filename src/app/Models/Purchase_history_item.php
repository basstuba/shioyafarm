<?php

namespace App\Models;

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
        'quantity',
        'price',
        'subtotal',
    ];

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
     * 購入した商品
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
