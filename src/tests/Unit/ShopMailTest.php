<?php

namespace Tests\Unit;

use App\Mail\ShopMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * しおや農園のメールの共通部分（差出人・件名の先頭・署名・お問い合わせページのURL）のテスト
 */
class ShopMailTest extends TestCase
{
    /**
     * protected のメソッドを呼べるようにした、検証用のメール
     */
    private function mail(): ShopMail
    {
        return new class extends ShopMail
        {
            public function envelopeFor(string $subject, bool $bccSender = false): Envelope
            {
                return $this->shopEnvelope($subject, $bccSender);
            }

            /**
             * @return array<string, string>
             */
            public function signature(): array
            {
                return $this->signatureData();
            }

            /**
             * @return array<string, string>
             */
            public function common(): array
            {
                return $this->commonData();
            }
        };
    }

    /**
     * 件名の先頭の文字
     */
    #[Test]
    public function 件名の先頭は農園の名前の隅付き括弧(): void
    {
        $this->assertSame('【しおや農園】', ShopMail::SUBJECT_PREFIX);
    }

    /**
     * 差出人は config('mail.from')。BCC を付けなければ控えは送らない
     */
    #[Test]
    public function 差出人は設定の農園のアドレスで控えは付けない(): void
    {
        config(['mail.from.address' => 'farm@example.com', 'mail.from.name' => 'テスト農園']);

        $envelope = $this->mail()->envelopeFor('【しおや農園】件名');

        $this->assertSame('【しおや農園】件名', $envelope->subject);
        $this->assertEquals(new Address('farm@example.com', 'テスト農園'), $envelope->from);
        $this->assertSame([], $envelope->bcc);
    }

    /**
     * BCC を付けると、差出人と同じ農園のアドレスに控えを送る
     */
    #[Test]
    public function 控えを付けると差出人と同じアドレスに控えを送る(): void
    {
        config(['mail.from.address' => 'farm@example.com', 'mail.from.name' => 'テスト農園']);

        $envelope = $this->mail()->envelopeFor('件名', bccSender: true);

        $this->assertEquals([new Address('farm@example.com', 'テスト農園')], $envelope->bcc);
    }

    /**
     * 署名の値は差出人と同じ。お問い合わせページのURLは共通の値にだけ入る
     */
    #[Test]
    public function 署名とお問い合わせページのリンク(): void
    {
        config(['mail.from.address' => 'farm@example.com', 'mail.from.name' => 'テスト農園']);
        $mail = $this->mail();

        $this->assertSame(['senderName' => 'テスト農園', 'senderAddress' => 'farm@example.com'], $mail->signature());
        $this->assertSame(
            ['senderName' => 'テスト農園', 'senderAddress' => 'farm@example.com', 'contactUrl' => route('top.inquiry')],
            $mail->common(),
        );
    }
}
