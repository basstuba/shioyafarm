<?php

namespace App\Providers;

use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\StripePaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** お問い合わせの送信を1分間に受け付ける回数（IPアドレスごと） */
    public const int INQUIRY_MAX_ATTEMPTS = 10;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 決済サービス（テストではこのバインドをモックに差し替える）
        $this->app->bind(PaymentGatewayInterface::class, function (): StripePaymentGateway {
            $secret = config('services.stripe.secret');

            return new StripePaymentGateway(is_string($secret) ? $secret : null);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureRoutePatterns();
    }

    /**
     * ルートパラメータの形式を定義する
     */
    private function configureRoutePatterns(): void
    {
        // 商品ID：先頭が0でない18桁までの数字（PHP_INT_MAXを超えて int への変換が失敗しないようにする）
        Route::pattern('item', '[1-9][0-9]{0,17}');
    }

    /**
     * 回数制限を定義する
     */
    private function configureRateLimiting(): void
    {
        // お問い合わせの送信（メッセージの改行は、画面側でPCのときだけ <br> にする）：超えたときは429画面ではなく、入力を残してフォームへ戻し、日本語のメッセージを出す
        RateLimiter::for('inquiry', fn (Request $request) => Limit::perMinute(self::INQUIRY_MAX_ATTEMPTS)
            ->by((string) $request->ip())
            ->response(fn () => redirect()
                ->route('top.inquiry')
                ->withInput($request->only(['last_name', 'first_name', 'email', 'detail']))
                ->with('inquiry_throttled', "送信回数が多すぎます。しばらく時間をおいてから\n再度お試しください。")));
    }
}
