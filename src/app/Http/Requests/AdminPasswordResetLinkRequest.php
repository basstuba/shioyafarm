<?php

namespace App\Http\Requests;

/**
 * 管理者のパスワード再設定メールの送信のリクエスト
 *
 * ルールとエラーメッセージは会員の再設定メールの送信（PasswordResetLinkRequest）と同じ。
 * 入力エラーのときは管理者用の送信ページへ戻す。
 */
class AdminPasswordResetLinkRequest extends PasswordResetLinkRequest
{
    /**
     * 入力エラーのときの戻り先のルート名
     *
     * @var string
     */
    protected $redirectRoute = 'admin.password.request';
}
