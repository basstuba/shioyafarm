<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * 管理画面から送ったメールの二重送信を防ぐ印を、キャッシュに出し入れする
 *
 * 同じ宛先に同じ内容を、短い間に続けて送ろうとしたとき（送信ボタンの連打・通信の再送）の2通目以降を止める。メールの履歴は保存しない。
 */
class AdminMailSendStore
{
    /** 印を残す秒数 */
    private const int TTL_SECONDS = 10;

    /**
     * 印をつける（すでに印があるときは false。確認とつけるのは1回の操作で行い、同時のリクエストでも1つだけが true になる）
     */
    public function claim(string $fingerprint): bool
    {
        return Cache::add($this->key($fingerprint), true, self::TTL_SECONDS);
    }

    /**
     * 印を外す（送信に失敗したとき。すぐにやり直せるようにする）
     */
    public function release(string $fingerprint): void
    {
        Cache::forget($this->key($fingerprint));
    }

    private function key(string $fingerprint): string
    {
        return 'admin-mail-send:'.$fingerprint;
    }
}
