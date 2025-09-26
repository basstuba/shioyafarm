<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Item;

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
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function item() {
        return $this->belongsTo(Item::class);
    }
}
