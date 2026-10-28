<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\PasswordResetMail;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * 会員
 *
 * 退会は論理削除（deleted_at）。退会した会員はログインできず、メールアドレスも物理削除されるまで再利用できない。
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

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
     * パスワード再設定のメールを送る（Laravel標準の英語の通知の代わりに、日本語のメールを同期送信する）
     *
     * 送信に失敗したときは、保存済みのトークンを削除してから例外を投げ直す。
     * 残したままだと、60秒以内の再送信が「送信済み」の案内になり、メールが届かないまま再送信もできなくなる。
     *
     * @param  string  $token  再設定用のトークン
     *
     * @throws Throwable メールの送信に失敗したとき
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        try {
            Mail::send(new PasswordResetMail($this, $token));
        } catch (Throwable $e) {
            /** @var PasswordBroker $broker deleteToken は契約（Contracts\Auth\PasswordBroker）にはなく、実装クラスにある */
            $broker = Password::broker(config('fortify.passwords'));
            $broker->deleteToken($this);

            throw $e;
        }
    }
}
