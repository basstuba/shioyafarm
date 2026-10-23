<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 銀行振込の振込先データ（銀行振込の予約1件につき1件）
 *
 * account_type は Stripe の値（futsu: 普通、toza: 当座）のまま保存し、日本語表記は表示側で行う。
 */
class Reservation_bank_transfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'stripe_payment_intent_id',
        'bank_name',
        'branch_name',
        'account_type',
        'account_number',
        'account_holder_name',
        'amount',
    ];

    /**
     * 振込先が属する予約
     *
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
