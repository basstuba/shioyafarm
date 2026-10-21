<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 購入履歴
 */
class Purchase_history extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'delivery_name',
        'delivery_prefecture',
        'delivery_city',
        'delivery_tell',
        'payment_method',
        'shipping_fee',
        'cod_fee',
        'total_price',
    ];

    /**
     * 購入した会員
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 購入明細
     *
     * @return HasMany<Purchase_history_item, $this>
     */
    public function purchaseHistoryItems(): HasMany
    {
        return $this->hasMany(Purchase_history_item::class);
    }

    /**
     * 銀行振込の振込先（銀行振込の注文だけ）
     *
     * @return HasOne<Purchase_bank_transfer, $this>
     */
    public function purchaseBankTransfer(): HasOne
    {
        return $this->hasOne(Purchase_bank_transfer::class);
    }
}
