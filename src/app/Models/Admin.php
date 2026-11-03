<?php

namespace App\Models;

use App\Mail\AdminPasswordResetMail;
use App\Models\Concerns\SendsPasswordResetMail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Override;
use Throwable;

/**
 * 管理者
 *
 * 会員（User）とは別のテーブル・別のガード（admin）でログインする。
 * 「ログイン状態を保持する」は使わないため、remember_token のカラムはない。
 */
class Admin extends Authenticatable
{
    use HasFactory, SendsPasswordResetMail;

    /** パスワード再設定のトークンを扱うパスワードブローカーの名前（config/auth.php の passwords） */
    public const string PASSWORD_BROKER = 'admins';

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

    /**
     * パスワード再設定のメールを送る（Laravel標準の英語の通知の代わりに、日本語のメールを同期送信する。失敗時の扱いは SendsPasswordResetMail）
     *
     * @param  string  $token  再設定用のトークン
     *
     * @throws Throwable メールの送信に失敗したとき
     */
    #[Override]
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->sendPasswordResetMail(self::PASSWORD_BROKER, new AdminPasswordResetMail($this, $token));
    }
}
