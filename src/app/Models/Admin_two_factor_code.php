<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * 管理者の二段階認証のコード（管理者1人につき1件。コードはハッシュ化して保存する）
 */
class Admin_two_factor_code extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'code',
        'attempts',
        'expires_at',
        'sent_at',
    ];

    /**
     * シリアライズ時に隠す属性
     *
     * @var list<string>
     */
    protected $hidden = [
        'code',
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
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * このコードの管理者
     *
     * @return BelongsTo<Admin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
