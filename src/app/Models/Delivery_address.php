<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 配送先住所
 */
class Delivery_address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'delivery_prefecture',
        'delivery_city',
    ];

    /**
     * この住所を登録した会員
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
