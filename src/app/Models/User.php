<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * 会員
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * stripe_customer_id は含めない（会員情報の更新フォームなどから書き換えられないようにする。保存は forceFill で行う）。
     *
     * @var list<string>
     */
    protected $fillable = [
        'last_name',
        'first_name',
        'prefecture',
        'city',
        'tell',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'stripe_customer_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * 登録した配送先住所
     *
     * @return HasMany<Delivery_address, $this>
     */
    public function deliveryAddresses(): HasMany
    {
        return $this->hasMany(Delivery_address::class);
    }

    /**
     * 購入履歴
     *
     * @return HasMany<Purchase_history, $this>
     */
    public function purchaseHistories(): HasMany
    {
        return $this->hasMany(Purchase_history::class);
    }

    /**
     * 予約
     *
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
