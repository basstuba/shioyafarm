<?php

namespace App\Support;

/**
 * 管理者の二段階認証で使うハッシュ（HMAC-SHA256。鍵はアプリケーションキー）
 *
 * 確認コードは数字6桁（100万通り）しかなく、bcrypt のような遅いハッシュにしても総当たりを防げない（防ぐのは失敗回数の上限）。
 * そのため、DB だけが漏れても元のコードを逆算できないよう、鍵つきの速いハッシュで保存する。照合は、入力のハッシュを条件にした DB の DELETE（AdminTwoFactorCodeRepository::consumeIfMatches）で行う（ハッシュは鍵がなければ作れないので、比べる時間の差からは推測できない）。
 */
final class AdminTwoFactorHash
{
    /**
     * 確認コードのハッシュ（16進の64文字）
     */
    public static function code(#[\SensitiveParameter] string $code): string
    {
        return self::hmac('code|'.$code);
    }

    /**
     * 入力待ちのセッションに入れる、パスワード（のハッシュ）の派生値
     *
     * 入力待ちの途中でパスワードが変わったかを調べるためのもの。パスワードのハッシュそのものはセッションに入れない。
     */
    public static function passwordFingerprint(string $passwordHash): string
    {
        return self::hmac('password|'.$passwordHash);
    }

    /**
     * アプリケーションキーを鍵にした HMAC-SHA256
     */
    private static function hmac(string $data): string
    {
        return hash_hmac('sha256', $data, (string) config('app.key'));
    }
}
