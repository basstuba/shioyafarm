<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 商品
 */
class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'weight',
        'price',
        'img_url',
        'detail',
        'recommend',
        'is_reserved_only',
        'reservation_limit',
    ];

    /**
     * この商品の予約
     *
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * この商品が含まれる購入明細
     *
     * @return HasMany<Purchase_history_item, $this>
     */
    public function purchaseHistoryItems(): HasMany
    {
        return $this->hasMany(Purchase_history_item::class);
    }
}
