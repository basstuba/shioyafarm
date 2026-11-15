<?php

namespace Tests\Unit;

use App\Mail\RegistrationMail;
use App\Models\User;
use App\Support\DeferredMail;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * メールの送信をレスポンスの後に遅らせる共通の処理（DeferredMail）のテスト
 */
class DeferredMailTest extends TestCase
{
    /**
     * 呼んだ時点ではメールを作らず・送らず、リクエストの終了時（遅らせた処理の実行時）に作って送る。キューは使わない
     */
    #[Test]
    public function 送信は遅らせた処理が実行されるまで行われない(): void
    {
        Mail::fake();
        $built = false;
        $user = new User(['email' => 'deferred@example.com']);

        DeferredMail::send(function () use (&$built, $user): RegistrationMail {
            $built = true;

            return new RegistrationMail($user);
        }, '送信に失敗しました');

        $this->assertFalse($built);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();

        app(DeferredCallbackCollection::class)->invoke();

        $this->assertTrue($built);
        Mail::assertSentCount(1);
        Mail::assertSent(RegistrationMail::class, fn (RegistrationMail $mail): bool => $mail->hasTo('deferred@example.com'));
        Mail::assertNothingQueued();
    }

    /**
     * 送信に失敗しても例外は外に出さず、渡した文言と値に、例外のクラスとメッセージを加えてログに残す
     */
    #[Test]
    public function 送信に失敗しても例外は出さずログに残す(): void
    {
        Mail::fake();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('smtp down'));
        Log::spy();
        $user = new User(['email' => 'deferred@example.com']);

        DeferredMail::send(fn () => new RegistrationMail($user), '送信に失敗しました', ['id' => 7]);
        app(DeferredCallbackCollection::class)->invoke();

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context): bool => $message === '送信に失敗しました'
                && $context === ['id' => 7, 'exception' => RuntimeException::class, 'message' => 'smtp down'])
            ->once();
    }

    /**
     * メールを作る処理で例外が起きても、外に出さずログに残す
     */
    #[Test]
    public function メールを作る処理の失敗もログに残す(): void
    {
        Mail::fake();
        Log::spy();

        DeferredMail::send(fn () => throw new RuntimeException('build failed'), '送信に失敗しました');
        app(DeferredCallbackCollection::class)->invoke();

        Mail::assertNothingSent();
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context): bool => $context['message'] === 'build failed')
            ->once();
    }
}
