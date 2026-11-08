<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * お問い合わせ
 */
class Inquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'last_name',
        'first_name',
        'email',
        'detail',
        'addressed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'addressed' => 'boolean',
        ];
    }

    /**
     * 姓と名をスペースでつなげた氏名（会員の full_name と同じ書き方）
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => $this->last_name.' '.$this->first_name);
    }
}
