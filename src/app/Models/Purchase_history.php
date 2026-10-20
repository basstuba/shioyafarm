<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
