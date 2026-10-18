<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Requests\LoginRequest;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
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
