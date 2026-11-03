<?php

namespace App\Http\Requests;

/**
 * 管理者ログインのリクエスト
 *
 * ルールとエラーメッセージは会員のログイン（LoginRequest）と同じ。
 * 入力エラーのときは管理者用ログインページへ戻す。
 */
class AdminLoginRequest extends LoginRequest
{
    /**
     * 入力エラーのときの戻り先のルート名
     *
     * @var string
     */
    protected $redirectRoute = 'admin.login';
}
