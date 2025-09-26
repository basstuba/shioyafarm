<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Delivery_address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'delivery_prefecture',
        'delivery_city',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
