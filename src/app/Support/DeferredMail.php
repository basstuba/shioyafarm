<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * メールの送信を、レスポンスを返した後（defer）に行う共通の処理
 *
 * ここでは送信を defer に登録するだけで、実際に送るのはリクエストの終了時なので、画面の表示はメールサーバーの応答を待たない。
 * 登録後に処理が例外で終わっても（レスポンスが500でも）送る（always）。キューは使わず、同じプロセスの中で送る。
 * 送信に失敗しても例外は外に出さない（会員登録・購入・予約は保存済みのため）。失敗はログに残す。
 */
final class DeferredMail
{
    /**
     * メールの送信を登録する
     *
     * $build はメールを作る処理で、送る直前（リクエストの終了時）に呼ばれる。$failureMessage は送信に失敗したときのログの文言、
     * $context は失敗のログに添える値（例外のクラスとメッセージは自動で加える）。
     *
     * @param  Closure(): Mailable  $build
     * @param  array<string, mixed>  $context
     */
    public static function send(Closure $build, string $failureMessage, array $context = []): void
    {
        defer(function () use ($build, $failureMessage, $context): void {
            try {
                Mail::send($build());
            } catch (Throwable $e) {
                Log::error($failureMessage, $context + [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }, always: true);
    }
}
