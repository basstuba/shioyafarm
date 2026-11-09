<?php

namespace App\Models;

use App\Support\ItemLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 予約
 */
class Reservation extends Model
{
    use HasFactory;

    // shipped（発送済み）・canceled_at（取消日時）は一括代入の対象にしない。管理画面の更新（リポジトリの updateShipped・cancel）だけで変える
    protected $fillable = [
        'user_id',
        'item_id',
        'item_name',
        'item_weight',
        'delivery_name',
        'delivery_prefecture',
        'delivery_city',
        'delivery_tell',
        'quantity',
        'price',
        'subtotal',
        'payment_method',
        'shipping_fee',
        'total_price',
    ];

    /**
     * キャストする属性
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipped' => 'boolean',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * 予約した時点の商品名と重さ（「じゃがいも 2kg」）
     */
    public function itemLabel(): string
    {
        return ItemLabel::of($this->item_name, $this->item_weight);
    }

    /**
     * 予約した会員（退会済みの会員を含む）
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * 予約した商品（論理削除済みを含む。表示には予約した時点の item_name・item_weight を使う）
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /**
     * 銀行振込の振込先（銀行振込の予約だけ）
     *
     * @return HasOne<Reservation_bank_transfer, $this>
     */
    public function reservationBankTransfer(): HasOne
    {
        return $this->hasOne(Reservation_bank_transfer::class);
    }
}
