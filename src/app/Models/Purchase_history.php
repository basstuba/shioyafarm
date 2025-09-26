<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Purchase_history_item;

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

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function purchaseHistoryItems() {
        return $this->hasMany(Purchase_history_item::class);
    }
}
