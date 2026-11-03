<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Override;

/**
 * 管理者
 *
 * 会員（User）とは別のテーブル・別のガード（admin）でログインする。
 * 「ログイン状態を保持する」は使わないため、remember_token のカラムはない。
 */
class Admin extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * シリアライズ時に隠す属性
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * キャスト
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * remember_token のカラムがないため、トークン名を空にして読み書きしない
     */
    #[Override]
    public function getRememberTokenName(): string
    {
        return '';
    }
}
