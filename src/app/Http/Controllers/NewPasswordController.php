<?php

namespace App\Http\Controllers;

use App\Http\Requests\NewPasswordRequest;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Http\Controllers\NewPasswordController as FortifyNewPasswordController;
use Override;

/**
 * 新しいパスワードの設定のコントローラー（FortifyのNewPasswordControllerを差し替えて使う）
 *
 * Fortify標準のstoreは、メールアドレスを小文字にするときに文字列を前提としているため、
 * 配列などが送られると入力の検証より先に500になる。また検証メッセージが英語のままなので、
 * 先に FormRequest（NewPasswordRequest）で検証して、日本語のメッセージにする。
 * 検証の後の処理はFortify標準のまま。パスワードの桁数・確認用との一致の検証は、トークンの確認の後に ResetUserPassword で行う。
 */
class NewPasswordController extends FortifyNewPasswordController
{
    /**
     * 新しいパスワードを保存する
     *
     * 入力の検証（FormRequest。文字列でない入力や日本語のメッセージはここで扱う）の後は、Fortify標準の処理に任せる。
     * 親の store が受け取るのは Request なので、引数の型は変えず、NewPasswordRequest はここで解決して検証する。
     */
    #[Override]
    public function store(Request $request): Responsable
    {
        Log::debug('NewPasswordController start');

        // FormRequest はコンテナで解決した時点で検証が走り、失敗すると ValidationException で元のページへ戻る（戻り値は使わない）
        app(NewPasswordRequest::class);

        $response = parent::store($request);

        Log::debug('NewPasswordController end');

        return $response;
    }
}
