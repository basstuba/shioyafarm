<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Reservation;
use App\Models\Purchase_history_item;

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

    public function reservations() {
        return $this->hasMany(Reservation::class);
    }

    public function purchaseHistoryItems() {
        return $this->hasMany(Purchase_history_item::class);
    }
}
