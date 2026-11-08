<?php

namespace Tests\Feature;

use App\Enums\MailRecipientType;
use App\Mail\AdminMessageMail;
use App\Services\MailRecipient;
use App\Support\MailTarget;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 管理画面から送るメール（AdminMessageMail）の件名・差出人・宛先・本文（HTML版・テキスト版）のテスト
 */
class AdminMessageMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.from.address' => 'shioyafarm@example.com', 'mail.from.name' => 'しおや農園']);
    }

    /**
     * 会員宛てのメールを作る
     */
    private function userMail(string $title = 'ご案内', string $body = '本文'): AdminMessageMail
    {
        return new AdminMessageMail(new MailRecipient(new MailTarget(MailRecipientType::User, 1), '山田 太郎', 'yamada@example.com'), $title, $body);
    }

    /**
     * お問い合わせへの返信のメールを作る
     */
    private function inquiryMail(string $detail, ?CarbonImmutable $at = null, string $body = '返信'): AdminMessageMail
    {
        $at ??= CarbonImmutable::parse('2026-11-03 09:30:00', config('app.timezone'));

        return new AdminMessageMail(new MailRecipient(new MailTarget(MailRecipientType::Inquiry, 5), '佐藤 花子', 'sato@example.com', $at, $detail), 'お返事', $body);
    }

    /**
     * HTML版・テキスト版を取り出す（Mailable の assertSeeInHtml / assertSeeInText と同じ描画）
     *
     * @return array{string, string}
     */
    private function render(AdminMessageMail $mail): array
    {
        $render = new ReflectionMethod($mail, 'renderForAssertions');

        return $render->invoke($mail);
    }

    /**
     * 件名は入力したタイトルの先頭に【しおや農園】を付け、差出人は config('mail.from')、宛先はお客様だけ（AC-8）
     */
    #[Test]
    public function 件名と差出人と宛先が決まる(): void
    {
        $mail = $this->userMail('UAT会員宛て');

        $mail->assertHasSubject('【しおや農園】UAT会員宛て');
        $mail->assertFrom('shioyafarm@example.com', 'しおや農園');
        $mail->assertTo('yamada@example.com');
        $this->assertCount(1, $mail->to);
    }

    /**
     * 控えは差出人と同じ農園のアドレスに BCC で送る。To・CC には入れない
     */
    #[Test]
    public function 控えは農園のアドレスに_bc_cで送る(): void
    {
        $mail = $this->userMail();

        $this->assertTrue($mail->hasBcc('shioyafarm@example.com'));
        $this->assertCount(1, $mail->envelope()->bcc);
        $this->assertFalse($mail->hasTo('shioyafarm@example.com'));
        $this->assertFalse($mail->hasCc('shioyafarm@example.com'));
    }

    /**
     * タイトルに改行があっても件名は1行（ヘッダーを増やさせない）
     */
    #[Test]
    public function タイトルの改行は件名で半角スペースになる(): void
    {
        $this->userMail("a\r\nBcc: evil@example.com")->assertHasSubject('【しおや農園】a Bcc: evil@example.com');
    }

    /**
     * 本文は入力したとおりで、改行が保たれる。HTML版は <br>、テキスト版は改行。空行も残る（AC-8）
     */
    #[Test]
    public function 本文は改行を保って出る(): void
    {
        [$html, $text] = $this->render($this->userMail(body: "1行目\n\n3行目\r\n4行目"));

        $this->assertStringContainsString("1行目<br>\n<br>\n3行目<br>\n4行目", $html);
        $this->assertStringContainsString("1行目\n\n3行目\n4行目", $text);
        $this->assertStringNotContainsString('<br', $text);
    }

    /**
     * 宛名・署名は自動で付けない。サンクスメールと同じ枠（ヘッダー・フッター）に入る
     */
    #[Test]
    public function 宛名と署名は付かず枠に入る(): void
    {
        [$html, $text] = $this->render($this->userMail(body: '本文だけ'));

        $this->assertStringNotContainsString('様', $html);
        $this->assertStringNotContainsString('様', $text);
        $this->assertStringNotContainsString('このメールは送信専用です', $text);
        $this->assertStringContainsString('しおや農園', $html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('本文だけ', $text);
    }

    /**
     * HTML の記号・Markdown の記号は、画面と同じく文字のまま表示される（AC-16）
     */
    #[Test]
    public function 本文の記号は文字のまま表示される(): void
    {
        $body = "<b>太字</b><script>alert(1)</script>\n# 見出し\n* 箇条書き\n**強調** [リンク](http://evil.example)\n1. 番号";
        [$html, $text] = $this->render($this->userMail(body: $body));

        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringNotContainsString('<ul', $html);
        $this->assertStringNotContainsString('<ol', $html);
        $this->assertStringNotContainsString('<strong>強調', $html);
        $this->assertStringNotContainsString('href="http://evil.example', $html);
        $this->assertStringContainsString('&lt;b&gt;太字&lt;/b&gt;', $html);
        $this->assertStringContainsString($body, $text);
    }

    /**
     * 行頭の空白が4つ以上あっても、コードブロックにならず空白として残る
     */
    #[Test]
    public function 行頭の空白はコードブロックにならない(): void
    {
        [$html, $text] = $this->render($this->userMail(body: "最初の行\n      字下げ\t* 記号"));

        $this->assertStringNotContainsString('<pre', $html);
        $this->assertStringNotContainsString('<code', $html);
        $this->assertStringContainsString("最初の行\n\u{A0}\u{A0}\u{A0}\u{A0}\u{A0}\u{A0}字下げ", $text);
    }

    /**
     * 本文の先頭が字下げのときも、コードブロックにならない
     */
    #[Test]
    public function 本文の先頭の字下げもコードブロックにならない(): void
    {
        // 本文の先頭の空白は、メール送信のリクエストでは取り除かれずに届く（TrimStrings の対象外）
        [$html] = $this->render($this->userMail(body: "      字下げの行\n次の行"));

        $this->assertStringNotContainsString('<pre', $html);
        $this->assertStringContainsString('字下げの行<br>', $html);
    }

    /**
     * お問い合わせへの返信には、本文の下に元のお問い合わせの問い合わせ日（YYYY/MM/DD・日本時間）・内容を引用として付ける（AC-9）
     */
    #[Test]
    public function お問い合わせへの返信に引用が付く(): void
    {
        // 問い合わせ日は、お問い合わせ一覧と同じくアプリのタイムゾーン（日本時間）のまま表示する
        $at = CarbonImmutable::parse('2026-11-04 05:00:00', config('app.timezone'));
        [$html, $text] = $this->render($this->inquiryMail("配送日を教えてください。\n急ぎです。", $at, '折り返しご連絡します。'));

        $this->assertStringContainsString("■ いただいたお問い合わせ\nお問い合わせ日：2026/11/04\n配送日を教えてください。\n急ぎです。", $text);
        $this->assertGreaterThan(mb_strpos($text, '折り返しご連絡します。'), mb_strpos($text, 'お問い合わせ日：2026/11/04'));
        $this->assertStringContainsString('お問い合わせ日：2026/11/04<br>', $html);
        $this->assertStringContainsString("<br>\n急ぎです。", $html);
    }

    /**
     * 会員宛てには引用を付けない
     */
    #[Test]
    public function 会員宛てには引用が付かない(): void
    {
        [$html, $text] = $this->render($this->userMail());

        $this->assertStringNotContainsString('いただいたお問い合わせ', $html);
        $this->assertStringNotContainsString('いただいたお問い合わせ', $text);
        $this->assertStringNotContainsString('お問い合わせ日', $text);
    }

    /**
     * 引用の内容も、記号・改行・Markdown の記号をエスケープする（AC-16）
     */
    #[Test]
    public function 引用の内容もエスケープされる(): void
    {
        $detail = "<script>alert(1)</script>\n# 見出し\n<b>太字</b>";
        [$html, $text] = $this->render($this->inquiryMail($detail));

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringContainsString($detail, $text);
    }
}
