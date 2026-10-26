<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Requests\LoginRequest;
use App\Services\AreaService;
use App\Support\IntendedUrl;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\RegisteredUserController as FortifyRegisteredUserController;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fortifyのログイン用リクエストを、プロジェクトのFormRequestに差し替える
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);

        // 会員登録のコントローラーも、文字列でない入力で500にならないものに差し替える
        $this->app->bind(FortifyRegisteredUserController::class, RegisteredUserController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ログイン・会員登録の後は、ページを開く前に見ていたページへ戻す
        Fortify::loginView(function (Request $request) {
            IntendedUrl::rememberPrevious($request);

            return view('auth.login');
        });

        // 会員登録ページには、お住まいの地域の選択肢として都道府県の一覧を渡す
        Fortify::registerView(function (Request $request) {
            IntendedUrl::rememberPrevious($request);

            return view('auth.register', [
                'prefectures' => app(AreaService::class)->getPrefectures(),
            ]);
        });

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        RateLimiter::for('login', function (Request $request) {
            // 配列などの文字列でない入力でもTypeErrorにならないよう、文字列のときだけ使う
            $username = $request->input(Fortify::username());
            $throttleKey = Str::transliterate(Str::lower(is_string($username) ? $username : '').'|'.$request->ip());

            // 上限に達したとき、通常のリクエストはログインページに日本語のエラーメッセージを表示する。
            // JSONを期待するリクエストは、入力エラーと区別できるよう429で返す。
            return Limit::perMinute(5)->by($throttleKey)->response(function (Request $request, array $headers) {
                $message = trans('auth.throttle', ['seconds' => $headers['Retry-After']]);

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 429, $headers);
                }

                throw ValidationException::withMessages([Fortify::username() => [$message]]);
            });
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
