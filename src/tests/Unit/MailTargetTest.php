<?php

namespace Tests\Unit;

use App\Enums\MailRecipientType;
use App\Support\MailTarget;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MailTarget（メール作成の宛先の種類とID・作成ページのURL・戻り先）のテスト
 */
class MailTargetTest extends TestCase
{
    /**
     * 種類が user・inquiry の文字列で、IDが1以上の整数（文字列か整数）のときだけ作れる
     */
    #[Test]
    public function 種類と_i_dが正しいときだけ作れる(): void
    {
        $target = MailTarget::fromInput('inquiry', '12');

        $this->assertSame(MailRecipientType::Inquiry, $target?->type);
        $this->assertSame(12, $target?->id);
        $this->assertSame(MailRecipientType::User, MailTarget::fromInput('user', 5)?->type);

        foreach ([['admin', 1], [['user'], 1], [null, 1], ['user', 0], ['user', '-1'], ['user', 'abc'], ['user', '99999999999999999999'], ['user', ['1']], ['user', null], ['user', 1.5]] as [$type, $id]) {
            $this->assertNull(MailTarget::fromInput($type, $id), json_encode([$type, $id]));
        }
    }

    /**
     * 作成ページのURLには、使える戻り先だけがクエリで付く
     */
    #[Test]
    public function 作成ページの_ur_lに使える戻り先だけが付く(): void
    {
        $target = new MailTarget(MailRecipientType::User, 7);
        $back = '/admin/users/search?name=a&page=2';

        $this->assertSame('/admin/mail/user/7?back='.urlencode($back), $target->createUrl($back));
        $this->assertSame('/admin/mail/user/7', $target->createUrl(null));
        $this->assertSame('/admin/mail/user/7', $target->createUrl('https://evil.example/'));
        $this->assertSame('/admin/mail/user/7', $target->createUrl('/admin/inquiries'));
        $this->assertSame('/admin/mail/inquiry/3', (new MailTarget(MailRecipientType::Inquiry, 3))->createUrl('/admin/users'));
    }

    /**
     * 戻り先は候補の順に使えるものを選び、なければ一覧の1ページ目
     */
    #[Test]
    public function 戻り先は使える候補を順に選ぶ(): void
    {
        $target = new MailTarget(MailRecipientType::Inquiry, 3);

        $this->assertSame('/admin/inquiries/search?page=2', $target->listUrl(null, '/admin/users', 'http://localhost/admin/inquiries/search?page=2', '/admin/inquiries'));
        $this->assertSame('/admin/inquiries', $target->listUrl(['x'], 'https://evil.example/', null));
        $this->assertSame('/admin/inquiries', $target->listUrl());
    }

    /**
     * 種類だけを取り出せる（文字列で user・inquiry のどちらかのときだけ）
     */
    #[Test]
    public function 種類だけを取り出せる(): void
    {
        $this->assertSame(MailRecipientType::User, MailTarget::typeFromInput('user'));
        $this->assertSame(MailRecipientType::Inquiry, MailTarget::typeFromInput('inquiry'));

        foreach (['admin', '', ['user'], null, 1] as $type) {
            $this->assertNull(MailTarget::typeFromInput($type));
        }
    }
}
