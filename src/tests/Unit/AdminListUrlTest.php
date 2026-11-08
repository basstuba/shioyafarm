<?php

namespace Tests\Unit;

use App\Support\AdminListUrl;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AdminListUrl::resolve（一覧の戻り先の検証）のテスト
 */
class AdminListUrlTest extends TestCase
{
    private function resolve(mixed $url): string
    {
        return AdminListUrl::resolve($url, 'admin.users.index', 'admin.users.search');
    }

    /**
     * 一覧・検索結果のパスは、検索条件・ページつきのパス＋クエリで返す（スキームとホストは捨てる）
     */
    #[Test]
    public function 一覧と検索結果は検索条件つきで返す(): void
    {
        $this->assertSame('/admin/users', $this->resolve('/admin/users'));
        $this->assertSame('/admin/users/search?name=a&page=2', $this->resolve('/admin/users/search?name=a&page=2'));
        $this->assertSame('/admin/users/search?name=a', $this->resolve('https://example.test/admin/users/search?name=a'));
        $this->assertSame('/admin/users?page=3', $this->resolve('//evil.example/admin/users?page=3'));
    }

    /**
     * 一覧でないパス・文字列でない値・制御文字を含む値は、一覧の1ページ目にする
     */
    #[Test]
    public function 不正な値は一覧の1ページ目にする(): void
    {
        foreach (['/admin/purchase', '/admin/users/evil', 'https://evil.example/', '', 'http:///', "/admin/users\nX: 1", "/admin/users?a=\0", ['/admin/users'], null, 5] as $url) {
            $this->assertSame('/admin/users', $this->resolve($url));
        }
    }
}
