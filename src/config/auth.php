<?php

use App\Models\Admin;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // 管理者（会員の web ガードとは別のセッションキーで、完全に分離する）
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'admins' => [
            'driver' => 'eloquent',
            'model' => Admin::class,
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
        // 管理者（会員とはトークンのテーブルも別。有効期限・再送信の待ち時間は会員と同じ）
        'admins' => [
            'provider' => 'admins',
            'table' => 'admin_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 管理者の二段階認証（メールのワンタイムコード）
    |--------------------------------------------------------------------------
    |
    | enabled：オン・オフ（.env の ADMIN_TWO_FACTOR_ENABLED。既定はオン。ポートフォリオの公開デモはオフにする）
    | expire：コードの有効期限（分）
    | max_attempts：コードを間違えられる回数（この回数目の失敗でコードを無効にする）
    | resend_seconds：前回の送信からコードを再送できるまでの秒数
    | miss_limit・miss_window_seconds：同じ管理者の確認コードの間違いを、ログインし直しても（別の端末・IPからでも）合わせて、この秒数のあいだに何回まで許すか。上限に達したら、その時間が過ぎるまでログインできない
    |
    */

    'admin_two_factor' => [
        // オフになるのは false・0・off・no のときだけ（大文字小文字・前後の空白は問わない）。未設定・空・null・不正な値などはすべて安全側のオンにする。
        // .env に null と書くと env() は PHP の null を返し、false と書くと PHP の false を返す
        'enabled' => ! in_array(
            strtolower(trim(is_string($twoFactorEnv = env('ADMIN_TWO_FACTOR_ENABLED', true)) ? $twoFactorEnv : ($twoFactorEnv === false ? 'false' : 'on'))),
            ['false', '0', 'off', 'no'],
            true,
        ),
        'expire' => 10,
        'max_attempts' => 5,
        'resend_seconds' => 60,
        'miss_limit' => 20,
        'miss_window_seconds' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the amount of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
