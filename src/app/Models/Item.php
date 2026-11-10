<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 商品（論理削除。削除した商品は公開側・管理画面の一覧から消えるが、過去の注文・予約の記録は残る）
 */
class Item extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** 管理画面で、対象の商品が見つからないとき（入力の誤り・削除済み・存在しない商品）の案内 */
    public const string NOT_FOUND_MESSAGE = '商品が見つかりません。';

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

    /**
     * この商品の予約
     *
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * この商品が含まれる購入明細
     *
     * @return HasMany<Purchase_history_item, $this>
     */
    public function purchaseHistoryItems(): HasMany
    {
        return $this->hasMany(Purchase_history_item::class);
    }
}
