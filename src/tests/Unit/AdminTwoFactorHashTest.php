<?php

namespace Tests\Unit;

use App\Support\AdminTwoFactorHash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 管理者の二段階認証のハッシュ（AdminTwoFactorHash。HMAC-SHA256）のテスト
 */
class AdminTwoFactorHashTest extends TestCase
{
    /**
     * コードのハッシュは、アプリケーションキーを鍵にした HMAC-SHA256（16進の64文字）で、同じコードなら同じ値になる
     */
    #[Test]
    public function コードのハッシュは鍵つきのhmac_sha256(): void
    {
        $hash = AdminTwoFactorHash::code('012345');

        $this->assertSame(hash_hmac('sha256', 'code|012345', (string) config('app.key')), $hash);
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $hash);
        $this->assertSame($hash, AdminTwoFactorHash::code('012345'));
        $this->assertNotSame($hash, AdminTwoFactorHash::code('012346'));
        // コードそのものや、鍵なしのハッシュではない
        $this->assertNotSame('012345', $hash);
        $this->assertNotSame(hash('sha256', 'code|012345'), $hash);
    }

    /**
     * アプリケーションキーが違えば、同じコードでもハッシュが変わる（DB だけが漏れても、鍵がなければ同じハッシュを作れない）
     */
    #[Test]
    public function 鍵が違えばハッシュが変わる(): void
    {
        $hash = AdminTwoFactorHash::code('123456');

        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);

        $this->assertNotSame($hash, AdminTwoFactorHash::code('123456'));
    }

    /**
     * パスワードの派生値は、パスワードのハッシュそのものではなく、コードのハッシュとも別の値になる
     */
    #[Test]
    public function パスワードの派生値はハッシュそのものではない(): void
    {
        $fingerprint = AdminTwoFactorHash::passwordFingerprint('$2y$04$example');

        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $fingerprint);
        $this->assertNotSame('$2y$04$example', $fingerprint);
        $this->assertSame($fingerprint, AdminTwoFactorHash::passwordFingerprint('$2y$04$example'));
        $this->assertNotSame($fingerprint, AdminTwoFactorHash::passwordFingerprint('$2y$04$other'));
        $this->assertNotSame(AdminTwoFactorHash::code('$2y$04$example'), $fingerprint);
    }
}
