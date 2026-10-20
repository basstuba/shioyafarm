<?php

namespace Tests\Unit;

use App\Models\Inquiry;
use App\Repositories\Interfaces\InquiryRepositoryInterface;
use App\Services\InquiryService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * お問い合わせサービスのテスト
 */
class InquiryServiceTest extends TestCase
{
    /**
     * 入力内容に未対応（addressed=false）を加えて保存する
     */
    #[Test]
    public function 未対応の状態で保存する(): void
    {
        $expected = [
            'last_name' => '山田',
            'first_name' => '太郎',
            'email' => 'sample@example.com',
            'detail' => '直売所はありますか？',
            'addressed' => false,
        ];
        $repository = Mockery::mock(InquiryRepositoryInterface::class);
        $repository->shouldReceive('create')->once()->with($expected)->andReturn(new Inquiry($expected));

        $inquiry = (new InquiryService($repository))->createInquiry([
            'last_name' => '山田',
            'first_name' => '太郎',
            'email' => 'sample@example.com',
            'detail' => '直売所はありますか？',
        ]);

        $this->assertFalse($inquiry->addressed);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
