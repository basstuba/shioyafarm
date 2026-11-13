<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RegistrationMailService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\StatefulGuard;
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
 * 登録に成功したら、会員登録完了メールの送信を RegistrationMailService に任せる（送信はレスポンスの後）。
 */
class RegisteredUserController extends FortifyRegisteredUserController
{
    /**
     * Service をコンストラクタで受け取る（規約の「Service はメソッドインジェクション」の例外）
     *
     * store() は Fortify の同名メソッドを上書きしているため、引数を足せない
     * （必須の引数を足すとシグネチャ違反、既定値付きにするとルートの依存解決で null が渡る）。
     */
    public function __construct(StatefulGuard $guard, private readonly RegistrationMailService $registrationMail)
    {
        parent::__construct($guard);
    }

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

        // 入力エラーのときは create() が例外を投げるので、ここに来るのは登録に成功したときだけ
        if ($user instanceof User) {
            $this->registrationMail->send($user);
        } else {
            // 通常は User が返る。想定外の型だったときは、メールを送れないことを記録する（登録とログインは続ける）
            Log::warning('会員登録完了メールを送れませんでした（登録された会員が User ではありません）', [
                'user_class' => $user::class,
                'user_id' => $user->getAuthIdentifier(),
            ]);
        }

        $this->guard->login($user, $request->boolean('remember'));

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        Log::debug('RegisteredUserController end');

        return app(RegisterResponse::class);
    }
}
