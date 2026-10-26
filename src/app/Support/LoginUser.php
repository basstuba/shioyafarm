<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * ログイン中の会員の取得
 *
 * 未ログイン（退会済みの会員のセッションを含む）のときは 401 にする。
 */
class LoginUser
{
    /**
     * ログイン中の会員
     */
    public static function require(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
