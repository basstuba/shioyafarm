<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 地域（都道府県）
 */
class Area extends Model
{
    use HasFactory;

    protected $fillable = ['prefecture'];
}
