<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\RegisteredUserController as FortifyRegisteredUserController;
use Override;

/**
 * 会員登録のコントローラー（FortifyのRegisteredUserControllerを差し替えて使う）
 *
 * Fortify標準のstoreは、メールアドレスを小文字にするときに文字列を前提としているため、
 * 配列などが送られると入力の検証より先に500になる。文字列のときだけ小文字にして、
 * それ以外は検証（CreateNewUser）でエラーにする。
 */
class RegisteredUserController extends FortifyRegisteredUserController
{
    /**
     * 会員を登録してログインする
     */
    #[Override]
    public function store(Request $request, CreatesNewUsers $creator): RegisterResponse
    {
        Log::debug('RegisteredUserController start');

        $username = $request->input(Fortify::username());
        if (config('fortify.lowercase_usernames') && is_string($username)) {
            $request->merge([Fortify::username() => mb_strtolower($username)]);
        }

        event(new Registered($user = $creator->create($request->all())));

        $this->guard->login($user, $request->boolean('remember'));

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        Log::debug('RegisteredUserController end');

        return app(RegisterResponse::class);
    }
}
