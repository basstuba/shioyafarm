<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 予約
 */
class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'item_id',
        'delivery_name',
        'delivery_prefecture',
        'delivery_city',
        'delivery_tell',
        'quantity',
        'price',
        'subtotal',
        'payment_method',
        'shipping_fee',
        'total_price',
    ];

    /**
     * 予約した会員
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 予約した商品
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * 銀行振込の振込先（銀行振込の予約だけ）
     *
     * @return HasOne<Reservation_bank_transfer, $this>
     */
    public function reservationBankTransfer(): HasOne
    {
        return $this->hasOne(Reservation_bank_transfer::class);
    }
}
