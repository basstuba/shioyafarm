<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\PasswordResetMail;
use App\Models\Concerns\SendsPasswordResetMail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Throwable;

/**
 * 会員
 *
 * 退会は論理削除（deleted_at）。退会した会員はログインできず、メールアドレスも物理削除されるまで再利用できない。
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, SendsPasswordResetMail, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * stripe_customer_id は含めない（会員情報の更新フォームなどから書き換えられないようにする。保存は UserRepository の forceFill で行う）。
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
     * 姓名（半角スペース区切り。管理画面の注文者の表示に使う）
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => $this->last_name.' '.$this->first_name);
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

    /**
     * パスワード再設定のメールを送る（Laravel標準の英語の通知の代わりに、日本語のメールを同期送信する。失敗時の扱いは SendsPasswordResetMail）
     *
     * @param  string  $token  再設定用のトークン
     *
     * @throws Throwable メールの送信に失敗したとき
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->sendPasswordResetMail((string) config('fortify.passwords'), new PasswordResetMail($this, $token));
    }
}
