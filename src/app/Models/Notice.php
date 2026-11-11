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

    /** 管理画面で、対象のお知らせが見つからないとき（入力の誤り・削除済み・存在しないお知らせ）の案内 */
    public const string NOT_FOUND_MESSAGE = 'お知らせが見つかりません。';

    protected $fillable = [
        'title',
        'text',
    ];
}
