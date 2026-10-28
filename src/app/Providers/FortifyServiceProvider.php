<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\NewPasswordController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordResetLinkRequest;
use App\Http\Responses\FailedPasswordResetLinkRequestResponse;
use App\Services\AreaService;
use App\Support\IntendedUrl;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\NewPasswordController as FortifyNewPasswordController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController as FortifyRegisteredUserController;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest as FortifySendPasswordResetLinkRequest;

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

        // パスワード再設定：メールアドレスの検証メッセージを日本語にし、文字列でない入力で500にならないものに差し替える
        $this->app->bind(FortifySendPasswordResetLinkRequest::class, PasswordResetLinkRequest::class);
        $this->app->bind(FortifyNewPasswordController::class, NewPasswordController::class);

        // 再設定メールの送信：未登録・退会済み・1分以内の再送信でも、送信できたときと同じ応答にする（アカウントの存在を漏らさない）
        $this->app->bind(FailedPasswordResetLinkRequestResponseContract::class, FailedPasswordResetLinkRequestResponse::class);
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

        // パスワード再設定の「メールアドレスの入力」と「新しいパスワードの入力」のページ
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', [
            // メールのリンクの ?email= の値（文字列のときだけ。入力エラーで戻ったときは old() が優先される）
            'email' => is_string($request->query('email')) ? $request->query('email') : '',
            'token' => $request->route('token'),
        ]));

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

        // パスワード再設定の送信（再設定メールの送信・新しいパスワードの設定）は、ルートごとにIPアドレスあたり1分5回まで。
        // 上限に達したとき、通常のリクエストは入力欄の近くに日本語のメッセージを表示し、JSONを期待するリクエストは429で返す。
        // 対象のルートは ThrottlePasswordReset（bootstrap/app.php で web グループに追加）で絞る。
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by($request->route()?->getName().'|'.$request->ip())->response(function (Request $request, array $headers) {
                $message = trans('passwords.rate_limited', ['seconds' => $headers['Retry-After']]);

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 429, $headers);
                }

                // メールアドレスの入力欄と、新しいパスワードの入力欄の近くに出す（再設定ページの email は無効リンクの案内になるため使わない）
                throw ValidationException::withMessages([$request->routeIs('password.update') ? 'password' : 'email' => [$message]]);
            });
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
