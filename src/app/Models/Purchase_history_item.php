<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Purchase_history;
use App\Models\Item;

class Purchase_history_item extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_history_id',
        'item_id',
        'quantity',
        'price',
        'subtotal',
    ];

    public function purchaseHistory() {
        return $this->belongsTo(Purchase_history::class);
    }

    public function item() {
        return $this->belongsTo(Item::class);
    }
}
