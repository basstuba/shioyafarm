<?php

namespace App\Http\Requests;

/**
 * 管理者の新しいパスワードの設定のリクエスト
 *
 * ルールとエラーメッセージは会員の新しいパスワードの設定（NewPasswordRequest）と同じ。
 * パスワードの桁数・確認用との一致は、トークンの確認の後に AdminPasswordResetService で検証する。
 * 入力エラーのときは直前のページ（メールのリンクの再設定ページ）へ戻す。
 */
class AdminNewPasswordRequest extends NewPasswordRequest {}
