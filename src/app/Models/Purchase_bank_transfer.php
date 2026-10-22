<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 銀行振込の振込先データ（銀行振込の購入履歴1件につき1件）
 *
 * account_type は Stripe の値（futsu: 普通、toza: 当座）のまま保存し、日本語表記は表示側で行う。
 */
class Purchase_bank_transfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_history_id',
        'stripe_payment_intent_id',
        'bank_name',
        'branch_name',
        'account_type',
        'account_number',
        'account_holder_name',
        'amount',
    ];

    /**
     * 振込先が属する購入履歴
     *
     * @return BelongsTo<Purchase_history, $this>
     */
    public function purchaseHistory(): BelongsTo
    {
        return $this->belongsTo(Purchase_history::class);
    }
}
