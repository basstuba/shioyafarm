<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * お知らせ
 */
class Notice extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'text',
    ];
}
